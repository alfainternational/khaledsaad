/*
 * بيكون الإحصاءات — ما لا يستطيع الخادم قياسه.
 *
 * خارج حزمة Vite عمدًا، ويُحمَّل بوسم script مباشر.
 *
 * السبب أن القياس يجب ألّا يتوقّف لأن حزمة الأصول قديمة في متصفح الزائر
 * أو لأن دفعة نشر لم تشمل `public/build` — وهو ما وقع فعلًا: نشر ملفات
 * الإحصاءات وحدها كان سيصل بلا سكربتها، فيُقاس «من أين جاء» ولا يُقاس
 * «كم قعد» إطلاقًا.
 *
 * الخادم يعرف أن الصفحة طُلبت. هذا الملف يعرف هل قُرئت: كم ثانية **نشطة**
 * قضاها الزائر فيها، وإلى أي عمق مرّر، وبماذا تفاعل، ومتى غادر.
 *
 * ثلاثة قرارات تحكم كل ما تحته:
 *
 * ١ — الزمن النشط لا زمن التبويب. العدّاد يقف عند إخفاء التبويب وعند
 *     خمول الزائر. بدون هذا يصير التبويب المنسيّ ثلاث ساعات «قراءة»،
 *     ويتحوّل متوسط مدة البقاء إلى رقم لا يصف أحدًا.
 *
 * ٢ — الإرسال إجمالي لا تفاضلي. كل نبضة تحمل مجموع ثواني هذه الصفحة،
 *     فنبضة ضائعة على شبكة متذبذبة لا تُنقص الرقم، ونبضة مكرّرة لا
 *     تضاعفه. الخادم يُسنِد الأكبر ولا يجمع.
 *
 * ٣ — لا شيء يُرسل عن صفحة لم يسجّلها الخادم. المعرّفات تأتي من وسوم
 *     تحقنها الصفحة نفسها؛ غيابها يعني أن الالتقاط مُطفأ، فيصمت الملف.
 */

(function () {

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? null;

const visit = meta('insights-visit');
const view = meta('insights-view');
const endpoint = meta('insights-endpoint');

if (visit && view && endpoint) {
    const heartbeatSeconds = Number(meta('insights-heartbeat')) || 15;
    const idleAfter = (Number(meta('insights-idle')) || 60) * 1000;

    let activeSeconds = 0;
    let scrollPercent = 0;
    let interactions = 0;
    let lastInput = Date.now();
    let sent = false;

    /* الزائر «نشط» إذا كان التبويب ظاهرًا وتحرّك خلال نافذة الخمول. */
    const isActive = () =>
        document.visibilityState === 'visible' && Date.now() - lastInput < idleAfter;

    const send = (type, extra = {}, useBeacon = false) => {
        const body = JSON.stringify({
            visit,
            view,
            type,
            active_seconds: activeSeconds,
            scroll_percent: scrollPercent,
            interactions,
            context: {
                /* المنطقة الزمنية: أقوى إشارة داخلية على البلد، وتبقى فرضية. */
                tz: Intl.DateTimeFormat().resolvedOptions().timeZone,
                sw: window.screen?.width,
                sh: window.screen?.height,
                vw: window.innerWidth,
                vh: window.innerHeight,
            },
            ...extra,
        });

        /*
         * عند المغادرة `sendBeacon` وحده يصل: fetch العادي يُلغى مع إغلاق
         * الصفحة، فتضيع آخر نبضة — وهي أهمّها لأنها تحمل الزمن الكامل.
         */
        if (useBeacon && navigator.sendBeacon) {
            navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain;charset=UTF-8' }));

            return;
        }

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body,
            keepalive: true,
            credentials: 'same-origin',
        }).catch(() => {});
    };

    /* عدّاد الثانية: يزيد فقط حين يكون الزائر نشطًا فعلًا. */
    setInterval(() => {
        if (isActive()) activeSeconds += 1;
    }, 1000);

    setInterval(() => {
        if (activeSeconds > 0 && isActive()) send('heartbeat');
    }, heartbeatSeconds * 1000);

    /* أي حركة تُجدّد النشاط. passive حتى لا يتأخر التمرير بسبب القياس. */
    ['mousemove', 'keydown', 'scroll', 'touchstart', 'click'].forEach((event) =>
        document.addEventListener(event, () => { lastInput = Date.now(); }, { passive: true }),
    );

    /*
     * عمق التمرير: النسبة القصوى التي بلغها الزائر، لا موضعه الحالي.
     * من نزل إلى القاع ثم عاد للأعلى قرأ الصفحة كاملة، والموضع الحالي
     * وحده كان سيسجّله «لم يتجاوز الثلث».
     */
    const measureScroll = () => {
        const doc = document.documentElement;
        const scrollable = doc.scrollHeight - window.innerHeight;
        const percent = scrollable <= 0 ? 100 : Math.round(((window.scrollY || 0) / scrollable) * 100);
        scrollPercent = Math.min(100, Math.max(scrollPercent, percent));
    };

    window.addEventListener('scroll', measureScroll, { passive: true });
    measureScroll();

    /*
     * الأحداث التلقائية: ما يفعله الزائر بلا أن يكتب أحد سطرًا لكل زر.
     *
     * الروابط الخارجية والتنزيلات والاتصال والواتساب — هذه هي «المغادرة
     * المقصودة»، وبدونها تبدو الصفحة نهاية طريق بينما كانت جسرًا.
     */
    document.addEventListener('click', (event) => {
        interactions += 1;

        const link = event.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href') || '';

        if (/^(tel:|mailto:|https?:\/\/(wa\.me|api\.whatsapp))/i.test(href)) {
            send('event', { event: { name: 'contact_click', category: 'conversion', label: href.slice(0, 180) } });

            return;
        }

        if (/\.(pdf|zip|xlsx?|docx?|pptx?|csv|apk)(\?|$)/i.test(href)) {
            send('event', { event: { name: 'download', category: 'engagement', label: href.slice(0, 180) } });

            return;
        }

        if (link.host && link.host !== window.location.host) {
            send('event', { event: { name: 'outbound_click', category: 'navigation', label: link.host } });
        }
    }, { passive: true });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        /* اسم النموذج لا محتواه: ما يُكتب في الحقول لا يغادر المتصفح. */
        send('event', {
            event: {
                name: form.dataset.insightsEvent || 'form_submit',
                category: 'conversion',
                label: (form.getAttribute('action') || window.location.pathname).slice(0, 180),
            },
        });
    }, { passive: true });

    /*
     * المغادرة: `pagehide` هو الحدث الوحيد الموثوق على iOS، و`beforeunload`
     * لا يقع هناك أصلًا. والإخفاء يُرسل أيضًا لأن من ينتقل إلى تبويب آخر
     * ولا يعود لا يُطلق شيئًا بعدها.
     */
    const finish = () => {
        if (sent) return;
        sent = true;
        send('exit', {}, true);
    };

    window.addEventListener('pagehide', finish);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'hidden') {
            /* عاد إلى التبويب: الجلسة مستمرة، فيُعاد فتح باب الإرسال. */
            sent = false;

            return;
        }

        if (activeSeconds > 0) send('exit', {}, true);
    });

    /*
     * واجهة للصفحات: تسجيل حدث منتج بسطر واحد.
     * مثال: window.ksInsights.track('diagnosis_started', { category: 'conversion' })
     */
    window.ksInsights = {
        track(name, options = {}) {
            send('event', { event: { name, category: options.category || 'interaction', label: options.label, value: options.value, meta: options.meta } });
        },
    };
}
})();

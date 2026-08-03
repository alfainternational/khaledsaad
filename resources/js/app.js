import './bootstrap';
import './content-editor';

document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-mobile-menu]');

    if (toggle && menu) {
        const closeMenu = () => {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'فتح القائمة');
            menu.hidden = true;
        };

        toggle.addEventListener('click', () => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';

            toggle.setAttribute('aria-expanded', String(!isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'فتح القائمة' : 'إغلاق القائمة');
            menu.hidden = isOpen;
        });

        menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
                toggle.focus();
            }
        });
    }

    /* ------------------------------------------------------------------
     * الحفظ التلقائي (بند ٧): أي نموذج data-autosave يُحفظ بعد سكون قصير
     * عبر نفس مسار الحفظ العادي، مع مؤشر «حُفظ تلقائيًا HH:MM».
     * ------------------------------------------------------------------ */
    document.querySelectorAll('form[data-autosave]').forEach((form) => {
        const note = document.createElement('p');
        note.className = 'autosave-note';
        note.setAttribute('role', 'status');
        note.hidden = true;
        form.prepend(note);

        let timer = null;
        let inFlight = false;

        const save = () => {
            if (inFlight) return;
            inFlight = true;
            note.hidden = false;
            note.textContent = 'يحفظ الآن…';

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: new FormData(form),
            })
                .then((response) => (response.ok ? response.json() : Promise.reject()))
                .then((payload) => {
                    note.textContent = 'حُفظت إجاباتك تلقائيًا — ' + (payload.saved_at ?? '');
                })
                .catch(() => {
                    note.textContent = 'تعذّر الحفظ التلقائي — إجاباتك تُحفظ عند الضغط على متابعة.';
                })
                .finally(() => {
                    inFlight = false;
                });
        };

        form.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(save, 1500);
        });

        form.addEventListener('submit', () => clearTimeout(timer));
    });

    /* ------------------------------------------------------------------
     * فهرس التقرير اللاصق (بند ٣٣): يُبنى من عناوين الأقسام الفعلية،
     * فلا ينجرف عن المحتوى أبدًا.
     * ------------------------------------------------------------------ */
    const tocHost = document.querySelector('[data-report-toc]');
    if (tocHost) {
        const headings = document.querySelectorAll('main section[aria-labelledby] > h2, main section > h2.section-title');
        if (headings.length >= 3) {
            const list = document.createElement('ol');
            list.className = 'report-toc__list';

            headings.forEach((heading, index) => {
                if (!heading.id) heading.id = 'toc-section-' + index;
                const item = document.createElement('li');
                const link = document.createElement('a');
                link.href = '#' + heading.id;
                link.textContent = heading.textContent.trim();
                item.appendChild(link);
                list.appendChild(item);
            });

            tocHost.querySelectorAll(':scope > a').forEach((fallback) => fallback.remove());
            tocHost.appendChild(list);
            tocHost.hidden = false;

            if ('IntersectionObserver' in window) {
                const links = list.querySelectorAll('a');
                const spy = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        links.forEach((link) => link.classList.toggle('is-current', link.hash === '#' + entry.target.id));
                    });
                }, { rootMargin: '-20% 0px -70% 0px' });
                headings.forEach((heading) => spy.observe(heading));
            }
        }
    }

    /* ------------------------------------------------------------------
     * سحب المهام بين الأعمدة (بند ٢٧): تحسين تدريجي فوق نموذج الحالة
     * الموجود — بدون سحب تبقى القائمة تعمل كما هي.
     * ------------------------------------------------------------------ */
    const board = document.querySelector('[data-task-board]');
    if (board) {
        board.querySelectorAll('[data-task-id]').forEach((card) => {
            card.setAttribute('draggable', 'true');
            card.addEventListener('dragstart', (event) => {
                event.dataTransfer.setData('text/task-id', card.dataset.taskId);
                event.dataTransfer.effectAllowed = 'move';
                card.classList.add('is-dragging');
            });
            card.addEventListener('dragend', () => card.classList.remove('is-dragging'));
        });

        board.querySelectorAll('[data-task-column]').forEach((column) => {
            column.addEventListener('dragover', (event) => {
                event.preventDefault();
                column.classList.add('is-drop-target');
            });
            column.addEventListener('dragleave', () => column.classList.remove('is-drop-target'));
            column.addEventListener('drop', (event) => {
                event.preventDefault();
                column.classList.remove('is-drop-target');

                const id = event.dataTransfer.getData('text/task-id');
                const card = board.querySelector('[data-task-id="' + id + '"]');
                if (!card) return;

                const select = card.querySelector('select[name="status"]');
                if (!select) return;

                select.value = column.dataset.taskColumn;
                column.appendChild(card);
                select.form.requestSubmit();
            });
        });
    }

    /* ------------------------------------------------------------------
     * نسخ رابط المشاركة (بند ١٨): نقرة واحدة والرابط في الحافظة.
     * ------------------------------------------------------------------ */
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-copy-link]');
        if (!button) return;

        const original = button.textContent;
        navigator.clipboard
            .writeText(button.dataset.copyLink)
            .then(() => {
                button.textContent = 'نُسخ الرابط ✓';
            })
            .catch(() => {
                window.prompt('انسخ الرابط يدويًا:', button.dataset.copyLink);
            })
            .finally(() => {
                setTimeout(() => {
                    button.textContent = original;
                }, 2500);
            });
    });

    /* ------------------------------------------------------------------
     * جولة أول استخدام (بند ١٩): ثلاث تلميحات خفيفة تظهر مرة واحدة.
     * ------------------------------------------------------------------ */
    const tourSteps = document.querySelectorAll('[data-tour]');
    if (tourSteps.length > 0 && !localStorage.getItem('tour-done')) {
        let index = 0;
        const tip = document.createElement('div');
        tip.className = 'tour-tip';
        tip.setAttribute('role', 'dialog');
        tip.setAttribute('aria-label', 'جولة تعريفية');
        document.body.appendChild(tip);

        const done = () => {
            localStorage.setItem('tour-done', '1');
            tip.remove();
        };

        const show = () => {
            const target = tourSteps[index];
            if (!target) return done();
            const rect = target.getBoundingClientRect();
            tip.innerHTML = '';

            const text = document.createElement('p');
            text.textContent = target.dataset.tour;
            const actions = document.createElement('div');
            actions.className = 'tour-tip__actions';

            const skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'btn btn--ghost btn--sm';
            skip.textContent = 'تخطَّ';
            skip.addEventListener('click', done);

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'btn btn--primary btn--sm';
            next.textContent = index === tourSteps.length - 1 ? 'فهمت' : 'التالي';
            next.addEventListener('click', () => {
                index += 1;
                index >= tourSteps.length ? done() : show();
            });

            actions.append(skip, next);
            tip.append(text, actions);
            tip.style.top = window.scrollY + rect.bottom + 10 + 'px';
            tip.style.insetInlineStart = Math.max(12, rect.left) + 'px';
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        };

        show();
    }

    /* ------------------------------------------------------------------
     * PWA (بند ١٥): تسجيل عامل الخدمة — تثبيت على الجوال + صفحة انقطاع.
     * ------------------------------------------------------------------ */
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    /* ------------------------------------------------------------------
     * تنبيهات المتصفح (بند ٣٢): بإذن صريح من صفحة التنبيهات. ما دام
     * تبويب مفتوحًا نراقب الجديد ونعرضه كتنبيه نظام.
     * ------------------------------------------------------------------ */
    const notifyToggle = document.querySelector('[data-browser-notifications]');

    if (notifyToggle && 'Notification' in window) {
        const refreshLabel = () => {
            notifyToggle.textContent = Notification.permission === 'granted'
                ? 'تنبيهات المتصفح مفعّلة على هذا الجهاز'
                : 'فعّل تنبيهات المتصفح على هذا الجهاز';
            notifyToggle.disabled = Notification.permission === 'granted';
        };

        refreshLabel();
        notifyToggle.hidden = Notification.permission === 'denied';
        notifyToggle.addEventListener('click', () => Notification.requestPermission().then(refreshLabel));
    }

    const feedUrl = document.body.dataset.notificationsFeed;

    if (feedUrl && 'Notification' in window && Notification.permission === 'granted') {
        const seenKey = 'notified-ids';
        const check = () => {
            fetch(feedUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => (response.ok ? response.json() : Promise.reject()))
                .then((payload) => {
                    const seen = new Set(JSON.parse(localStorage.getItem(seenKey) || '[]'));
                    (payload.data || [])
                        .filter((item) => !item.read && !seen.has(item.id))
                        .slice(0, 3)
                        .forEach((item) => {
                            new Notification(item.title, { body: item.body, icon: '/assets/brand/khaled-saad-mark.png', dir: 'rtl', lang: 'ar' });
                            seen.add(item.id);
                        });
                    localStorage.setItem(seenKey, JSON.stringify([...seen].slice(-100)));
                })
                .catch(() => {});
        };

        check();
        setInterval(check, 60000);
    }

    /*
     * نسخ المثال التطبيقي.
     *
     * مفوَّض على المستند لا مربوط بكل زر: بطاقات المهام والتوصيات تُحقن
     * ديناميكيًّا في أكثر من سطح، والربط المباشر يفقد ما وصل بعد التحميل.
     *
     * لا نعتمد على clipboard API وحده: صفحات المنصة تُفتح أحيانًا عبر
     * http في التطوير، وهناك تكون navigator.clipboard غير متاحة أصلًا،
     * فيبقى المستخدم أمام زرّ لا يفعل شيئًا.
     */
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-copy-example]');

        if (!button) {
            return;
        }

        const block = button.closest('.worked-example');
        const source = block?.querySelector('[data-copy-source]');
        const feedback = block?.querySelector('[data-copy-feedback]');

        if (!source) {
            return;
        }

        const text = source.textContent ?? '';
        const done = () => {
            if (!feedback) {
                return;
            }

            feedback.hidden = false;
            setTimeout(() => {
                feedback.hidden = true;
            }, 2000);
        };

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => selectFallback(source));

            return;
        }

        selectFallback(source);
    });

    /* تعذّر النسخ البرمجي: نُظلّل النص ليكمل المستخدم بلوحة المفاتيح. */
    function selectFallback(node) {
        const range = document.createRange();
        range.selectNodeContents(node);
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(range);
    }

    const revealElements = document.querySelectorAll('.reveal');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealElements.forEach((element) => element.classList.add('is-visible'));

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        },
        {
            rootMargin: '0px 0px -8% 0px',
            threshold: 0.08,
        },
    );

    revealElements.forEach((element) => observer.observe(element));
});

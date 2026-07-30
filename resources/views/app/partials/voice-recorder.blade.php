{{--
    مسجّل صوتي لسؤال مفتوح.

    يملأ **خانة سؤاله وحده** بالنص المنسوخ ولا يرسل النموذج. المراجعة شرط لا
    تحسين: النسخ العربي يخطئ في الأسماء والأرقام، وما يدخل الدماغ بلا مراجعة
    يصير حقيقةً مصدرها خطأ.

    يعمل بلا مكتبة خارجية — MediaRecorder مدعوم في متصفحات الجوال الحديثة،
    وحيث لا يكون مدعومًا يختفي الزر بدل أن يظهر معطّلًا.
--}}
<div class="voice-recorder" data-voice data-endpoint="{{ route('app.voice.store', $projectSlug) }}" data-max-seconds="300" hidden>
    <button type="button" class="btn btn--ghost btn--sm" data-voice-toggle>
        <span aria-hidden="true">●</span> <span data-voice-label>سجّل إجابتك صوتيًّا</span>
    </button>
    <p class="muted" data-voice-status role="status" aria-live="polite"></p>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // غياب الدعم يخفي الزر: زرٌّ ظاهر لا يعمل أسوأ من غيابه.
                if (!navigator.mediaDevices || typeof MediaRecorder === 'undefined') {
                    return;
                }

                var csrf = document.querySelector('meta[name="csrf-token"]');

                /*
                 * الصيغة تُختار من المتصفح لا تُفترض: سفاري على iOS ينتج
                 * `audio/mp4` ولا يعرف WebM إطلاقًا. كان الكود يلصق على كل
                 * تسجيل الوسم `audio/webm` والاسم `answer.webm`، فيصل إلى
                 * المزوّد ملف m4a يدّعي أنه webm — ويُرفض.
                 */
                var FORMATS = [
                    { mime: 'audio/webm;codecs=opus', extension: 'webm' },
                    { mime: 'audio/webm', extension: 'webm' },
                    { mime: 'audio/mp4', extension: 'm4a' },
                    { mime: 'audio/ogg;codecs=opus', extension: 'ogg' },
                ];

                function pickFormat() {
                    for (var index = 0; index < FORMATS.length; index++) {
                        if (typeof MediaRecorder.isTypeSupported !== 'function'
                            || MediaRecorder.isTypeSupported(FORMATS[index].mime)) {
                            return FORMATS[index];
                        }
                    }

                    return null;
                }

                function extensionFor(mimeType) {
                    var base = String(mimeType || '').split(';')[0].trim().toLowerCase();

                    for (var index = 0; index < FORMATS.length; index++) {
                        if (FORMATS[index].mime.split(';')[0] === base) {
                            return FORMATS[index].extension;
                        }
                    }

                    return 'webm';
                }

                document.querySelectorAll('[data-voice]').forEach(function (root) {
                    root.hidden = false;

                    var toggle = root.querySelector('[data-voice-toggle]');
                    var label = root.querySelector('[data-voice-label]');
                    var status = root.querySelector('[data-voice-status]');
                    var maxSeconds = Number(root.dataset.maxSeconds || 300);

                    /*
                     * الخانة تُبحث من المسجّل إلى الخارج، لا من النموذج إلى
                     * الداخل. معالج الأدوات يعرض أسئلة الخطوة كلها في نموذج
                     * واحد، فكان `form.querySelector('textarea')` يعيد **أول**
                     * خانة في الصفحة دائمًا: يسجّل المستخدم إجابة السؤال الرابع
                     * فتُكتب في السؤال الأول، ولا شيء يخبره.
                     */
                    function fieldFor(element) {
                        var previous = element.previousElementSibling;

                        while (previous) {
                            if (previous.tagName === 'TEXTAREA') return previous;
                            previous = previous.previousElementSibling;
                        }

                        var box = element.closest('.field, .question-control-group, label');

                        if (box) {
                            var owned = box.querySelector('textarea');
                            if (owned) return owned;
                        }

                        var form = element.closest('form');

                        return form ? form.querySelector('textarea') : null;
                    }

                    var field = fieldFor(root);
                    var recorder = null;
                    var chunks = [];
                    var startedAt = 0;
                    var stopTimer = null;
                    var tickTimer = null;
                    var busy = false;

                    function say(message) {
                        status.textContent = message || '';
                    }

                    function reset() {
                        clearTimeout(stopTimer);
                        clearInterval(tickTimer);
                        label.textContent = 'سجّل إجابتك صوتيًّا';
                    }

                    async function start() {
                        var format = pickFormat();

                        try {
                            var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            recorder = format ? new MediaRecorder(stream, { mimeType: format.mime }) : new MediaRecorder(stream);
                            chunks = [];
                            startedAt = Date.now();

                            recorder.ondataavailable = function (event) {
                                if (event.data.size > 0) chunks.push(event.data);
                            };

                            recorder.onstop = function () {
                                stream.getTracks().forEach(function (track) { track.stop(); });
                                upload(recorder ? recorder.mimeType : (format ? format.mime : 'audio/webm'));
                                recorder = null;
                            };

                            recorder.start();
                            label.textContent = 'أوقف التسجيل';
                            say('يسجّل الآن…');

                            /*
                             * السقف يُطبَّق هنا لا عند الخادم وحده: تسجيل ست
                             * دقائق كان يُرفع كاملًا ثم يُرفض برسالة تحقّق، أي
                             * يُدفع زمن الرفع كله ثمنًا لخطأ كان يمكن منعه.
                             */
                            stopTimer = setTimeout(function () {
                                if (recorder && recorder.state === 'recording') {
                                    say('بلغ التسجيل الحد الأقصى، ويُنسَخ الآن.');
                                    recorder.stop();
                                    reset();
                                }
                            }, maxSeconds * 1000);

                            tickTimer = setInterval(function () {
                                if (!recorder || recorder.state !== 'recording') return;

                                var left = maxSeconds - Math.round((Date.now() - startedAt) / 1000);
                                say('يسجّل الآن… بقي ' + Math.max(0, left) + ' ثانية');
                            }, 1000);
                        } catch (error) {
                            reset();
                            say('تعذّر الوصول إلى الميكروفون. اكتب إجابتك بدلًا من ذلك.');
                        }
                    }

                    async function upload(mimeType) {
                        var seconds = Math.min(maxSeconds, Math.max(1, Math.round((Date.now() - startedAt) / 1000)));
                        var extension = extensionFor(mimeType);
                        var body = new FormData();

                        body.append('audio', new Blob(chunks, { type: String(mimeType || '').split(';')[0] }), 'answer.' + extension);
                        body.append('seconds', String(seconds));

                        // زرٌّ حيٌّ أثناء الرفع يعني تسجيلين متزامنين وحجزين على السقف.
                        busy = true;
                        toggle.disabled = true;
                        say('يُنسَخ التسجيل…');

                        try {
                            if (!csrf) {
                                say('تعذّر نسخ التسجيل. حدّث الصفحة وحاول مرة أخرى.');

                                return;
                            }

                            var response = await fetch(root.dataset.endpoint, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrf.content,
                                    'Accept': 'application/json',
                                },
                                body: body,
                            });

                            var payload = await response.json();

                            if (!response.ok) {
                                // رسالة السقف تُعرض بنصّها: هي إرشاد بميزانية لا عطل.
                                say(payload.message || 'تعذّر نسخ التسجيل.');

                                return;
                            }

                            if (field) {
                                field.value = field.value
                                    ? field.value + ' ' + payload.data.text
                                    : payload.data.text;
                                field.dispatchEvent(new Event('input', { bubbles: true }));
                                field.focus();
                                say('راجع النص قبل الإرسال — النسخ قد يخطئ في الأسماء والأرقام.');

                                return;
                            }

                            /*
                             * لا خانة لهذا المسجّل: نص لا يُعرض ولا يُحفظ خسارةٌ
                             * صامتة دفع المستخدم ثمنها. يُقال له صريحًا.
                             */
                            say('نُسخ التسجيل لكن تعذّر إيجاد خانة الإجابة: ' + payload.data.text);
                        } catch (error) {
                            say('تعذّر نسخ التسجيل. حاول مرة أخرى أو اكتب إجابتك.');
                        } finally {
                            busy = false;
                            toggle.disabled = false;
                            reset();
                        }
                    }

                    toggle.addEventListener('click', function () {
                        if (busy) {
                            return;
                        }

                        if (recorder && recorder.state === 'recording') {
                            recorder.stop();
                            reset();

                            return;
                        }

                        start();
                    });
                });
            });
        </script>
    @endpush
@endonce

{{--
    مسجّل صوتي لسؤال مفتوح.

    يملأ أقرب `textarea` بالنص المنسوخ **ولا يرسل النموذج**. المراجعة شرط لا
    تحسين: النسخ العربي يخطئ في الأسماء والأرقام، وما يدخل الدماغ بلا مراجعة
    يصير حقيقةً مصدرها خطأ.

    يعمل بلا مكتبة خارجية — MediaRecorder مدعوم في متصفحات الجوال الحديثة،
    وحيث لا يكون مدعومًا يختفي الزر بدل أن يظهر معطّلًا.
--}}
<div class="voice-recorder" data-voice data-endpoint="{{ route('app.voice.store', $projectSlug) }}" hidden>
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

                document.querySelectorAll('[data-voice]').forEach(function (root) {
                    root.hidden = false;

                    var toggle = root.querySelector('[data-voice-toggle]');
                    var label = root.querySelector('[data-voice-label]');
                    var status = root.querySelector('[data-voice-status]');
                    var field = root.closest('form') ? root.closest('form').querySelector('textarea') : null;

                    var recorder = null;
                    var chunks = [];
                    var startedAt = 0;

                    function say(message) {
                        status.textContent = message || '';
                    }

                    async function start() {
                        try {
                            var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            recorder = new MediaRecorder(stream);
                            chunks = [];
                            startedAt = Date.now();

                            recorder.ondataavailable = function (event) {
                                if (event.data.size > 0) chunks.push(event.data);
                            };

                            recorder.onstop = function () {
                                stream.getTracks().forEach(function (track) { track.stop(); });
                                upload();
                            };

                            recorder.start();
                            label.textContent = 'أوقف التسجيل';
                            say('يسجّل الآن…');
                        } catch (error) {
                            say('تعذّر الوصول إلى الميكروفون. اكتب إجابتك بدلًا من ذلك.');
                        }
                    }

                    async function upload() {
                        var seconds = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
                        var body = new FormData();

                        body.append('audio', new Blob(chunks, { type: 'audio/webm' }), 'answer.webm');
                        body.append('seconds', String(seconds));

                        say('يُنسَخ التسجيل…');

                        try {
                            var response = await fetch(root.dataset.endpoint, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
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
                                field.focus();
                            }

                            say('راجع النص قبل الإرسال — النسخ قد يخطئ في الأسماء والأرقام.');
                        } catch (error) {
                            say('تعذّر نسخ التسجيل. حاول مرة أخرى أو اكتب إجابتك.');
                        }
                    }

                    toggle.addEventListener('click', function () {
                        if (recorder && recorder.state === 'recording') {
                            recorder.stop();
                            recorder = null;
                            label.textContent = 'سجّل إجابتك صوتيًّا';
                            return;
                        }

                        start();
                    });
                });
            });
        </script>
    @endpush
@endonce

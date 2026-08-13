@php
    /**
     * مساعدة سؤال واحد: دليل ومقترحات تخصّ هذا النشاط، وقياس كفاية ما كُتب.
     *
     * يُدرَج على **كل** سؤال في كل قالب واستمارة بلا استثناء. لذلك لا يعرف من أي
     * سطح جاء: يقرأ ما يُمرَّر إليه ويتصرّف به.
     *
     * $projectSlug   معرّف المشروع في المسار
     * $surface       consultation | tool
     * $questionKey   مفتاح السؤال في سطحه
     * $fieldKey      مفتاح الحقيقة في الدماغ — به تُقاس الكفاية
     * $answerType    نوع الإجابة، يحدّد هل تُقاس الكفاية وكيف يُملأ المقترح
     * $inputName     اسم الحقل في النموذج، ليُملأ المقترح في خانته وحدها
     * $runUuid       (للأدوات) تشغيل الأداة الذي يحدّد أي سؤال يُقصد
     * $sessionUuid   (للاستشارة) جلسة الاستشارة
     */
    $assistId = 'assist-'.md5($surface.'|'.$questionKey.'|'.($inputName ?? ''));
    $measurable = in_array($answerType, ['text', 'textarea', 'long_text', 'repeater'], true);
@endphp

<div
    class="assist"
    id="{{ $assistId }}"
    data-assist
    data-endpoint="{{ route('app.assist.store', $projectSlug) }}"
    data-fitness-endpoint="{{ route('app.assist.fitness', $projectSlug) }}"
    data-surface="{{ $surface }}"
    data-question-key="{{ $questionKey }}"
    data-field-key="{{ $fieldKey }}"
    data-type="{{ $answerType }}"
    data-input="{{ $inputName ?? 'value' }}"
    data-run="{{ $runUuid ?? '' }}"
    data-session="{{ $sessionUuid ?? '' }}"
    data-measurable="{{ $measurable ? '1' : '0' }}"
>
    <div class="assist__bar">
        <button type="button" class="btn btn--ghost btn--sm" data-assist-run>
            اقترح لي إجابة تناسب نشاطي
        </button>
        <p class="muted assist__status" data-assist-status role="status" aria-live="polite"></p>
    </div>

    {{--
        الوسم البصري وكلمة «فرضية» شرط لا تحسين (§٤.١، §١٣): المقترح كلامُ نموذج
        لغوي عن نشاط لم يره، والخطر ليس أن يكون فرضية بل أن يُقرأ حقيقة.
    --}}
    <section class="assist__panel" data-assist-panel hidden aria-live="polite">
        <p class="assist__badge" data-assist-badge>فرضية</p>
        <p class="assist__guide" data-assist-guide></p>
        <p class="assist__recommendation" data-assist-recommendation hidden></p>
        <ul class="assist__suggestions" data-assist-suggestions></ul>
        <details class="assist__basis" data-assist-basis-box hidden>
            <summary>على أي معلومة بُني هذا المقترح</summary>
            <ul data-assist-basis></ul>
        </details>
    </section>

    @if ($measurable)
        {{--
            قياس الكفاية لحظيٌّ وحتميٌّ بلا تكلفة: صاحب النشاط يرى أن وصفه عامٌّ
            وهو ما زال أمام السؤال، لا في تقرير لا يستطيع تعديله.
        --}}
        <section class="assist__fitness" data-assist-fitness hidden aria-live="polite">
            <p class="assist__fitness-head">
                <strong data-fitness-score></strong>
                <span data-fitness-headline></span>
            </p>
            <ul class="assist__fitness-gaps" data-fitness-gaps></ul>
            <details class="assist__basis">
                <summary>كيف حُسبت هذه الدرجة</summary>
                <ul data-fitness-basis></ul>
            </details>
        </section>
    @endif
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var csrf = document.querySelector('meta[name="csrf-token"]');

                if (!csrf) {
                    return;
                }

                function post(url, payload) {
                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf.content,
                        },
                        body: JSON.stringify(payload),
                    }).then(function (response) {
                        if (!response.ok) throw new Error('assist unavailable');

                        return response.json();
                    });
                }

                document.querySelectorAll('[data-assist]').forEach(function (root) {
                    var status = root.querySelector('[data-assist-status]');
                    var panel = root.querySelector('[data-assist-panel]');
                    var trigger = root.querySelector('[data-assist-run]');
                    var name = root.dataset.input;

                    /*
                     * الخانة تُبحث في صندوق السؤال أولًا ثم في النموذج: معالج
                     * الأدوات يعرض أسئلة الخطوة كلها في نموذج واحد، والبحث من
                     * النموذج مباشرةً كان يعيد خانة سؤال آخر — وهو العطل نفسه
                     * الذي كان في المسجّل الصوتي.
                     */
                    function controls() {
                        var box = root.closest('.field, .consultation-question, .question-form') || document;
                        var found = box.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');

                        if (found.length > 0) return found;

                        var form = root.closest('form');

                        return form ? form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]') : [];
                    }

                    function say(message) {
                        status.textContent = message || '';
                    }

                    function fill(value) {
                        var nodes = controls();
                        var applied = false;

                        nodes.forEach(function (node) {
                            if (node.tagName === 'SELECT') {
                                node.value = value;
                                applied = node.value === String(value);
                            } else if (node.type === 'radio' || node.type === 'checkbox') {
                                if (String(node.value) === String(value)) {
                                    node.checked = true;
                                    applied = true;
                                }
                            } else if (node.tagName === 'TEXTAREA' || node.tagName === 'INPUT') {
                                /*
                                 * المقترح يُدخل ولا يُرسل: المراجعة شرط. نصٌّ من
                                 * نموذج لغوي يُحفظ بلا قراءة صاحبه يصير حقيقةً في
                                 * الدماغ مصدرها فرضية (§٤.١).
                                 */
                                node.value = value;
                                applied = true;
                            }

                            if (applied) {
                                node.dispatchEvent(new Event('input', { bubbles: true }));
                                node.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });

                        if (applied) {
                            say(@js(__('أُدخل المقترح في الخانة. عدّله بما يطابق نشاطك فعلًا قبل الحفظ.')));
                        } else {
                            say(@js(__('تعذّر إدخال المقترح تلقائيًّا. انسخه واكتبه بنفسك.')));
                        }
                    }

                    function renderList(node, items) {
                        node.replaceChildren();

                        (items || []).forEach(function (item) {
                            var line = document.createElement('li');
                            line.textContent = item;
                            node.appendChild(line);
                        });
                    }

                    function render(data) {
                        var guide = root.querySelector('[data-assist-guide]');
                        var list = root.querySelector('[data-assist-suggestions]');
                        var badge = root.querySelector('[data-assist-badge]');
                        var recommendation = root.querySelector('[data-assist-recommendation]');
                        var basisBox = root.querySelector('[data-assist-basis-box]');

                        guide.textContent = data.guide || '';
                        badge.textContent = data.assumption_label || @js(__('فرضية'));

                        if (data.recommendation_reason) {
                            recommendation.textContent = @js(__('الأقرب لوصفك: :reason')).replace(':reason', data.recommendation_reason);
                            recommendation.hidden = false;
                        } else {
                            recommendation.hidden = true;
                        }

                        list.replaceChildren();

                        (data.suggestions || []).forEach(function (suggestion) {
                            var item = document.createElement('li');
                            var button = document.createElement('button');
                            var why = document.createElement('span');

                            button.type = 'button';
                            button.className = 'btn btn--secondary btn--sm';
                            button.textContent = suggestion.label;

                            if (data.recommended_value && String(data.recommended_value) === String(suggestion.value)) {
                                item.classList.add('is-recommended');
                                button.textContent = @js(__(':label — الأقرب لوصفك')).replace(':label', suggestion.label);
                            }

                            button.addEventListener('click', function () { fill(suggestion.value); });
                            why.className = 'muted';
                            why.textContent = suggestion.why || '';
                            item.append(button, why);
                            list.appendChild(item);
                        });

                        renderList(root.querySelector('[data-assist-basis]'), data.basis);
                        basisBox.hidden = !(data.basis && data.basis.length);
                        panel.hidden = false;
                    }

                    trigger.addEventListener('click', function () {
                        trigger.disabled = true;
                        say(@js(__('نقرأ ما نعرفه عن نشاطك…')));

                        post(root.dataset.endpoint, {
                            surface: root.dataset.surface,
                            question_key: root.dataset.questionKey,
                            run_uuid: root.dataset.run || null,
                            session_uuid: root.dataset.session || null,
                        })
                            .then(function (payload) {
                                if (!payload.data || (!payload.data.guide && !(payload.data.suggestions || []).length)) {
                                    // الفراغ يُعلن ولا يُلفَّق (§٤.٣).
                                    say(@js(__('لا تتوفر مقترحات لهذا السؤال الآن. اكتب إجابتك وتُقاس كفايتها.')));

                                    return;
                                }

                                render(payload.data);
                                say('');
                            })
                            .catch(function () {
                                say(@js(__('تعذّر جلب المقترحات الآن. يمكنك الإجابة بصورة طبيعية.')));
                            })
                            .finally(function () { trigger.disabled = false; });
                    });

                    if (root.dataset.measurable !== '1') {
                        return;
                    }

                    var fitnessBox = root.querySelector('[data-assist-fitness]');
                    var timer = null;

                    function measure() {
                        var nodes = controls();
                        var value = [];

                        nodes.forEach(function (node) {
                            if (node.value) value.push(node.value);
                        });

                        if (value.length === 0) {
                            fitnessBox.hidden = true;

                            return;
                        }

                        post(root.dataset.fitnessEndpoint, {
                            field_key: root.dataset.fieldKey,
                            type: root.dataset.type,
                            value: value.length === 1 ? value[0] : value,
                        })
                            .then(function (payload) {
                                if (!payload.data) {
                                    fitnessBox.hidden = true;

                                    return;
                                }

                                // كل رقم يُعرض معه أساسه (§١٣).
                                root.querySelector('[data-fitness-score]').textContent =
                                    @js(__('كفاية إجابتك :score من 100')).replace(':score', payload.data.score);
                                root.querySelector('[data-fitness-headline]').textContent = payload.data.headline;
                                renderList(root.querySelector('[data-fitness-gaps]'), payload.data.gaps);
                                renderList(root.querySelector('[data-fitness-basis]'), payload.data.basis);
                                fitnessBox.hidden = false;
                            })
                            .catch(function () { fitnessBox.hidden = true; });
                    }

                    controls().forEach(function (node) {
                        node.addEventListener('input', function () {
                            clearTimeout(timer);
                            timer = setTimeout(measure, 600);
                        });
                    });

                    // قياس أوّليّ لإجابة محفوظة من قبل: من يعود إلى سؤال أجاب عنه
                    // يرى كفاية إجابته القديمة، لا صفحة صامتة.
                    measure();
                });
            });
        </script>
    @endpush
@endonce

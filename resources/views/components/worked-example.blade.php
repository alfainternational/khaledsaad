@props(['example' => null, 'source' => null, 'open' => false, 'inferred' => true, 'ltr' => false, 'print' => false])

@php
    // المثال التطبيقي: النص الجاهز للنسخ. مكوّن واحد لكل الأسطح — التقرير
    // ولوحة المهام ودليل التنفيذ — فلا يعرض سطحٌ ما يخفيه الآخر.
    $example = is_array($example) ? $example : null;
    $body = trim((string) ($example['body'] ?? ''));
    $notes = array_values(array_filter((array) ($example['notes'] ?? [])));
    $source = $source ?? ($example['source'] ?? null);
@endphp

@if ($body !== '')
    {{-- وضع الطباعة: لا أحد يطوي <details> في ورقة PDF، ولا أحد يضغط زر نسخ
         مطبوعًا. لذلك يُطبع المثال مفتوحًا بوسوم كتلية يفهمها mPDF، ويُحذف
         الزر بدل أن يظهر ميتًا. نفس المكوّن ونفس النص — الاختلاف في السطح. --}}
    @if ($print)
    <div class="worked-example">
        <p class="worked-example__summary">
    @else
    <details class="worked-example" @if ($open) open @endif>
        <summary class="worked-example__summary">
    @endif
            <span class="worked-example__kind">{{ $example['kind_label'] ?? 'مثال جاهز' }}</span>
            <span class="worked-example__title">{{ $example['title'] ?? 'مثال تطبيقي' }}</span>
            {{-- مثالُ التوصية اجتهاد منهجي مهما كان مصدره (§٤.١). أما القصاصة
                 التقنية فمعيار ثابت لا ادعاء عن النشاط، ووسمها «فرضية» يُفقد
                 الوسم معناه حين يظهر على ما ليس بفرضية. --}}
            @if ($inferred)
                <x-evidence-badge level="inferred" compact />
            @endif
    @if ($print)
        </p>
    @else
        </summary>
    @endif

        <div class="worked-example__body">
            <p class="worked-example__lead">
                @if ($print)
                    هذا نصّ جاهز. املأ ما بين الأقواس المربعة ببياناتك قبل استعماله.
                @else
                    هذا نصّ جاهز للنسخ. املأ ما بين الأقواس المربعة ببياناتك قبل استعماله.
                @endif
            </p>

            {{-- الكود يُقرأ من اليسار: عرض JSON-LD بـRTL يقلب الأقواس فيصير
                 النص غير صالح للصق، وهو كل الغرض منه. --}}
            <pre @class(['worked-example__text', 'worked-example__text--ltr' => $ltr])
                @if ($ltr) dir="ltr" @endif
                data-copy-source>{{ $body }}</pre>

            @unless ($print)
                <div class="worked-example__actions">
                    <button type="button" class="btn btn--ghost btn--sm" data-copy-example>انسخ النص</button>
                    <span class="worked-example__copied" data-copy-feedback hidden>نُسخ</span>
                </div>
            @endunless

            @if ($notes !== [])
                <ul class="worked-example__notes">
                    @foreach ($notes as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- مصدر المثال يُعلَن: قالب مأمون ليس كصياغة على حالة النشاط،
                 وإخفاء الفرق يجعل المستخدم يثق بما لا يستحق نفس الثقة. --}}
            @if ($source === 'deterministic')
                <p class="worked-example__source muted">
                    مثال مبدئي من قوالب المنصة، لم يُصَغ على حالة نشاطك بعد.
                    حوّل التوصية إلى مهمة واطلب تطويرها للحصول على مثال مكتوب ببياناتك.
                </p>
            @endif
        </div>
    @if ($print)
    </div>
    @else
    </details>
    @endif
@endif

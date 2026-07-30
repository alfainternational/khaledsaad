@php
    $type = $question['type'];
    $current = $current ?? null;
    $validation = $question['validation'] ?? [];
    $required = (bool) ($question['required'] ?? false);
@endphp

@if (in_array($type, ['select', 'radio', 'boolean', 'confirmation'], true))
    <div class="consultation-options question-control question-control--choices">
        @foreach ($question['options'] as $option)
            <label><input type="radio" name="value" value="{{ $option['value'] }}" @checked((string)$current === (string)$option['value']) @required($required)> <span>{{ $option['label'] }}</span></label>
        @endforeach
    </div>
@elseif ($type === 'multiselect')
    <div class="consultation-options consultation-options--multi question-control question-control--choices">
        @foreach ($question['options'] as $option)
            <label><input type="checkbox" name="value[]" value="{{ $option['value'] }}" @checked(in_array($option['value'], (array)$current, true))> <span>{{ $option['label'] }}</span></label>
        @endforeach
    </div>
@elseif ($type === 'number')
    <input class="question-control" type="number" name="value" step="any" value="{{ $current }}" @required($required)>
@elseif ($type === 'scale')
    <div class="field"><input class="question-control" type="range" name="value" min="{{ $validation['min'] ?? 1 }}" max="{{ $validation['max'] ?? 10 }}" step="{{ $validation['step'] ?? 1 }}" value="{{ $current ?? ($validation['min'] ?? 1) }}"><span class="field__help">من {{ $validation['min'] ?? 1 }} إلى {{ $validation['max'] ?? 10 }}</span></div>
@elseif ($type === 'range')
    <div class="field-row">
        <label class="field"><span class="field__label">من</span><input class="question-control" type="number" name="value[min]" step="any" value="{{ data_get($current, 'min') }}" required></label>
        <label class="field"><span class="field__label">إلى</span><input class="question-control" type="number" name="value[max]" step="any" value="{{ data_get($current, 'max') }}" required></label>
    </div>
@elseif ($type === 'ranking')
    @foreach ($question['options'] as $option)
        <label class="field"><span class="field__label">ترتيب {{ $option['label'] }}</span><input class="question-control" type="number" min="1" max="{{ count($question['options']) }}" name="value[{{ $option['value'] }}]" value="{{ data_get($current, $option['value']) }}" required></label>
    @endforeach
@elseif ($type === 'repeater')
    <p class="field__help">أدخل كل عنصر في خانة مستقلة.</p>
    @for ($index = 0; $index < min(10, $validation['max_items'] ?? 5); $index++)
        <input class="question-control" type="text" name="value[]" value="{{ data_get($current, $index) }}" placeholder="عنصر {{ $index + 1 }}" @required($index === 0 && $required)>
    @endfor
@elseif (in_array($type, ['url', 'email', 'date', 'text'], true))
    <input class="question-control" type="{{ $type === 'text' ? 'text' : $type }}" name="value" value="{{ $current }}" @required($required)>
@else
    <textarea class="question-control" name="value" rows="4" @required($required)>{{ $current }}</textarea>

    {{--
        الصوت للسؤال المفتوح وحده: هو ما يُتعب كتابته على الجوال. أما الاختيار
        والرقم والتاريخ فالنقر فيها أسرع من الكلام، وإظهار مسجّل عندها ضجيج.

        `$projectSlug` يصل من الشاشة المستدعية؛ غيابه يعني سياقًا بلا مشروع
        (معاينة أو تصدير) فلا يُعرض المسجّل بدل أن يشير إلى مسار ناقص.

        ومفتاح خدمة النسخ شرطٌ كذلك: بلا ضبطه من لوحة الآدمن يسجّل المستخدم
        دقيقةً كاملة ثم يقابل «لم يُضبط المفتاح» — عطلٌ يبدو له عطلَ تسجيله.
        الزرّ يختفي كما يختفي عند غياب دعم المتصفح، للسبب نفسه.
    --}}
    @if (isset($projectSlug) && filled(config('services.speech.key')))
        @include('app.partials.voice-recorder', ['projectSlug' => $projectSlug])
    @endif
@endif

{{--
    المساعدة على **كل** نوع سؤال بلا استثناء، لا على المفتوح وحده: في سؤال
    الاختيار ترشيحٌ لأفضل خيار متاح بسبب معلن، وفي المفتوح مقترح صياغة يعدّله
    صاحبه. وهي خارج شرط النوع أعلاه عمدًا — أي نوع إجابة يُضاف لاحقًا يحصل عليها
    بلا أن يتذكّرها من يضيفه.

    غياب `$projectSlug` أو `$sessionUuid` يعني سياقًا بلا مشروع (معاينة أو تصدير)،
    فلا تُعرض بدل أن تشير إلى مسار ناقص.
--}}
@if (isset($projectSlug, $sessionUuid) && filled($question['field_key'] ?? null))
    @include('app.partials.question-assist', [
        'projectSlug' => $projectSlug,
        'surface' => 'consultation',
        'questionKey' => $question['key'] ?? ($question['question_key'] ?? ''),
        'fieldKey' => $question['field_key'],
        'answerType' => $type,
        'inputName' => 'value',
        'sessionUuid' => $sessionUuid,
    ])
@endif

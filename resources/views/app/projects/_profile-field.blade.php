@php
    /**
     * حقل واحد من ملف المشروع، معروضًا من `ProfileQuestions` لا من نصّ في القالب.
     *
     * $field    بند من ProfileQuestions::fields()
     * $value    القيمة الحالية
     * $project  المشروع — لبناء مسار المساعدة
     *
     * المصدر واحد عمدًا: القالب يعرض، والقياس يحكم، والمساعدة تبني — ثلاثتها من
     * الإعلان نفسه. حين كانت التسميات مكتوبة هنا، كان تعديل سؤال يترك القياس
     * يحكم على سؤال آخر.
     */
    $key = $field['key'];
    $current = old($key, $value);
@endphp

<label class="field">
    <span class="field__label">{{ $field['label'] }}</span>
    @if (! empty($field['help']))
        <span class="field__help">{{ $field['help'] }}</span>
    @endif

    @if ($field['type'] === 'select')
        <select name="{{ $key }}">
            @if ($key === 'sector')
                <option value="" @selected($current === null || $current === '')>اختر القطاع…</option>
            @endif
            @foreach ($field['options'] ?? [] as $option)
                <option value="{{ $option['value'] }}" @selected((string) $current === (string) $option['value'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    @elseif ($field['type'] === 'textarea')
        <textarea name="{{ $key }}" rows="3" maxlength="{{ $key === 'description' ? 2000 : 1000 }}">{{ $current }}</textarea>
    @elseif ($field['type'] === 'number')
        <input type="number" name="{{ $key }}" value="{{ $current }}" min="0">
    @else
        <input type="text" name="{{ $key }}" value="{{ $current }}" maxlength="120"
            @if (! empty($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
            @if ($key === 'name') required @endif>
    @endif

    @if (! empty($field['why']))
        <span class="question-reason" aria-label="سبب طرح السؤال">{{ $field['why'] }}</span>
    @endif
</label>

{{--
    المساعدة خارج `<label>` لا داخله: عنصر داخل التسمية يجعل النقر على أي جزء
    منه ينقل التركيز إلى الحقل، فيصير الضغط على مقترح إدخالًا في الخانة الخطأ.
    وبحثها عن الخانة يسقط عندها إلى النموذج، وأسماء حقول الملف فريدة فيه.
--}}
@include('app.partials.question-assist', [
    'projectSlug' => $project->slug,
    'surface' => 'profile',
    'questionKey' => $key,
    'fieldKey' => $key,
    'answerType' => $field['type'],
    'inputName' => $key,
])

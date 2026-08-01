@php
    $value = old($field['key'], $field['value']);
    $isMulti = $field['type'] === 'multiselect';
    $selected = $isMulti ? (array) ($value ?? []) : [];

    // قيمة مصفوفة لحقل مفرد (قادمة من old أو ذاكرة قديمة): نأخذ أول قيمة صالحة.
    if (! $isMulti && is_array($value)) {
        $scalars = array_values(array_filter($value, is_scalar(...)));
        $value = $scalars[0] ?? null;
    }
@endphp

<div class="field">
    <span class="field__label" id="label-{{ $field['key'] }}">
        {{ $field['label'] }}
        @unless ($field['required'])
            <small class="muted">(اختياري)</small>
        @endunless
        @if (! empty($field['sector']))
            {{-- السؤال الذي جاء من القطاع يُعلن مصدره: وعدناه بأن اختياره
                 يفتح أسئلة خاصة به، ووعدٌ لا يُرى كأنه لم يكن. --}}
            <span class="badge badge--sector">خاص بـ{{ \App\Modules\Shared\Sectors\Sector::label($field['sector']) }}</span>
        @endif
    </span>

    @if ($field['help'])
        <span class="field__help">{{ $field['help'] }}</span>
    @endif

    @if (! empty($field['example']))
        <span class="field__example">{{ $field['example'] }}</span>
    @endif

    @switch ($field['type'])
        @case('textarea')
            <textarea class="question-control" name="{{ $field['key'] }}" rows="4"
                aria-labelledby="label-{{ $field['key'] }}"
                @required($field['required'])>{{ $value }}</textarea>

            {{--
                الصوت على السؤال المفتوح وحده: هو ما يثقل كتابته فيُترك فارغًا،
                وهو ما يخفض تغطية المحاور ١–٦. سؤال باختيارات لا يحتاج إملاءً.

                ومفتاح خدمة النسخ شرط: بلا ضبطه يسجّل المستخدم دقيقةً ثم يقابل
                «لم يُضبط المفتاح» — يقرؤه عطلًا في تسجيله لا في إعداد المنصة.
            --}}
            @if (isset($projectSlug) && filled(config('services.speech.key')))
                @include('app.partials.voice-recorder', ['projectSlug' => $projectSlug])
            @endif
            @break

        @case('select')
            <select class="question-control" name="{{ $field['key'] }}" aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
                <option value="">اختر…</option>
                @foreach ($field['options'] as $option)
                    <option value="{{ $option['value'] }}" @selected((string) $value === (string) $option['value'])>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
            @break

        @case('multiselect')
            <fieldset class="checkbox-grid question-control question-control--choices">
                <legend class="sr-only">{{ $field['label'] }}</legend>
                @foreach ($field['options'] as $option)
                    <label class="checkbox">
                        <input type="checkbox" name="{{ $field['key'] }}[]" value="{{ $option['value'] }}"
                            @checked(in_array($option['value'], $selected, false))>
                        <span>{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </fieldset>
            @break

        @case('number')
            <input class="question-control" type="number" name="{{ $field['key'] }}" value="{{ $value }}"
                aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
            @break

        @case('url')
            <input class="question-control" type="url" name="{{ $field['key'] }}" value="{{ $value }}"
                aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
            @break

        @default
            <input class="question-control" type="text" name="{{ $field['key'] }}" value="{{ $value }}"
                aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
    @endswitch

    @if (! empty($field['why']))
        <p class="question-reason" aria-label="سبب طرح السؤال">{{ $field['why'] }}</p>
    @endif

    @if (! empty($field['benchmark']))
        <p class="field__benchmark">
            <b>للمقارنة:</b> {{ $field['benchmark']['text'] }}
            <small>{{ $field['benchmark']['source'] }}</small>
        </p>
    @endif

    @if (! empty($field['competitor_view']))
        {{-- رؤية كاملة للمنافسين: أين ترى إعلاناتهم على كل منصة. --}}
        <details class="field__competitors">
            <summary>اطّلع على إعلانات منافسيك في هذه المنصات</summary>
            <ul>
                @foreach ($field['competitor_view'] as $view)
                    <li @class(['is-limited' => $view['limited']])>
                        <div>
                            <strong>{{ $view['platforms'] }}</strong>
                            <span>{{ $view['what'] }}</span>
                        </div>
                        @if ($view['url'])
                            <a href="{{ $view['url'] }}" target="_blank" rel="noopener noreferrer">
                                {{ $view['source'] }} <b aria-hidden="true">↗</b>
                            </a>
                        @else
                            <em>{{ $view['source'] }}</em>
                        @endif
                    </li>
                @endforeach
            </ul>
            <small>مكتبات إعلانات رسمية وعامة من المنصات نفسها — تُظهر الإعلانات النشطة لأي معلِن.</small>
        </details>
    @endif

    {{--
        المساعدة على كل حقل بلا استثناء، أيًّا كان نوعه وأيًّا كانت الأداة: هي
        موضوعة على القالب المشترك لا على أداة بعينها، فكل أداة قائمة وكل أداة
        تُضاف لاحقًا تحصل عليها بلا أن يتذكّرها من يبنيها.
    --}}
    @if (isset($projectSlug, $runUuid))
        @include('app.partials.question-assist', [
            'projectSlug' => $projectSlug,
            'surface' => 'tool',
            'questionKey' => $field['key'],
            'fieldKey' => $field['key'],
            'answerType' => $field['type'],
            'inputName' => $field['key'],
            'runUuid' => $runUuid,
        ])
    @endif

    @error($field['key'])
        <strong class="field__error">{{ $message }}</strong>
    @enderror
</div>

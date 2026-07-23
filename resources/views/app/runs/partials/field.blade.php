@php
    $value = old($field['key'], $field['value']);
    $isMulti = $field['type'] === 'multiselect';
    $selected = $isMulti ? (array) ($value ?? []) : [];
@endphp

<div class="field">
    <span class="field__label" id="label-{{ $field['key'] }}">
        {{ $field['label'] }}
        @unless ($field['required'])
            <small class="muted">(اختياري)</small>
        @endunless
    </span>

    @switch ($field['type'])
        @case('textarea')
            <textarea name="{{ $field['key'] }}" rows="4"
                aria-labelledby="label-{{ $field['key'] }}"
                @required($field['required'])>{{ $value }}</textarea>
            @break

        @case('select')
            <select name="{{ $field['key'] }}" aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
                <option value="">اختر…</option>
                @foreach ($field['options'] as $option)
                    <option value="{{ $option['value'] }}" @selected((string) $value === (string) $option['value'])>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
            @break

        @case('multiselect')
            <fieldset class="checkbox-grid">
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
            <input type="number" name="{{ $field['key'] }}" value="{{ $value }}"
                aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
            @break

        @case('url')
            <input type="url" name="{{ $field['key'] }}" value="{{ $value }}"
                aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
            @break

        @default
            <input type="text" name="{{ $field['key'] }}" value="{{ $value }}"
                aria-labelledby="label-{{ $field['key'] }}" @required($field['required'])>
    @endswitch

    @if ($field['help'])
        <span class="field__help">{{ $field['help'] }}</span>
    @endif

    @if (! empty($field['example']))
        <span class="field__example">{{ $field['example'] }}</span>
    @endif

    @if (! empty($field['why']))
        {{-- لماذا نسأل: حق المستخدم أن يعرف قبل أن يجيب. --}}
        <details class="field__why">
            <summary>لماذا نسأل عن هذه؟</summary>
            <p>{{ $field['why'] }}</p>
        </details>
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
            <summary>شوف إعلانات منافسيك على هذه المنصات</summary>
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

    @error($field['key'])
        <strong class="field__error">{{ $message }}</strong>
    @enderror
</div>

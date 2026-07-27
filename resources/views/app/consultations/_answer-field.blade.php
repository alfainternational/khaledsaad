@php
    $type = $question['type'];
    $current = $current ?? null;
    $validation = $question['validation'] ?? [];
@endphp

@if (in_array($type, ['select', 'radio', 'boolean', 'confirmation'], true))
    <div class="consultation-options">
        @foreach ($question['options'] as $option)
            <label><input type="radio" name="value" value="{{ $option['value'] }}" @checked((string)$current === (string)$option['value']) @required($question['required'])> <span>{{ $option['label'] }}</span></label>
        @endforeach
    </div>
@elseif ($type === 'multiselect')
    <div class="consultation-options consultation-options--multi">
        @foreach ($question['options'] as $option)
            <label><input type="checkbox" name="value[]" value="{{ $option['value'] }}" @checked(in_array($option['value'], (array)$current, true))> <span>{{ $option['label'] }}</span></label>
        @endforeach
    </div>
@elseif ($type === 'number')
    <input type="number" name="value" step="any" value="{{ $current }}" @required($question['required'])>
@elseif ($type === 'scale')
    <div class="field"><input type="range" name="value" min="{{ $validation['min'] ?? 1 }}" max="{{ $validation['max'] ?? 10 }}" step="{{ $validation['step'] ?? 1 }}" value="{{ $current ?? ($validation['min'] ?? 1) }}"><span class="field__help">من {{ $validation['min'] ?? 1 }} إلى {{ $validation['max'] ?? 10 }}</span></div>
@elseif ($type === 'range')
    <div class="field-row">
        <label class="field"><span class="field__label">من</span><input type="number" name="value[min]" step="any" value="{{ data_get($current, 'min') }}" required></label>
        <label class="field"><span class="field__label">إلى</span><input type="number" name="value[max]" step="any" value="{{ data_get($current, 'max') }}" required></label>
    </div>
@elseif ($type === 'ranking')
    @foreach ($question['options'] as $option)
        <label class="field"><span class="field__label">ترتيب {{ $option['label'] }}</span><input type="number" min="1" max="{{ count($question['options']) }}" name="value[{{ $option['value'] }}]" value="{{ data_get($current, $option['value']) }}" required></label>
    @endforeach
@elseif ($type === 'repeater')
    <p class="field__help">أدخل كل عنصر في خانة مستقلة.</p>
    @for ($index = 0; $index < min(10, $validation['max_items'] ?? 5); $index++)
        <input type="text" name="value[]" value="{{ data_get($current, $index) }}" placeholder="عنصر {{ $index + 1 }}" @required($index === 0 && $question['required'])>
    @endfor
@elseif (in_array($type, ['url', 'email', 'date', 'text'], true))
    <input type="{{ $type === 'text' ? 'text' : $type }}" name="value" value="{{ $current }}" @required($question['required'])>
@else
    <textarea name="value" rows="4" @required($question['required'])>{{ $current }}</textarea>
@endif

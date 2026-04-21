@props([
    'modeKey',
    'mode',
    'modeExperience' => [],
    'initialMode',
    'latestRun' => null,
    'isDiagnosis' => false,
    'chunkSize' => 3,
])

@php
    $fields = $mode['fields'] ?? [];
    $chunks = $modeKey === 'guided' ? array_chunk($fields, $chunkSize) : [$fields];
    $totalFields = count($fields);
@endphp

<section
    class="tool-mode-panel"
    data-tool-mode-panel="{{ $modeKey }}"
    @if ($initialMode !== $modeKey) hidden @endif
>
    @if (! empty($modeExperience))
        <details class="tool-mode-focus-details mb-4">
            <summary class="tool-mode-focus-summary">
                <span class="tool-mode-focus-summary-text">مساعدة عن التعبئة</span>
                <span class="tool-mode-focus-summary-meta">{{ $mode['label'] }}</span>
            </summary>
            <div class="tool-mode-focus-card tool-mode-focus-card-scroll">
                <div class="tool-mode-focus-head">
                    <strong>{{ $modeExperience['focus_title'] ?? 'كيف تملأ الحقول؟' }}</strong>
                </div>
                @php
                    $focusNote = (string) ($modeExperience['focus_note'] ?? ($mode['description'] ?? ''));
                @endphp
                @if ($focusNote !== '')
                    <p>{{ $focusNote }}</p>
                @endif

                @if (! empty($modeExperience['focus_points']))
                    <ul class="tool-mode-focus-points">
                        @foreach (collect($modeExperience['focus_points'])->take(5) as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>
    @endif

    {{-- Field Progress Dots --}}
    @if ($totalFields > 1)
        <div class="tool-field-dots" data-field-dots>
            @foreach ($fields as $fi => $f)
                <span
                    class="tool-field-dot {{ !empty(old('inputs.'.$f['key'], data_get($latestRun?->inputs_json ?? [], $f['key']))) ? 'is-filled' : '' }}"
                    data-field-dot="{{ $f['key'] }}"
                    title="{{ $f['label'] }}"
                ></span>
            @endforeach
        </div>
    @endif

    <div
        class="tool-ui-stepper"
        data-tool-stepper
        data-tool-stepper-mode="{{ $modeKey }}"
        @if ($modeKey !== 'guided') data-tool-stepper-static="true" @endif
    >
        @foreach ($chunks as $chunkIndex => $chunk)
            <div
                class="tool-ui-step-panel"
                data-tool-step-panel="{{ $chunkIndex }}"
                @if ($chunkIndex !== 0) hidden @endif
            >
                <div class="app-form-grid">
                    @foreach ($chunk as $field)
                        @php
                            $fieldKey = $field['key'];
                            $fieldExperience = $modeExperience['fields'][$fieldKey] ?? [];
                            $htmlName = "inputs[{$fieldKey}]";
                            $fieldValue = old("inputs.{$fieldKey}", data_get($latestRun?->inputs_json ?? [], $fieldKey));
                            $previewKey = $fieldKey;
                            $placeholder = $fieldExperience['smart_placeholder'] ?? ($field['placeholder'] ?? '');
                            $priority = $fieldExperience['priority'] ?? 'important';
                            $suggestedValue = $fieldExperience['suggested_value'] ?? null;
                            $genericTerms = $fieldExperience['quality']['generic_terms'] ?? [];
                            $minLength = $fieldExperience['quality']['min_length'] ?? 0;
                            $example = '';
                            if (str_starts_with($placeholder, 'مثال: ')) {
                                $example = mb_substr($placeholder, mb_strlen('مثال: '));
                            } elseif (str_starts_with($placeholder, 'مثال:')) {
                                $example = mb_substr($placeholder, mb_strlen('مثال:'));
                            }
                        @endphp
                        <div
                            class="tool-field-wrap {{ !empty($fieldValue) ? 'is-filled' : '' }}"
                            data-field-wrap="{{ $previewKey }}"
                            data-field-priority="{{ $priority }}"
                            data-field-label="{{ $field['label'] }}"
                            data-field-context="{{ $fieldExperience['context_hint'] ?? '' }}"
                            data-field-empty-prompt="{{ $fieldExperience['empty_prompt'] ?? '' }}"
                            data-field-weak-prompt="{{ $fieldExperience['weak_prompt'] ?? '' }}"
                            data-field-min-length="{{ $minLength }}"
                            data-field-generic-terms='@json($genericTerms)'
                        >
                            <label class="app-field">
                                <span class="tool-field-label-row">
                                    <span>{{ $field['label'] }}</span>
                                    <span class="tool-field-priority-badge is-{{ $priority }}">{{ $fieldExperience['priority_label'] ?? 'مهم جداً' }}</span>
                                    <span class="tool-field-quality-badge" data-field-quality-badge="{{ $previewKey }}" hidden></span>
                                    <span class="tool-field-check" aria-hidden="true">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                </span>
                                @if (($field['type'] ?? 'text') === 'textarea')
                                    <textarea
                                        class="app-input"
                                        name="{{ $htmlName }}"
                                        rows="4"
                                        placeholder="{{ $placeholder }}"
                                        @if ($isDiagnosis)
                                            data-diagnosis-input="{{ $previewKey }}"
                                        @else
                                            data-tool-preview-input="{{ $previewKey }}"
                                            data-tool-preview-label="{{ $field['label'] }}"
                                        @endif
                                        data-field-key="{{ $previewKey }}"
                                        data-field-label="{{ $field['label'] }}"
                                    >{{ $fieldValue }}</textarea>
                                @elseif (($field['type'] ?? 'text') === 'select')
                                    <select
                                        class="app-input"
                                        name="{{ $htmlName }}"
                                        @if ($isDiagnosis)
                                            data-diagnosis-input="{{ $previewKey }}"
                                        @else
                                            data-tool-preview-input="{{ $previewKey }}"
                                            data-tool-preview-label="{{ $field['label'] }}"
                                        @endif
                                        data-field-key="{{ $previewKey }}"
                                        data-field-label="{{ $field['label'] }}"
                                    >
                                        <option value="">اختر</option>
                                        @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}" @selected((string) $fieldValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        class="app-input"
                                        type="text"
                                        name="{{ $htmlName }}"
                                        value="{{ $fieldValue }}"
                                        placeholder="{{ $placeholder }}"
                                        @if ($isDiagnosis)
                                            data-diagnosis-input="{{ $previewKey }}"
                                        @else
                                            data-tool-preview-input="{{ $previewKey }}"
                                            data-tool-preview-label="{{ $field['label'] }}"
                                        @endif
                                        data-field-key="{{ $previewKey }}"
                                        data-field-label="{{ $field['label'] }}"
                                    >
                                @endif
                            </label>
                            @if (! empty($fieldExperience['context_hint']) || ! empty($field['answer_tip']))
                                <details class="tool-field-hint-details">
                                    <summary class="tool-field-hint-summary">شرح السؤال (اختياري)</summary>
                                    <div class="tool-field-hint-details-body">
                                        @if (! empty($fieldExperience['context_hint']))
                                            <div class="tool-field-context">{{ $fieldExperience['context_hint'] }}</div>
                                        @endif
                                        @if (! empty($field['answer_tip']))
                                            <div class="tool-field-guidance">{{ $field['answer_tip'] }}</div>
                                        @endif
                                    </div>
                                </details>
                            @endif
                            <div class="tool-field-live-status" data-field-live-status="{{ $previewKey }}"></div>
                            @if ($suggestedValue)
                                <button
                                    type="button"
                                    class="tool-field-suggestion"
                                    data-field-suggestion-value="{{ $suggestedValue }}"
                                    data-field-suggestion-label="{{ $fieldExperience['suggestion_label'] ?? 'استخدم هذه الصياغة' }}"
                                    data-target-key="{{ $previewKey }}"
                                >
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    {{ $fieldExperience['suggestion_label'] ?? 'استخدم هذه الصياغة' }}
                                </button>
                            @endif
                            @if ($example && ($field['type'] ?? 'text') !== 'select')
                                <button type="button" class="tool-field-example" data-field-example="{{ $example }}" data-target-key="{{ $previewKey }}">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    استخدم هذا المثال
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if (count($chunks) > 1)
            <div class="tool-ui-stepper-nav">
                <span class="tool-ui-stepper-indicator" data-tool-step-indicator>1 / {{ count($chunks) }}</span>
                <div class="app-inline-actions">
                    <button type="button" class="btn btn-ghost btn-sm" data-tool-step-prev disabled>السابق</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-tool-step-next>التالي</button>
                </div>
            </div>
        @endif
    </div>
</section>

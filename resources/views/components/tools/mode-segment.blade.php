@props([
    'blueprint',
    'modeAvailability' => [],
    'initialMode' => 'guided',
    'isDiagnosis' => false,
])

@php
    $modeCount = count($blueprint['modes'] ?? []);
    // Default to the recommended (guided) mode and tuck the switcher away so the
    // user is not forced to choose a mode before answering. Only collapse for
    // regular tools with more than one mode — diagnosis keeps its own layout.
    $collapseSwitcher = ! $isDiagnosis && $modeCount > 1;
@endphp

@if ($collapseSwitcher)
    <details class="tool-context-rail tool-mode-rail" data-tool-mode-root>
        <summary class="onb-advanced-summary">
            <span>مستوى الأسئلة — الافتراضي مبسّط</span>
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <input type="hidden" name="mode" value="{{ $initialMode }}" data-tool-mode-switcher>
        <div class="tool-mode-switcher tool-mode-rail-body">
            @foreach ($blueprint['modes'] as $modeKey => $mode)
                @php
                    $modeState = $modeAvailability[$modeKey] ?? ['available' => true, 'reason' => null];
                    $modeTitle = trim(
                        ($modeState['reason'] ?? '')
                        ?: (($mode['description'] ?? '') !== '' ? (string) $mode['description'] : '')
                    );
                @endphp
                <button
                    type="button"
                    class="tool-mode-button {{ $initialMode === $modeKey ? 'is-active' : '' }}"
                    data-tool-mode-button="{{ $modeKey }}"
                    aria-label="وضع الإدخال: {{ $mode['label'] }}"
                    @disabled(! $modeState['available'])
                    @if ($modeTitle !== '') title="{{ $modeTitle }}" @endif
                >
                    <span class="tool-mode-button-label">{{ $mode['label'] }}@if (! $modeState['available']) · مقفل @endif</span>
                </button>
            @endforeach
        </div>
    </details>
@else
    <div class="{{ $isDiagnosis ? 'diagnosis-mode-group' : 'tool-mode-switcher' }}" data-tool-mode-root>
        <input type="hidden" name="mode" value="{{ $initialMode }}" data-tool-mode-switcher>
        @foreach ($blueprint['modes'] as $modeKey => $mode)
            @php
                $modeState = $modeAvailability[$modeKey] ?? ['available' => true, 'reason' => null];
                $modeTitle = trim(
                    ($modeState['reason'] ?? '')
                    ?: (($mode['description'] ?? '') !== '' ? (string) $mode['description'] : '')
                );
            @endphp
            <button
                type="button"
                class="{{ $isDiagnosis ? 'diagnosis-mode-button' : 'tool-mode-button' }} {{ $initialMode === $modeKey ? 'is-active' : '' }}"
                data-tool-mode-button="{{ $modeKey }}"
                aria-label="وضع الإدخال: {{ $mode['label'] }}"
                @disabled(! $modeState['available'])
                @if ($modeTitle !== '') title="{{ $modeTitle }}" @endif
            >
                <span class="tool-mode-button-label">{{ $mode['label'] }}@if (! $modeState['available']) · مقفل @endif</span>
            </button>
        @endforeach
    </div>
@endif

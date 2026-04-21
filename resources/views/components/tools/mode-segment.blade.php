@props([
    'blueprint',
    'modeAvailability' => [],
    'initialMode' => 'guided',
    'isDiagnosis' => false,
])

<div class="{{ $isDiagnosis ? 'diagnosis-mode-group' : 'tool-mode-switcher' }}" data-tool-mode-root>
    <input type="hidden" name="mode" value="{{ $initialMode }}" data-tool-mode-switcher>
    @foreach ($blueprint['modes'] as $modeKey => $mode)
        @php
            $modeState = $modeAvailability[$modeKey] ?? ['available' => true, 'reason' => null];
        @endphp
        @php
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

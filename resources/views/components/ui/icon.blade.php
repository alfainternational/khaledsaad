@props(['name' => 'dot'])

<span {{ $attributes->class('ui-icon') }} aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        @switch($name)
            @case('chart')
                <path d="M4 19V9M10 19V5M16 19v-7M22 19H2" />
                @break
            @case('check')
                <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
                @break
            @case('empty')
                <path d="M4 6h16v12H4zM8 10h8M8 14h5" />
                @break
            @case('warning')
                <path d="m12 3 10 18H2L12 3Z" /><path d="M12 9v5M12 17h.01" />
                @break
            @default
                <circle cx="12" cy="12" r="4" />
        @endswitch
    </svg>
</span>

@php($name = $name ?? 'folder')
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('article')
        @case('file-text')
            <path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 12h6M9 16h6"/>
            @break
        @case('lesson')
        @case('book-open')
            <path d="M3 5.5A4.5 4.5 0 0 1 7.5 4H11v16H7.5A4.5 4.5 0 0 0 3 21z"/><path d="M21 5.5A4.5 4.5 0 0 0 16.5 4H13v16h3.5A4.5 4.5 0 0 1 21 21z"/>
            @break
        @case('lecture')
        @case('presentation')
            <path d="M4 3h16v13H4zM8 21l4-5 4 5M2 3h20"/>
            @break
        @case('course')
        @case('graduation-cap')
            <path d="m2 10 10-5 10 5-10 5z"/><path d="M6 12.5V17c3 2.5 9 2.5 12 0v-4.5M22 10v6"/>
            @break
        @case('storefront')
        @case('shopping-bag')
            <path d="M4 8h16l-1 12H5z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>
            @break
        @case('building')
        @case('real-estate')
            <path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M10 21v-5h4v5M9 10h.01M15 10h.01M9 13h.01M15 13h.01"/>
            @break
        @case('megaphone')
            <path d="m3 11 15-6v14L3 13zM7 14l2 6h3l-1.5-7"/>
            @break
        @case('chart')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
            @break
        @case('lightbulb')
            <path d="M9 18h6M10 22h4M8.5 15.5A7 7 0 1 1 15.5 15.5L14 18h-4z"/>
            @break
        @case('target')
            <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
            @break
        @default
            <path d="M3 6h7l2 2h9v12H3z"/>
    @endswitch
</svg>

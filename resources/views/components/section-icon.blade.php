@props(['name'])

<span class="erow__icon" data-row-icon aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
        @switch($name)
            @case('spend')
                <path d="M4 7h16v11H4zM7 7V5h10v2M8 12h8M8 15h5"/><path d="m15 3 2 2 2-2"/>
                @break
            @case('publish')
                <path d="m4 13 11-6v10L4 13Zm0 0v4l4 1v-3M15 10c2 0 4-1 5-3M15 14c2 0 4 1 5 3"/>
                @break
            @case('analytics')
                <path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/><circle cx="16" cy="7" r="2"/>
                @break
            @case('compass')
                <circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/>
                @break
            @case('score')
                <path d="M4 16a8 8 0 1 1 16 0M12 12l4-4M7 18h10"/><circle cx="12" cy="12" r="1"/>
                @break
            @case('ai')
                <rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 10h6M9 14h3M12 2v4M12 18v4M2 12h4M18 12h4"/>
                @break
            @case('tasks')
                <path d="M9 6h11M9 12h11M9 18h11M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>
                @break
            @case('calendar')
                <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 14h3M14 14h2M8 18h3"/>
                @break
            @case('listen')
                <path d="M4 13v-1a8 8 0 0 1 16 0v1M4 13h3v6H5a2 2 0 0 1-1-2v-4ZM20 13h-3v6h2a2 2 0 0 0 1-2v-4Z"/>
                @break
            @case('search')
                <circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5M8 10h5M10.5 7.5v5"/>
                @break
            @case('sort')
                <path d="M8 4v16M5 7l3-3 3 3M16 20V4M13 17l3 3 3-3"/>
                @break
            @case('act')
                <path d="M12 3v5M12 16v5M3 12h5M16 12h5"/><path d="m14 10 6-6M10 14l-6 6"/><circle cx="12" cy="12" r="3"/>
                @break
            @case('target')
                <circle cx="11" cy="13" r="8"/><circle cx="11" cy="13" r="4"/><path d="m14 10 6-6M16 4h4v4"/>
                @break
            @case('evidence')
                <path d="M6 3h9l3 3v15H6zM15 3v4h4M9 12l2 2 4-4M9 18h6"/>
                @break
            @case('priority')
                <path d="M4 5h16M4 12h12M4 19h8"/><circle cx="20" cy="12" r="1.5"/><circle cx="16" cy="19" r="1.5"/>
                @break
            @case('timeline')
                <path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="11" cy="18" r="2"/>
                @break
            @case('briefcase')
                <rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 12h18M10 12v2h4v-2"/>
                @break
            @case('education')
                <path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 12v5c4 3 8 3 12 0v-5M22 9v6"/>
                @break
            @case('award')
                <circle cx="12" cy="9" r="6"/><path d="m8 14-2 8 6-3 6 3-2-8M9.5 9l1.5 1.5L14.5 7"/>
                @break
            @case('skills')
                <path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8M18.4 5.6 5.6 18.4"/><circle cx="12" cy="12" r="3"/>
                @break
            @case('reason')
                <path d="M4 12h5l2-3 3 6 2-3h4"/><circle cx="12" cy="12" r="9"/>
                @break
            @case('decision')
                <path d="M5 4h14v16H5zM8 8h8M8 12h5M8 16h3"/><path d="m15 15 2 2 4-4"/>
                @break
            @case('followup')
                <path d="M20 11a8 8 0 1 1-2.3-5.7L20 8M20 3v5h-5"/><path d="M12 7v5l3 2"/>
                @break
            @case('lock')
                <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>
                @break
            @case('article')
                <path d="M6 3h9l3 3v15H6zM15 3v4h4M9 11h6M9 15h6M9 18h4"/>
                @break
            @case('lesson')
                <path d="M3 5h7a3 3 0 0 1 3 3v12a3 3 0 0 0-3-3H3zM21 5h-7a3 3 0 0 0-3 3v12a3 3 0 0 1 3-3h7z"/>
                @break
            @case('lecture')
                <path d="M4 4h16v12H4zM8 21l4-5 4 5M8 9h3v4H8zM14 7h3v6h-3z"/>
                @break
            @case('course')
                <path d="m12 3 9 5-9 5-9-5 9-5ZM5 11v6l7 4 7-4v-6"/>
                @break
            @case('question')
                <path d="M4 5h16v12H9l-5 4V5Z"/><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-1 .7-1.5 1-1.5 2M12 15h.01"/>
                @break
            @case('enter')
                <path d="M13 5h6v14h-6M10 8l-4 4 4 4M6 12h9"/>
                @break
            @case('answers')
                <path d="M5 4h14v16H5zM8 8h8M8 12h5M8 16h4"/><path d="m15 15 1 1 2-2"/>
                @break
            @case('account')
                <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
                @break
            @case('review')
                <path d="M7 4h10v17H7zM9 4V2h6v2M10 9h4M10 13h4M10 17h2"/><path d="m15 16 1 1 3-3"/>
                @break
            @default
                <circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>
        @endswitch
    </svg>
</span>

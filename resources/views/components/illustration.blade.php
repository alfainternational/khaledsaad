@props(['name' => 'clarity'])

@php
    // رسوم بأسلوب العلامة: أشكال زاويّة، تدرّج سماوي→أزرق، ولمسة برتقالية.
    // معرّفات التدرّج فريدة لكل نسخة حتى لا تتعارض عند تكرار الرسمة في الصفحة.
    $uid = 'ill-'.$name.'-'.substr(md5($name.uniqid('', true)), 0, 6);
@endphp

<svg
    class="illustration illustration--{{ $name }}"
    viewBox="0 0 220 180"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    focusable="false"
    {{ $attributes }}
>
    <defs>
        <linearGradient id="{{ $uid }}-cool" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#09d7e5"/>
            <stop offset="55%" stop-color="#2575ff"/>
            <stop offset="100%" stop-color="#174de8"/>
        </linearGradient>
        <linearGradient id="{{ $uid }}-warm" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0%" stop-color="#ff4b12"/>
            <stop offset="100%" stop-color="#ff9b27"/>
        </linearGradient>
        <linearGradient id="{{ $uid }}-deep" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#0c42ca"/>
            <stop offset="100%" stop-color="#071f5b"/>
        </linearGradient>
    </defs>

    @switch($name)
        {{-- إنفاق بلا وضوح: أموال تتسرب من قمع مثقوب --}}
        @case('spend')
            <rect x="26" y="24" width="168" height="34" rx="17" fill="url(#{{ $uid }}-deep)" opacity="0.12"/>
            <path d="M40 30h140l-46 54v50l-48 22V84z" fill="url(#{{ $uid }}-cool)"/>
            <path d="M110 134v18" stroke="url(#{{ $uid }}-warm)" stroke-width="8" stroke-linecap="round"/>
            <circle cx="74" cy="150" r="9" fill="url(#{{ $uid }}-warm)" opacity="0.85"/>
            <circle cx="152" cy="158" r="6" fill="url(#{{ $uid }}-warm)" opacity="0.55"/>
            <circle cx="120" cy="166" r="4" fill="url(#{{ $uid }}-warm)" opacity="0.4"/>
            @break

        {{-- محتوى بلا تحويل: منشورات كثيرة وخط نتيجة مسطّح --}}
        @case('content')
            <rect x="24" y="22" width="70" height="54" rx="12" fill="url(#{{ $uid }}-cool)" opacity="0.9"/>
            <rect x="104" y="22" width="70" height="54" rx="12" fill="url(#{{ $uid }}-deep)" opacity="0.22"/>
            <rect x="64" y="88" width="70" height="54" rx="12" fill="url(#{{ $uid }}-deep)" opacity="0.16"/>
            <path d="M24 156h172" stroke="#dfe8f5" stroke-width="6" stroke-linecap="round"/>
            <path d="M32 150h150" stroke="url(#{{ $uid }}-warm)" stroke-width="5" stroke-linecap="round" stroke-dasharray="2 16"/>
            @break

        {{-- أدوات بلا نظام: قطع متفرقة لا تتصل --}}
        @case('scatter')
            <rect x="26" y="30" width="58" height="58" rx="14" fill="url(#{{ $uid }}-cool)" opacity="0.85"/>
            <rect x="112" y="20" width="46" height="46" rx="12" fill="url(#{{ $uid }}-deep)" opacity="0.2"/>
            <rect x="150" y="86" width="44" height="44" rx="12" fill="url(#{{ $uid }}-warm)" opacity="0.75"/>
            <rect x="52" y="112" width="52" height="46" rx="12" fill="url(#{{ $uid }}-deep)" opacity="0.14"/>
            <path d="M84 60h28M158 66l-6 20M104 112l40-14" stroke="#b8c5d7" stroke-width="4" stroke-linecap="round" stroke-dasharray="6 10"/>
            @break

        {{-- قرارات بالانطباع: كفّتان غير متوازنتين --}}
        @case('guess')
            <path d="M110 26v112" stroke="url(#{{ $uid }}-deep)" stroke-width="8" stroke-linecap="round"/>
            <path d="M44 58h132" stroke="url(#{{ $uid }}-deep)" stroke-width="8" stroke-linecap="round" transform="rotate(-9 110 58)"/>
            <path d="M36 74l24 34H12z" fill="url(#{{ $uid }}-warm)"/>
            <path d="M182 46l22 32h-44z" fill="url(#{{ $uid }}-cool)" opacity="0.55"/>
            <rect x="74" y="140" width="72" height="14" rx="7" fill="url(#{{ $uid }}-deep)" opacity="0.18"/>
            @break

        {{-- الخبرة: مسار مهني صاعد بمحطات --}}
        @case('experience')
            <path d="M22 150l44-30 40 16 46-46 46-24" stroke="url(#{{ $uid }}-cool)" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="66" cy="120" r="11" fill="#fff" stroke="url(#{{ $uid }}-cool)" stroke-width="7"/>
            <circle cx="152" cy="90" r="11" fill="#fff" stroke="url(#{{ $uid }}-cool)" stroke-width="7"/>
            <path d="M186 22l24 26-24 26z" fill="url(#{{ $uid }}-warm)"/>
            <rect x="20" y="158" width="180" height="10" rx="5" fill="url(#{{ $uid }}-deep)" opacity="0.12"/>
            @break

        {{-- البداية: باب يفتح على مسار واضح --}}
        @case('start')
            <path d="M52 30h74a20 20 0 0 1 20 20v100H72a20 20 0 0 1-20-20z" fill="url(#{{ $uid }}-cool)"/>
            <circle cx="128" cy="96" r="8" fill="#fff" opacity="0.9"/>
            <path d="M150 96h48" stroke="url(#{{ $uid }}-warm)" stroke-width="9" stroke-linecap="round"/>
            <path d="M178 76l24 20-24 20z" fill="url(#{{ $uid }}-warm)"/>
            <rect x="34" y="150" width="130" height="12" rx="6" fill="#071f5b" opacity="0.12"/>
            @break

        {{-- النتيجة: تقرير بدرجة وأولويات --}}
        @case('report')
            <rect x="38" y="18" width="132" height="148" rx="18" fill="#fff" stroke="#dfe8f5" stroke-width="4"/>
            <circle cx="76" cy="56" r="20" fill="none" stroke="#e7eefc" stroke-width="9"/>
            <path d="M76 36a20 20 0 0 1 16 32" stroke="url(#{{ $uid }}-cool)" stroke-width="9" stroke-linecap="round" fill="none"/>
            <rect x="106" y="42" width="52" height="9" rx="4.5" fill="url(#{{ $uid }}-deep)" opacity="0.2"/>
            <rect x="106" y="60" width="34" height="9" rx="4.5" fill="url(#{{ $uid }}-deep)" opacity="0.12"/>
            <rect x="56" y="96" width="102" height="12" rx="6" fill="url(#{{ $uid }}-cool)" opacity="0.85"/>
            <rect x="56" y="118" width="76" height="12" rx="6" fill="url(#{{ $uid }}-warm)" opacity="0.8"/>
            <rect x="56" y="140" width="52" height="12" rx="6" fill="url(#{{ $uid }}-deep)" opacity="0.16"/>
            @break

        {{-- الأسئلة: حوار قصير --}}
        @case('questions')
            <rect x="24" y="30" width="112" height="60" rx="18" fill="url(#{{ $uid }}-cool)"/>
            <path d="M56 90l-6 24 30-24z" fill="url(#{{ $uid }}-cool)"/>
            <rect x="86" y="102" width="110" height="54" rx="18" fill="url(#{{ $uid }}-deep)" opacity="0.16"/>
            <circle cx="56" cy="60" r="6" fill="#fff" opacity="0.9"/>
            <circle cx="80" cy="60" r="6" fill="#fff" opacity="0.7"/>
            <circle cx="104" cy="60" r="6" fill="#fff" opacity="0.5"/>
            <path d="M160 40l16 18-16 18" stroke="url(#{{ $uid }}-warm)" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            @break

        {{-- المعرفة: مقال وضوء فكرة --}}
        @case('knowledge')
            <rect x="30" y="40" width="120" height="120" rx="18" fill="#fff" stroke="#dfe8f5" stroke-width="4"/>
            <rect x="50" y="70" width="80" height="10" rx="5" fill="url(#{{ $uid }}-deep)" opacity="0.2"/>
            <rect x="50" y="92" width="60" height="10" rx="5" fill="url(#{{ $uid }}-deep)" opacity="0.14"/>
            <rect x="50" y="114" width="70" height="10" rx="5" fill="url(#{{ $uid }}-deep)" opacity="0.14"/>
            <circle cx="168" cy="46" r="26" fill="url(#{{ $uid }}-warm)" opacity="0.9"/>
            <path d="M168 32v16M168 58h.01" stroke="#fff" stroke-width="7" stroke-linecap="round"/>
            @break

        {{-- الافتراضي: وضوح — عدسة على المسار الصحيح --}}
        @default
            <circle cx="96" cy="84" r="52" fill="none" stroke="url(#{{ $uid }}-cool)" stroke-width="12"/>
            <path d="M136 124l44 40" stroke="url(#{{ $uid }}-deep)" stroke-width="14" stroke-linecap="round"/>
            <path d="M74 84l16 18 32-38" stroke="url(#{{ $uid }}-warm)" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    @endswitch
</svg>

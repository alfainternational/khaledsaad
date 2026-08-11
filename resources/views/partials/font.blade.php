{{--
    الطباعة — «الوضوح الطبي» (STYLESEED.md §٢)

    كان هنا ملف `Hacen-Tunisia.ttf` واحد مُصرَّحًا بـ`font-weight: 100 950`، وهو
    خط **ثابت بوزن واحد** (لا جدول fvar). النتيجة أن المتصفح اعتبره مغطّيًا لكل
    الأوزان فلم يصنع عريضًا: العنوان عند 950 والنص عند 400 كانا يُرسمان بنفس
    السُمك تمامًا (قِيس على الإنتاج: 546.44px للجملتين)، فضاعت الهرمية كلها.

    البديل: IBM Plex Sans Arabic (رخصة OFL) بأربعة أوزان حقيقية، بصيغة WOFF2،
    مقسّمة بـunicode-range فلا يُنزَّل المقطع اللاتيني إلا عند وجود حروف لاتينية.
    العربي 400 = 42KB مقابل 113KB للملف السابق.

    النسخة الـTTF من العائلة نفسها في assets/fonts لمولّد الـPDF (mPDF لا يقرأ WOFF2).
--}}
@php
    $plexWeights = [400, 500, 600, 700];

    /*
     * عائلة عرض ثانية للعناوين البطلة وحدها. Plex يقف عند ٧٠٠، والتكوين
     * الجديد يحتاج كتلة أثقل ليصير العنوان هو الصورة لا عنصرًا فوقها.
     * لا تُستعمل في نصّ يُقرأ — عائلتان لا ثالثة لهما (STYLESEED.md §٢).
     */
    $cairoWeights = [700, 900];
@endphp

{{-- تحميل مبكر للوزنين الأكثر استعمالًا فقط: نص الجسم والعناوين --}}
<link rel="preload" href="{{ asset('assets/fonts/plex/plex-ar-400.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('assets/fonts/plex/plex-ar-700.woff2') }}" as="font" type="font/woff2" crossorigin>

<style>
    @foreach ($plexWeights as $weight)
    @font-face {
        font-family: 'IBM Plex Sans Arabic';
        src: url('{{ asset("assets/fonts/plex/plex-ar-{$weight}.woff2") }}') format('woff2');
        font-weight: {{ $weight }};
        font-style: normal;
        font-display: swap;
        unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0898-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FEFF;
    }

    @font-face {
        font-family: 'IBM Plex Sans Arabic';
        src: url('{{ asset("assets/fonts/plex/plex-latin-{$weight}.woff2") }}') format('woff2');
        font-weight: {{ $weight }};
        font-style: normal;
        font-display: swap;
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }
    @endforeach

    @foreach ($cairoWeights as $weight)
    @font-face {
        font-family: 'Cairo Display';
        src: url('{{ asset("assets/fonts/cairo/cairo-ar-{$weight}.woff2") }}') format('woff2');
        font-weight: {{ $weight }};
        font-style: normal;
        /* optional لا swap: خط العرض لا يُنتظر. إن تأخّر يُرسم العنوان بـPlex
           ولا يقفز التخطيط لاحقًا — القفزة عند عنوان بهذا الحجم مزعجة. */
        font-display: optional;
        unicode-range: U+0600-06FF, U+0750-077F, U+08A0-08FF, U+200C-200E, U+2010-2011, U+FB50-FDFF, U+FE70-FEFF;
    }

    @font-face {
        font-family: 'Cairo Display';
        src: url('{{ asset("assets/fonts/cairo/cairo-latin-{$weight}.woff2") }}') format('woff2');
        font-weight: {{ $weight }};
        font-style: normal;
        font-display: optional;
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2122, U+2212, U+FEFF, U+FFFD;
    }
    @endforeach
</style>

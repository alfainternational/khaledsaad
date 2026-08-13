<!doctype html>
<html lang="{{ $appLocales->htmlLang() }}" dir="{{ $appLocales->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#071F5B">
        <meta name="description" content="@yield('description', config('brand.tagline'))">
        <meta name="author" content="{{ config('brand.name') }}">

        <title>@yield('title', 'خالد سعد | تشخيص وتسويق ونمو رقمي')</title>

        {{--
            القانوني بلغة الصفحة المعروضة لا بلغة المصدر.

            كان `url()->current()` يُسقط `?lang=`، فتُعلن الصفحة الإنجليزية
            أن نسختها القانونية هي العربية — بينما `hreflang` يقول إنها
            نسخة إنجليزية مستقلة. يحسم جوجل التناقض لصالح `canonical`،
            فتسقط الإنجليزية والفرنسية من الفهرس كلتاهما بلا أي عطل ظاهر.
        --}}
        <link rel="canonical" href="{{ $localeUrls->canonical() }}">
        @include('partials.hreflang')
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('assets/brand/khaled-saad-mark.png') }}">
        <link rel="icon" href="{{ asset('assets/brand/khaled-saad-approved.png') }}" type="image/png">

        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:locale" content="{{ $appLocales->ogLocale() }}">
        @foreach ($appLocales->enabled() as $alternateLocale)
            @if ($alternateLocale !== app()->getLocale())
                <meta property="og:locale:alternate" content="{{ $appLocales->ogLocale($alternateLocale) }}">
            @endif
        @endforeach
        <meta property="og:title" content="@yield('title', 'خالد سعد | تشخيص وتسويق ونمو رقمي')">
        <meta property="og:description" content="@yield('description', config('brand.tagline'))">
        <meta property="og:url" content="{{ $localeUrls->canonical() }}">
        <meta property="og:site_name" content="{{ config('brand.name') }}">
        <meta property="og:image" content="@yield('og_image', asset('assets/brand/khaled-saad-approved.png'))">
        @hasSection('og_image_width')
            <meta property="og:image:width" content="@yield('og_image_width')">
            <meta property="og:image:height" content="@yield('og_image_height')">
        @endif

        <meta name="twitter:card" content="@yield('twitter_card', 'summary')">
        <meta name="twitter:title" content="@yield('title', 'خالد سعد | تشخيص وتسويق ونمو رقمي')">
        <meta name="twitter:description" content="@yield('description', config('brand.tagline'))">
        <meta name="twitter:image" content="@yield('og_image', asset('assets/brand/khaled-saad-approved.png'))">

        @include('partials.theme')
        @include('partials.font')
        @include('partials.insights')

        @php($pageStructuredData = $structuredData ?? app(\App\Support\Content\ContentStructuredData::class)->personJson())
        <script type="application/ld+json">{!! $pageStructuredData !!}</script>

        @include('partials.js-translations')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    @php($layoutFamily = trim($__env->yieldContent('layout', 'marketing')))
    @php($interfaceFamily = trim($__env->yieldContent('interface_family', 'public')))
    <body data-layout="{{ $layoutFamily }}" data-interface-system="v2" data-interface-family="{{ $interfaceFamily }}">
        <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>
        @yield('content')
    </body>
</html>

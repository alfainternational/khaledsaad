<!doctype html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#071F5B">
        <meta name="description" content="@yield('description', config('brand.tagline'))">
        <meta name="author" content="{{ config('brand.name') }}">

        <title>@yield('title', 'خالد سعد | تشخيص وتسويق ونمو رقمي')</title>

        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('assets/brand/khaled-saad-mark.png') }}">
        <link rel="icon" href="{{ asset('assets/brand/khaled-saad-approved.png') }}" type="image/png">

        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:locale" content="ar_SA">
        <meta property="og:title" content="@yield('title', 'خالد سعد | تشخيص وتسويق ونمو رقمي')">
        <meta property="og:description" content="@yield('description', config('brand.tagline'))">
        <meta property="og:url" content="{{ url()->current() }}">
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

        @php($pageStructuredData = $structuredData ?? app(\App\Support\Content\ContentStructuredData::class)->personJson())
        <script type="application/ld+json">{!! $pageStructuredData !!}</script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    @php($layoutFamily = trim($__env->yieldContent('layout', 'marketing')))
    <body data-layout="{{ $layoutFamily }}">
        <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>
        @yield('content')
    </body>
</html>

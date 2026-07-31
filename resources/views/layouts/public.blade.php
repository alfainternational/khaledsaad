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
        <link rel="icon" href="{{ asset('assets/brand/khaled-saad-approved.png') }}" type="image/png">

        <meta property="og:type" content="website">
        <meta property="og:locale" content="ar_SA">
        <meta property="og:title" content="@yield('title', 'خالد سعد | تشخيص وتسويق ونمو رقمي')">
        <meta property="og:description" content="@yield('description', config('brand.tagline'))">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:site_name" content="{{ config('brand.name') }}">

        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="@yield('title', 'خالد سعد | تشخيص وتسويق ونمو رقمي')">
        <meta name="twitter:description" content="@yield('description', config('brand.tagline'))">

        @include('partials.theme')
        @include('partials.font')

        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => config('brand.name'),
                'alternateName' => config('brand.name_en'),
                'url' => url('/'),
                'jobTitle' => config('brand.headline'),
                'description' => config('brand.about.0'),
                'telephone' => config('brand.contact.phone'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'عرعر',
                    'addressRegion' => 'الحدود الشمالية',
                    'addressCountry' => 'SA',
                ],
                'alumniOf' => [
                    '@type' => 'CollegeOrUniversity',
                    'name' => config('brand.education.0.institution'),
                ],
                'sameAs' => [
                    config('brand.contact.linkedin'),
                    config('brand.contact.x'),
                ],
                'knowsAbout' => config('brand.skills'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    @php($layoutFamily = trim($__env->yieldContent('layout', 'marketing')))
    <body data-layout="{{ $layoutFamily }}">
        <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>
        @yield('content')
    </body>
</html>

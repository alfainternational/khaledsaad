<!doctype html>
<html lang="{{ $appLocales->htmlLang() }}" dir="{{ $appLocales->direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#071F5B">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title', 'الحساب') — خالد سعد</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        {{-- كان مفقودًا هنا وحده: صفحات الحساب تبقى فاتحة بينما بقية الموقع
             يتبع تفضيل الجهاز، فيومض الانتقال من الداكن إلى الفاتح عند الدخول. --}}
        @include('partials.theme')
        @include('partials.font')
        @include('partials.insights')
        @include('partials.js-translations')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php($layoutFamily = trim($__env->yieldContent('layout', 'auth')))
    <body class="auth-page" data-layout="{{ $layoutFamily }}" data-interface-system="v2" data-interface-family="auth">
        <a class="skip-link" href="#main-content">تجاوز إلى المحتوى</a>

        <main id="main-content" class="auth-card layout-page layout-page--auth">
            <a href="{{ route('home') }}" class="auth-card__brand">
                <x-brand-logo class="panel__brand-logo--on-light" />
                <x-brand-logo light class="panel__brand-logo--on-dark" />
            </a>

            <h1>@yield('heading')</h1>
            <p class="auth-card__lead">@yield('lead')</p>

            @yield('context')

            @if (session('status'))
                <p class="alert alert--success" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="alert alert--error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('form')

            <p class="auth-card__alt">@yield('alt')</p>

            {{--
                مبدّل اللغة هنا أيضًا، وليس في الهيدر وحده.

                شاشات الحساب هي أول ما يراه زائر لم يزر الموقع قط — يصل من
                رابط دعوة أو من رابط إعادة تعيين كلمة مرور، فلا كوكي لغة
                لديه ولا تفضيل محفوظ. وهي الشاشات الوحيدة بلا هيدر، فكان
                تغيير اللغة فيها يتطلّب تعديل الرابط يدويًّا.
            --}}
            @include('partials.language-switcher')
        </main>
    </body>
</html>

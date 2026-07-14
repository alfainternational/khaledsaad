<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6366f1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('brand/icon-app.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <title>{{ $title ?? 'لوحة الإدارة' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body shell-body" data-shell="admin">
    <div class="shell-overlay" data-shell-close hidden></div>

    <div class="app-shell shell-layout @guest shell-layout--single @endguest">
        @auth
            <aside class="app-sidebar shell-sidebar" id="admin-sidebar">
                <div class="shell-sidebar-top">
                    <button type="button" class="shell-icon-button shell-mobile-close" data-shell-close aria-label="إغلاق القائمة">
                        <span></span><span></span>
                    </button>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="app-brand">
                    <img class="app-brand-mark" src="{{ asset('brand/icon-app.png') }}" alt="">
                    <div>
                        <strong>لوحة الإدارة</strong>
                        <span>منصة التسويق الاستراتيجي</span>
                    </div>
                </a>

                <nav class="app-nav" aria-label="التنقل الإداري">
                    <span class="shell-nav-label">المنصة</span>
                    <a href="{{ route('admin.dashboard') }}" class="app-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><span>الرئيسية</span></a>
                    <a href="{{ route('admin.users.index') }}" class="app-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><span>المستخدمون</span></a>
                    <a href="{{ route('admin.accounts.index') }}" class="app-nav-link {{ request()->routeIs('admin.accounts.*') ? 'active' : '' }}"><span>الحسابات</span></a>
                    <a href="{{ route('admin.workspaces.index') }}" class="app-nav-link {{ request()->routeIs('admin.workspaces.*') ? 'active' : '' }}"><span>مساحات العمل</span></a>
                    <a href="{{ route('admin.projects.index') }}" class="app-nav-link {{ request()->routeIs('admin.projects.*') ? 'active' : '' }}"><span>المشاريع</span></a>
                    <a href="{{ route('admin.clients.index') }}" class="app-nav-link {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}"><span>العملاء</span></a>

                    <span class="shell-nav-label">التحكم</span>
                    <a href="{{ route('admin.plans.index') }}" class="app-nav-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}"><span>الخطط</span></a>
                    <a href="{{ route('admin.subscriptions.index') }}" class="app-nav-link {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}"><span>الاشتراكات</span></a>
                    <a href="{{ route('admin.tools.index') }}" class="app-nav-link {{ request()->routeIs('admin.tools.*') ? 'active' : '' }}"><span>الأدوات</span></a>
                    <a href="{{ route('admin.ai-templates.index') }}" class="app-nav-link {{ request()->routeIs('admin.ai-templates.*') ? 'active' : '' }}"><span>قوالب AI</span></a>
                    <a href="{{ route('admin.feature-flags.index') }}" class="app-nav-link {{ request()->routeIs('admin.feature-flags.*') ? 'active' : '' }}"><span>Feature Flags</span></a>

                    <span class="shell-nav-label">المحتوى والتسويق</span>
                    <a href="{{ route('admin.cms-pages.index') }}" class="app-nav-link {{ request()->routeIs('admin.cms-pages.*') ? 'active' : '' }}"><span>صفحات CMS</span></a>
                    <a href="{{ route('admin.blog-posts.index') }}" class="app-nav-link {{ request()->routeIs('admin.blog-posts.*') ? 'active' : '' }}"><span>المدونة</span></a>
                    <a href="{{ route('admin.case-studies.index') }}" class="app-nav-link {{ request()->routeIs('admin.case-studies.*') ? 'active' : '' }}"><span>دراسات الحالة</span></a>
                    <a href="{{ route('admin.community-posts.index') }}" class="app-nav-link {{ request()->routeIs('admin.community-posts.*') ? 'active' : '' }}"><span>المجتمع</span></a>
                    <a href="{{ route('admin.marketing-template-highlights.index') }}" class="app-nav-link {{ request()->routeIs('admin.marketing-template-highlights.*') ? 'active' : '' }}"><span>عروض القوالب</span></a>
                    <a href="{{ route('admin.partners.index') }}" class="app-nav-link {{ request()->routeIs('admin.partners.*') ? 'active' : '' }}"><span>الشركاء</span></a>
                    <a href="{{ route('admin.contact-messages.index') }}" class="app-nav-link {{ request()->routeIs('admin.contact-messages.*') ? 'active' : '' }}"><span>رسائل التواصل</span></a>

                    <span class="shell-nav-label">المراقبة</span>
                    <a href="{{ route('admin.tool-runs.index') }}" class="app-nav-link {{ request()->routeIs('admin.tool-runs.*') ? 'active' : '' }}"><span>سجل الأدوات</span></a>
                    <a href="{{ route('admin.ai-generations.index') }}" class="app-nav-link {{ request()->routeIs('admin.ai-generations.*') ? 'active' : '' }}"><span>مخرجات AI</span></a>
                    <a href="{{ route('admin.ai-credits.index') }}" class="app-nav-link {{ request()->routeIs('admin.ai-credits.*') ? 'active' : '' }}"><span>رصيد AI</span></a>
                    <a href="{{ route('admin.ai-control.index') }}" class="app-nav-link {{ request()->routeIs('admin.ai-control.*') ? 'active' : '' }}"><span>مركز تحكم الذكاء</span></a>
                    <a href="{{ route('admin.social-auth.index') }}" class="app-nav-link {{ request()->routeIs('admin.social-auth.*') ? 'active' : '' }}"><span>تسجيل الدخول الاجتماعي</span></a>
                    <a href="{{ route('admin.ai-lab.index') }}" class="app-nav-link {{ request()->routeIs('admin.ai-lab.*') ? 'active' : '' }}"><span>مختبر الذكاء (تطوير)</span></a>
                    <a href="{{ route('admin.agents.index') }}" class="app-nav-link {{ request()->routeIs('admin.agents.*') ? 'active' : '' }}"><span>قدرات الوكلاء</span></a>
                    <a href="{{ route('admin.comments.index') }}" class="app-nav-link {{ request()->routeIs('admin.comments.*') ? 'active' : '' }}"><span>التعليقات</span></a>
                    <a href="{{ route('admin.audit-logs.index') }}" class="app-nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}"><span>سجل التدقيق</span></a>
                </nav>

                <div class="app-sidebar-stack">
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm">لوحة العمل (المستخدم)</a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm app-logout-button">تسجيل الخروج</button>
                    </form>
                </div>
            </aside>
        @endauth

        <div class="app-main">
            @auth
                <header class="app-header shell-header">
                    <div>
                        <div class="shell-header-tools">
                            <button type="button" class="shell-icon-button shell-menu-button" data-shell-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="فتح القائمة">
                                <span></span><span></span><span></span>
                            </button>
                            <div class="shell-theme-cluster">
                                <button type="button" class="shell-icon-button shell-theme-toggle" data-theme-toggle aria-label="تبديل الوضع">
                                    <svg class="shell-theme-icon shell-theme-icon-moon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.354 15.354A9 9 0 018.646 3.646 9 9 0 1012 21a9 9 0 008.354-5.646z"/>
                                    </svg>
                                    <svg class="shell-theme-icon shell-theme-icon-sun" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.864-6.364l-1.06 1.06M6.696 17.304l-1.06 1.06m13.228 0l-1.06-1.06M6.696 6.696l-1.06-1.06M15.5 12a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <p class="app-header-kicker">{{ $pageKicker ?? 'إدارة المنصة' }}</p>
                        <h1 class="app-header-title">{{ $pageTitle ?? 'لوحة الإدارة' }}</h1>
                    </div>
                    <div class="app-header-meta shell-header-meta">
                        <div class="shell-header-chip shell-header-chip-accent">
                            <span>{{ auth()->user()->name }}</span>
                            <small>Super Admin · {{ auth()->user()->email }}</small>
                        </div>
                    </div>
                </header>
            @endauth

            <main class="app-content admin-content">
                @if (session('status'))
                    <div class="app-alert success">{{ session('status') }}</div>
                @endif

                @if (session('temporary_password'))
                    <div class="app-alert warning">
                        كلمة المرور المؤقتة: <strong>{{ session('temporary_password') }}</strong>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="app-alert danger">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>

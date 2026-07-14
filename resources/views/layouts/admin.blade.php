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

                @php
                    $navIcons = [
                        'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                        'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                        'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                        'grid' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
                        'folder' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                        'group' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                        'card' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                        'refresh' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                        'wrench' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
                        'doc' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        'flag' => 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 2H21l-3 6 3 6h-8.5l-1-2H5a2 2 0 00-2 2z',
                        'blog' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
                        'cap' => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
                        'chat' => 'M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l3.586-3.586z',
                        'star' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
                        'mail' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                        'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
                        'bolt' => 'M13 10V3L4 14h7v7l9-11h-7z',
                        'coin' => 'M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z',
                        'sliders' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
                        'globe' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9',
                        'beaker' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
                        'chip' => 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 7h10a0 0 0 010 0v10a0 0 0 010 0H7a0 0 0 010 0V7a0 0 0 010 0z',
                        'comment' => 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z',
                        'audit' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                    ];
                    $navSections = [
                        'المنصة' => [
                            ['admin.dashboard', 'admin.dashboard', 'الرئيسية', 'home'],
                            ['admin.users.index', 'admin.users.*', 'المستخدمون', 'users'],
                            ['admin.accounts.index', 'admin.accounts.*', 'الحسابات', 'building'],
                            ['admin.workspaces.index', 'admin.workspaces.*', 'مساحات العمل', 'grid'],
                            ['admin.projects.index', 'admin.projects.*', 'المشاريع', 'folder'],
                            ['admin.clients.index', 'admin.clients.*', 'العملاء', 'group'],
                        ],
                        'التحكم' => [
                            ['admin.plans.index', 'admin.plans.*', 'الخطط', 'card'],
                            ['admin.subscriptions.index', 'admin.subscriptions.*', 'الاشتراكات', 'refresh'],
                            ['admin.tools.index', 'admin.tools.*', 'الأدوات', 'wrench'],
                            ['admin.ai-templates.index', 'admin.ai-templates.*', 'قوالب AI', 'doc'],
                            ['admin.feature-flags.index', 'admin.feature-flags.*', 'Feature Flags', 'flag'],
                        ],
                        'المحتوى والتسويق' => [
                            ['admin.cms-pages.index', 'admin.cms-pages.*', 'صفحات CMS', 'doc'],
                            ['admin.blog-posts.index', 'admin.blog-posts.*', 'المدونة', 'blog'],
                            ['admin.case-studies.index', 'admin.case-studies.*', 'دراسات الحالة', 'cap'],
                            ['admin.community-posts.index', 'admin.community-posts.*', 'المجتمع', 'chat'],
                            ['admin.marketing-template-highlights.index', 'admin.marketing-template-highlights.*', 'عروض القوالب', 'star'],
                            ['admin.partners.index', 'admin.partners.*', 'الشركاء', 'group'],
                            ['admin.contact-messages.index', 'admin.contact-messages.*', 'رسائل التواصل', 'mail'],
                        ],
                        'المراقبة' => [
                            ['admin.tool-runs.index', 'admin.tool-runs.*', 'سجل الأدوات', 'clipboard'],
                            ['admin.ai-generations.index', 'admin.ai-generations.*', 'مخرجات AI', 'bolt'],
                            ['admin.ai-credits.index', 'admin.ai-credits.*', 'رصيد AI', 'coin'],
                            ['admin.ai-control.index', 'admin.ai-control.*', 'مركز تحكم الذكاء', 'sliders'],
                            ['admin.social-auth.index', 'admin.social-auth.*', 'تسجيل الدخول الاجتماعي', 'globe'],
                            ['admin.ai-lab.index', 'admin.ai-lab.*', 'مختبر الذكاء', 'beaker'],
                            ['admin.agents.index', 'admin.agents.*', 'قدرات الوكلاء', 'chip'],
                            ['admin.comments.index', 'admin.comments.*', 'التعليقات', 'comment'],
                            ['admin.audit-logs.index', 'admin.audit-logs.*', 'سجل التدقيق', 'audit'],
                        ],
                    ];
                @endphp
                <nav class="app-nav" aria-label="التنقل الإداري">
                    @foreach ($navSections as $sectionLabel => $links)
                        <span class="shell-nav-label">{{ $sectionLabel }}</span>
                        @foreach ($links as [$routeName, $pattern, $label, $iconKey])
                            <a href="{{ route($routeName) }}" class="app-nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $navIcons[$iconKey] }}"/></svg>
                                <span>{{ $label }}</span>
                            </a>
                        @endforeach
                    @endforeach
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
                <header class="app-header shell-header shell-bar">
                    <div class="shell-bar-start">
                        <button type="button" class="shell-icon-button shell-menu-button" data-shell-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="فتح القائمة">
                            <span></span><span></span><span></span>
                        </button>
                        <div class="shell-bar-search">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M21 21l-4.3-4.3"/></svg>
                            <input type="search" placeholder="ابحث أو اكتب أمراً..." aria-label="بحث">
                            <kbd class="shell-kbd">⌘K</kbd>
                        </div>
                    </div>
                    <div class="shell-bar-end">
                        <button type="button" class="shell-icon-button shell-theme-toggle" data-theme-toggle aria-label="تبديل الوضع">
                            <svg class="shell-theme-icon shell-theme-icon-moon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.354 15.354A9 9 0 018.646 3.646 9 9 0 1012 21a9 9 0 008.354-5.646z"/>
                            </svg>
                            <svg class="shell-theme-icon shell-theme-icon-sun" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.864-6.364l-1.06 1.06M6.696 17.304l-1.06 1.06m13.228 0l-1.06-1.06M6.696 6.696l-1.06-1.06M15.5 12a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z"/>
                            </svg>
                        </button>
                        <a href="{{ route('admin.contact-messages.index') }}" class="shell-icon-button shell-icon-badge" aria-label="الرسائل والتنبيهات" title="الرسائل والتنبيهات">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </a>
                        <div class="shell-user">
                            <span class="shell-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                            <div class="shell-user-info">
                                <strong>{{ auth()->user()->name }}</strong>
                                <small>Super Admin</small>
                            </div>
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

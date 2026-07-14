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
    <title>{{ $title ?? 'لوحة العمل' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body shell-body" data-shell="app">
    @if (session()->has('impersonator_user_id'))
        <div class="app-alert warning impersonation-banner" role="status">
            <form method="POST" action="{{ route('impersonation.stop') }}" class="admin-inline-actions impersonation-banner-form">
                @csrf
                <span>أنت في وضع انتحال جلسة مستخدم.</span>
                <button type="submit" class="btn btn-secondary btn-sm">إنهاء الانتحال والعودة للإدارة</button>
            </form>
        </div>
    @endif
    @php
        $workspaceType = $currentWorkspace?->type;
        $workspaceRole = $currentWorkspaceRole ?? 'viewer';
        $showClientsNav = in_array($workspaceType, ['agency'], true)
            || in_array($workspaceRole, ['owner', 'admin'], true)
            || request()->routeIs('clients.*');
        $showTeamNav = in_array($workspaceType, ['team', 'agency'], true)
            || request()->routeIs('team.*');
        $showStudioNav = (bool) entitlement('modules.ai_studio') || config('services.gemini.key');
        $showAgencyNav = $workspaceType === 'agency' || request()->routeIs('agency.*');
    @endphp

    <div class="shell-overlay" data-shell-close hidden></div>

    <div class="app-shell shell-layout">
        <aside class="app-sidebar shell-sidebar" id="app-sidebar">
            <div class="shell-sidebar-top">
                <button type="button" class="shell-icon-button shell-mobile-close" data-shell-close aria-label="إغلاق القائمة">
                    <span></span><span></span>
                </button>
            </div>

            <a href="{{ route('dashboard') }}" class="app-brand">
                <img class="app-brand-mark" src="{{ asset('brand/icon-app.png') }}" alt="">
                <div>
                    <strong>{{ $currentWorkspace?->name ?? 'مساحة العمل' }}</strong>
                    <span>{{ $currentWorkspace?->account?->subscription?->plan?->name_ar ?? 'بدون خطة' }}</span>
                </div>
            </a>

            <nav class="app-nav" aria-label="التنقل الداخلي">
                <a href="{{ route('dashboard') }}" class="app-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>الرئيسية</span>
                </a>
                <a href="{{ route('projects.index') }}" class="app-nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>المشاريع</span>
                </a>
                <a href="{{ route('tools.index') }}" class="app-nav-link {{ request()->routeIs('tools.*') ? 'active' : '' }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>الأدوات</span>
                </a>
                @if ($showStudioNav)
                    <a href="{{ route('studio.index') }}" class="app-nav-link {{ request()->routeIs('studio.*') ? 'active' : '' }}">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>الاستوديو</span>
                    </a>
                @endif
                @if ($showClientsNav)
                    <a href="{{ route('clients.index') }}" class="app-nav-link {{ request()->routeIs('clients.*') ? 'active' : '' }}">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>العملاء</span>
                    </a>
                @endif
                <a href="{{ route('approvals.index') }}" class="app-nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>الاعتمادات</span>
                </a>
                <a href="{{ route('reports.index') }}" class="app-nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span>التقارير</span>
                </a>
                @if ($showTeamNav)
                    <a href="{{ route('team.index') }}" class="app-nav-link {{ request()->routeIs('team.*') ? 'active' : '' }}">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>الفريق</span>
                    </a>
                @endif
                @if ($showAgencyNav)
                    <a href="{{ route('agency.index') }}" class="app-nav-link {{ request()->routeIs('agency.index') ? 'active' : '' }}">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span>الوكالة</span>
                    </a>
                @endif
                <a href="{{ route('billing.index') }}" class="app-nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <span>الخطط والدفع</span>
                </a>
                <a href="{{ route('account.index') }}" class="app-nav-link {{ request()->routeIs('account.*') ? 'active' : '' }}">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>الحساب</span>
                </a>
            </nav>

            <div class="app-sidebar-stack">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm app-logout-button">تسجيل الخروج</button>
                </form>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-header shell-header">
                <div>
                    <div class="shell-header-tools">
                        <button type="button" class="shell-icon-button shell-menu-button" data-shell-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="فتح القائمة">
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
                    @if(($pageKicker ?? '') !== '')
                        <p class="app-header-kicker">{{ $pageKicker }}</p>
                    @endif
                    <h1 class="app-header-title">{{ $pageTitle ?? 'لوحة العمل' }}</h1>
                </div>
                <div class="app-header-meta shell-header-meta">
                    @php
                        $wsTypeAr = ['agency' => 'وكالة', 'team' => 'فريق', 'personal' => 'شخصي'][$currentWorkspace?->type] ?? 'مساحة عمل';
                        $roleAr = ['owner' => 'مالك', 'admin' => 'مدير', 'editor' => 'محرر', 'contributor' => 'مساهم', 'viewer' => 'مشاهد', 'client' => 'عميل'][$currentWorkspaceRole] ?? 'عضو';
                    @endphp
                    <div class="shell-header-actions">
                        <a href="{{ route('approvals.index') }}" class="shell-icon-button shell-icon-badge" aria-label="الاعتمادات والتنبيهات" title="الاعتمادات والتنبيهات">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </a>
                    </div>
                    <div class="shell-header-chip">
                        <span>{{ auth()->user()->name }}</span>
                        <small>{{ $wsTypeAr }} · {{ $roleAr }}</small>
                    </div>
                    <span class="shell-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                </div>
            </header>

            <main class="app-content">
                @if (session('status'))
                    <div class="app-alert success">{{ session('status') }}</div>
                @endif

                @if (session('error'))
                    <div class="app-alert danger">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="app-alert danger">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @auth
    <button
        type="button"
        class="ai-chat-toggle"
        id="ai-chat-toggle"
        aria-expanded="false"
        aria-controls="ai-chat-panel"
        aria-label="المستشار الذكي"
        data-conversations-url="{{ route('api.ai.conversations.index') }}"
        data-research-url="{{ route('api.ai.research') }}"
    >
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        <span class="ai-chat-toggle-label">المستشار الذكي</span>
    </button>

    <aside class="ai-chat-panel" id="ai-chat-panel" hidden>
        <header class="ai-chat-header">
            <div class="ai-chat-heading">
                <strong id="ai-chat-title">المستشار الذكي</strong>
                <small>مستشار استراتيجي يعرف سياق مشروعك</small>
            </div>
            <div class="ai-chat-header-actions">
                <button type="button" class="ai-chat-icon-button" id="ai-chat-history-toggle" aria-label="المحادثات السابقة" title="المحادثات السابقة">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8m0-5v5h5M12 7v5l3 2"/></svg>
                </button>
                <button type="button" class="ai-chat-icon-button" id="ai-chat-new" aria-label="محادثة جديدة" title="محادثة جديدة">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14M5 12h14"/></svg>
                </button>
                <button type="button" class="ai-chat-icon-button" id="ai-chat-close" aria-label="إغلاق">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </header>

        <section class="ai-chat-history" id="ai-chat-history" hidden aria-label="المحادثات السابقة">
            <div class="ai-chat-history-list" id="ai-chat-history-list"></div>
        </section>

        <div class="ai-chat-messages" id="ai-chat-messages">
            <button type="button" class="ai-chat-load-older" id="ai-chat-load-older" hidden>عرض رسائل أقدم</button>
            @if (! empty($ambientAdvisor))
                <div class="ai-chat-msg ai-chat-msg-assistant ai-chat-msg-nextstep">
                    @if (! empty($ambientAdvisor['insight_headline']))
                        <p class="ai-chat-insight-line">{{ $ambientAdvisor['insight_headline'] }}</p>
                    @endif
                    <p><strong>خطوتك التالية:</strong> {{ $ambientAdvisor['headline'] }}</p>
                    @if (! empty($ambientAdvisor['body']))
                        <p>{{ $ambientAdvisor['body'] }}</p>
                    @endif
                    @if (! empty($ambientAdvisor['bullets']))
                        <ul class="ai-chat-capabilities">
                            @foreach ($ambientAdvisor['bullets'] as $advisorBullet)
                                <li>{{ $advisorBullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @foreach (($ambientAdvisor['actions'] ?? []) as $advisorAction)
                        @if (! empty($advisorAction['route']) && ! empty($advisorAction['label']))
                            <a href="{{ $advisorAction['route'] }}" class="btn btn-secondary btn-sm">{{ $advisorAction['label'] }}</a>
                        @endif
                    @endforeach
                </div>
            @endif
            <div class="ai-chat-msg ai-chat-msg-assistant">
                <p>أنا المستشار الذكي. أستطيع مساعدتك في:</p>
                <ul class="ai-chat-capabilities">
                    <li>مراجعة ما كتبته في أي أداة وتقديم ملاحظات عملية</li>
                    <li>شرح الفرق بين الأدوات ومتى تستخدم كل واحدة</li>
                    <li>اقتراح الخطوة التالية بناءً على ما أنجزته</li>
                    <li>الإجابة عن أي سؤال حول التسويق والاستراتيجية</li>
                </ul>
            </div>
            <div class="ai-chat-suggestions" id="ai-chat-suggestions">
                <button type="button" class="ai-chat-suggestion" data-ai-suggestion="ما الأداة التي يجب أن أبدأ بها؟">ما الأداة التي أبدأ بها؟</button>
                <button type="button" class="ai-chat-suggestion" data-ai-suggestion="ما الفرق بين أداة بناء العرض وأداة الجملة التعريفية؟">الفرق بين بناء العرض والجملة التعريفية؟</button>
                <button type="button" class="ai-chat-suggestion" data-ai-suggestion="راجع ما أدخلته حتى الآن وأخبرني ما الذي ينقصني">راجع تقدمي الحالي</button>
            </div>
        </div>

        <footer class="ai-chat-footer">
            <textarea
                class="ai-chat-input"
                id="ai-chat-input"
                rows="1"
                placeholder="اسأل عن أي شيء يخص مشروعك..."
                aria-label="رسالة للمستشار الذكي"
            ></textarea>
            <button type="button" class="ai-chat-send ai-chat-research" id="ai-chat-research" aria-label="بحث حيّ في الإنترنت" title="بحث حيّ في الإنترنت">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M21 21l-4.3-4.3"/></svg>
            </button>
            <button type="button" class="ai-chat-send" id="ai-chat-send" aria-label="إرسال">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
        </footer>
    </aside>
    @endauth
    @stack('scripts')
</body>
</html>

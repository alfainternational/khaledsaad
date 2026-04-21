@extends('layouts.admin', ['title' => 'لوحة الإدارة', 'pageTitle' => 'لوحة الإدارة', 'pageKicker' => 'Dashboard'])

@php
    $primaryAlert = $operationalAlerts[0] ?? null;
    $moreAlerts = array_slice($operationalAlerts, 1);
@endphp

@section('content')

<section class="dash-welcome">
    <div class="dash-welcome-text">
        <h2 class="dash-welcome-title">{{ auth()->user()->name }}، مركز التحكم</h2>
        <p class="dash-welcome-sub">إدارة المنصة · {{ number_format($stats['accounts']) }} حساب · {{ number_format($stats['users']) }} مستخدم</p>
    </div>
    <div class="dash-welcome-actions">
        <a href="{{ route('admin.users.create') }}" class="btn btn-secondary">مستخدم</a>
        <a href="{{ route('admin.accounts.create') }}" class="btn btn-secondary">حساب</a>
        <a href="{{ route('admin.workspaces.create') }}" class="btn btn-secondary">مساحة</a>
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">خطة</a>
    </div>
</section>

@if ($primaryAlert)
    <section class="dash-spotlight">
        <div class="dash-spotlight-glow"></div>
        <div class="dash-spotlight-content">
            <span class="dash-spotlight-label">تنبيه تشغيلي</span>
            <h3 class="dash-spotlight-title">{{ $primaryAlert['title'] }}</h3>
            <p>{{ $primaryAlert['body'] }}</p>
        </div>
        <a href="{{ $primaryAlert['url'] }}" class="btn btn-primary btn-lg dash-spotlight-cta">معالجة</a>
    </section>
@endif

@if ($moreAlerts !== [])
    <section class="card dash-card panel-modern mb-5">
        <div class="dash-card-head">
            <h3 class="heading-sm">تنبيهات إضافية</h3>
            <span class="app-badge">{{ count($moreAlerts) }}</span>
        </div>
        <div class="dash-feed-list">
            @foreach ($moreAlerts as $alert)
                <a href="{{ $alert['url'] }}" class="dash-feed-item dash-feed-item--link">
                    <div class="dash-feed-dot dash-feed-dot--audit"></div>
                    <div class="dash-feed-body">
                        <strong>{{ $alert['title'] }}</strong>
                        <span>{{ $alert['body'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif

<section class="dash-metrics">
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['users']) }}</strong>
        <span class="dash-metric-label">مستخدم</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['accounts']) }}</strong>
        <span class="dash-metric-label">حساب</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['workspaces']) }}</strong>
        <span class="dash-metric-label">مساحة عمل</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['projects']) }}</strong>
        <span class="dash-metric-label">مشروع</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['clients']) }}</strong>
        <span class="dash-metric-label">عميل</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['flags']) }}</strong>
        <span class="dash-metric-label">Flags نشطة</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['tool_runs']) }}</strong>
        <span class="dash-metric-label">تشغيل أداة</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['ai_generations']) }}</strong>
        <span class="dash-metric-label">مخرج AI</span>
    </article>
    <article class="dash-metric">
        <strong class="dash-metric-value">{{ number_format($stats['comments']) }}</strong>
        <span class="dash-metric-label">تعليق</span>
    </article>
</section>

<section class="dash-grid">
    <div class="dash-feed">
        <article class="card dash-card panel-modern">
            <div class="dash-card-head">
                <h3 class="heading-sm">اختصارات الإدارة</h3>
                <a href="{{ route('admin.feature-flags.create') }}" class="btn btn-ghost btn-sm">Flag جديد</a>
            </div>
            <div class="dash-feed-list">
                <a href="{{ route('admin.users.index') }}" class="dash-feed-item dash-feed-item--link">
                    <div class="dash-feed-dot dash-feed-dot--tool"></div>
                    <div class="dash-feed-body">
                        <strong>المستخدمون</strong>
                        <span>{{ number_format($stats['users']) }} مسجل</span>
                    </div>
                </a>
                <a href="{{ route('admin.accounts.index') }}" class="dash-feed-item dash-feed-item--link">
                    <div class="dash-feed-dot dash-feed-dot--tool"></div>
                    <div class="dash-feed-body">
                        <strong>الحسابات</strong>
                        <span>الخطط والاشتراكات</span>
                    </div>
                </a>
                <a href="{{ route('admin.subscriptions.index') }}" class="dash-feed-item dash-feed-item--link">
                    <div class="dash-feed-dot dash-feed-dot--approval"></div>
                    <div class="dash-feed-body">
                        <strong>الاشتراكات</strong>
                        <span>مراقبة الفوترة</span>
                    </div>
                </a>
                <a href="{{ route('admin.tool-runs.index') }}" class="dash-feed-item dash-feed-item--link">
                    <div class="dash-feed-dot dash-feed-dot--tool"></div>
                    <div class="dash-feed-body">
                        <strong>سجل الأدوات</strong>
                        <span>{{ number_format($stats['tool_runs']) }} تشغيل</span>
                    </div>
                </a>
                <a href="{{ route('admin.ai-generations.index') }}" class="dash-feed-item dash-feed-item--link">
                    <div class="dash-feed-dot dash-feed-dot--ai"></div>
                    <div class="dash-feed-body">
                        <strong>مخرجات AI</strong>
                        <span>{{ number_format($stats['ai_generations']) }} سجل</span>
                    </div>
                </a>
                <a href="{{ route('admin.audit-logs.index') }}" class="dash-feed-item dash-feed-item--link">
                    <div class="dash-feed-dot dash-feed-dot--audit"></div>
                    <div class="dash-feed-body">
                        <strong>سجل التدقيق</strong>
                        <span>كل العمليات الحساسة</span>
                    </div>
                </a>
            </div>
        </article>

        <article class="card dash-card panel-modern">
            <div class="dash-card-head">
                <h3 class="heading-sm">آخر النشاطات الإدارية</h3>
            </div>
            <div class="dash-feed-list">
                @forelse ($latestAuditLogs as $log)
                    <div class="dash-feed-item">
                        <div class="dash-feed-dot dash-feed-dot--audit"></div>
                        <div class="dash-feed-body">
                            <strong>{{ $log->action }}</strong>
                            <span>{{ $log->actor?->email ?? 'نظام' }}</span>
                        </div>
                        <time class="dash-feed-time">{{ $log->created_at?->diffForHumans() }}</time>
                    </div>
                @empty
                    <p class="app-empty">لا يوجد سجل تدقيق بعد.</p>
                @endforelse
            </div>
        </article>
    </div>

    <div class="dash-side">
        <article class="card dash-card panel-modern">
            <div class="dash-card-head">
                <h3 class="heading-sm">توزيع الخطط</h3>
                <a href="{{ route('admin.plans.index') }}" class="btn btn-ghost btn-sm">الخطط</a>
            </div>
            <div class="dash-feed-list">
                @forelse ($planDistribution as $plan)
                    <div class="dash-feed-item">
                        <div class="dash-feed-dot dash-feed-dot--ai"></div>
                        <div class="dash-feed-body">
                            <strong>{{ $plan->name_ar }}</strong>
                            <span>{{ $plan->code }}</span>
                        </div>
                        <span class="dash-feed-time">{{ $plan->subscriptions_count }} اشتراك</span>
                    </div>
                @empty
                    <p class="app-empty">لا توجد خطط بعد.</p>
                @endforelse
            </div>
        </article>
    </div>
</section>

@endsection

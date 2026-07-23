@extends('layouts.app')

@section('title', 'لوحة الإدارة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>نظرة عامة</h1>
        </div>
    </header>

    <section class="stat-row">
        <article class="stat"><span class="stat__value">{{ $stats['users'] }}</span><span class="stat__label">مستخدم</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['tools_live'] }}/{{ $stats['tools_total'] }}</span><span class="stat__label">أدوات حية</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['runs_completed'] }}</span><span class="stat__label">تشغيل مكتمل</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['runs_failed'] }}</span><span class="stat__label">تشغيل فاشل</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['reports'] }}</span><span class="stat__label">تقرير</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['ai_cost_usd'] }}$</span><span class="stat__label">تكلفة الذكاء</span></article>
    </section>

    <section aria-labelledby="recent-heading">
        <h2 id="recent-heading" class="section-title">آخر التشغيلات</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>الأداة</th><th>المشروع</th><th>الحالة</th><th>منذ</th></tr>
                </thead>
                <tbody>
                    @forelse ($recent_runs as $run)
                        <tr>
                            <td>{{ $run['tool'] }}</td>
                            <td>{{ $run['project'] }}</td>
                            <td>{{ $run['status'] }}</td>
                            <td>{{ $run['at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">لا تشغيلات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

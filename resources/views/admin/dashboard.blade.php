@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'لوحة الإدارة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>نظرة عامة</h1>
        </div>
    </header>

    <section class="layout-metrics" aria-label="الملخص الأساسي">
        <article class="stat"><span class="stat__value">{{ $stats['users'] }}</span><span class="stat__label">مستخدم</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['tools_live'] }}/{{ $stats['tools_total'] }}</span><span class="stat__label">تشخيصات منشورة</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['runs_completed'] }}</span><span class="stat__label">تحليلات مكتملة</span></article>
        <article class="stat"><span class="stat__value">{{ $stats['runs_failed'] }}</span><span class="stat__label">تحليلات متعثرة</span></article>
    </section>

    <div class="layout-main-aside">
        <section class="layout-flow" aria-labelledby="recent-heading">
            <h2 id="recent-heading" class="section-title">آخر التحليلات</h2>
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
                            <tr><td colspan="4">لا توجد تحليلات مسجلة بعد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="layout-aside layout-flow" aria-label="ملخص إضافي">
            <article class="stat"><span class="stat__value">{{ $stats['reports'] }}</span><span class="stat__label">تقرير</span></article>
            <article class="stat"><span class="stat__value">{{ $stats['ai_cost_usd'] }}$</span><span class="stat__label">تكلفة الذكاء</span></article>
        </aside>
    </div>
@endsection

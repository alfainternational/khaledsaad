@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'غرفة العمليات')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>غرفة العمليات: الصحة والتحويل والتدقيق</h1>
        </div>
    </header>

    {{-- بند ٢٠: صحة النظام — الطابور والمزوّدون وآخر الأعطال --}}
    <section class="layout-metrics" aria-label="صحة النظام">
        <article class="stat">
            <span class="stat__value">{{ $queue['pending'] ?? '—' }}</span>
            <span class="stat__label">مهمة في الطابور الآن</span>
        </article>
        <article @class(['stat', 'stat--alert' => ($queue['failed'] ?? 0) > 0])>
            <span class="stat__value">{{ $queue['failed'] ?? '—' }}</span>
            <span class="stat__label">مهام فاشلة{{ $queue['last_failed_at'] ? ' — آخرها '.\Illuminate\Support\Carbon::parse($queue['last_failed_at'])->diffForHumans() : '' }}</span>
        </article>
        @foreach ($providers as $provider => $configured)
            <article @class(['stat', 'stat--alert' => ! $configured])>
                <span class="stat__value">{{ $configured ? '✓' : '✗' }}</span>
                <span class="stat__label">مفتاح {{ $provider }} {{ $configured ? 'مضبوط' : 'غائب' }}</span>
            </article>
        @endforeach
    </section>

    @if ($failures->isNotEmpty())
        <section aria-labelledby="failures-heading">
            <h2 id="failures-heading" class="section-title">آخر تشغيلات فشلت ({{ $failures->count() }})</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>الأداة</th><th>المشروع</th><th>السبب</th><th>متى</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($failures as $run)
                            <tr>
                                <td>{{ $run->toolVersion->tool->title ?? '—' }}</td>
                                <td>{{ $run->project->name ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($run->failure_reason, 90) }}</td>
                                <td>{{ $run->completed_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- بند ٣٠: قمع التحويل — أربعة أرقام تقول أين ينكسر النموذج التجاري --}}
    <section aria-labelledby="funnel-heading">
        <h2 id="funnel-heading" class="section-title">قمع التحويل — آخر 30 يومًا</h2>
        <div class="layout-metrics">
            <article class="stat">
                <span class="stat__value">{{ \App\Support\Presentation\Num::int($funnel['guests']) }}</span>
                <span class="stat__label">ضيف جرّب تشخيصًا</span>
            </article>
            <article class="stat">
                <span class="stat__value">{{ \App\Support\Presentation\Num::int($funnel['registered']) }}</span>
                <span class="stat__label">حساب جديد سُجّل</span>
            </article>
            <article class="stat">
                <span class="stat__value">{{ \App\Support\Presentation\Num::int($funnel['activated']) }}</span>
                <span class="stat__label">مستخدم أكمل تشخيصًا</span>
            </article>
            <article class="stat">
                <span class="stat__value">{{ \App\Support\Presentation\Num::int($funnel['paying']) }}</span>
                <span class="stat__label">اشتراك مدفوع فعّال الآن</span>
            </article>
        </div>
    </section>

    {{-- بند ٢٢: سجل التدقيق — من فعل ماذا --}}
    <section aria-labelledby="audit-heading">
        <h2 id="audit-heading" class="section-title">سجل التدقيق — آخر {{ $audit->count() }} إجراءً</h2>

        @if ($audit->isEmpty())
            <p class="muted">لا إجراءات حساسة مسجلة بعد. يُسجَّل هنا: تعديل برومبت، سكّ إصدار، استيراد يدوي، وانتحال مستخدم.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>من</th><th>الإجراء</th><th>على</th><th>متى</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($audit as $entry)
                            <tr>
                                <td>{{ $entry->actor->name ?? 'النظام' }}</td>
                                <td>{{ $entry->action }}</td>
                                <td>{{ $entry->subject_type ? class_basename($entry->subject_type).'#'.$entry->subject_id : '—' }}</td>
                                <td>{{ $entry->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

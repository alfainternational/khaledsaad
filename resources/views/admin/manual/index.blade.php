@extends('layouts.app')
@section('layout', 'index')

@section('title', 'المراجعة اليدوية')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>طلبات المراجعة اليدوية</h1>
            <p class="muted">نزّل الإدخالات، عالجها بأي أداة خارجية، ثم الصق النتيجة فتظهر في حساب العميل بنفس شكل التقرير.</p>
        </div>
    </header>

    {{--
        المدّة المعلنة وحالتها.

        وعدٌ بمراجعة بشرية يتأخر يضرّ أكثر من عدم عرضه: من وُعِد بيومين ثم
        انتظر أسبوعًا يقرأ التأخير خذلانًا لا ازدحامًا. هذا الشريط يجعل
        التأخير مرئيًّا قبل أن يصير شكوى.
    --}}
    <section class="layout-metrics" aria-label="{{ __('حالة طابور المراجعة') }}">
        <article @class(['stat', 'stat--alert' => $queue['crowded'] || ! $queue['accepting']])>
            <span class="stat__value">
                {{ \App\Support\Presentation\Num::int($queue['open']) }}@if ($queue['max'] > 0)<span class="muted"> / {{ \App\Support\Presentation\Num::int($queue['max']) }}</span>@endif
            </span>
            <span class="stat__label">
                {{ $queue['accepting'] ? __('طلب مفتوح') : __('الطابور ممتلئ — الاستقبال متوقف') }}
            </span>
        </article>

        <article @class(['stat', 'stat--alert' => $queue['breached'] > 0])>
            <span class="stat__value">{{ \App\Support\Presentation\Num::int($queue['breached']) }}</span>
            <span class="stat__label">{{ __('تجاوز المدّة المعلنة') }}</span>
        </article>

        <article class="stat">
            <span class="stat__value">{{ \App\Support\Presentation\Num::int($queue['sla_hours']) }}</span>
            <span class="stat__label">{{ __('ساعة — المدّة الموعودة') }}</span>
        </article>
    </section>


    <section aria-labelledby="pending-heading">
        <h2 id="pending-heading" class="section-title">بانتظار المراجعة</h2>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>الأداة</th><th>المشروع</th><th>منذ</th><th>الإجراء</th></tr>
                </thead>
                <tbody>
                    @forelse ($pending as $row)
                        <tr>
                            <td>{{ $row['tool'] }}</td>
                            <td>{{ $row['project'] }}</td>
                            <td>{{ $row['requested_at'] }}</td>
                            <td>
                                <a href="{{ route('admin.manual.show', $row['uuid']) }}" class="btn btn--primary btn--sm">افتح وعالِج</a>
                                <a href="{{ route('admin.manual.export', $row['uuid']) }}" class="btn btn--ghost btn--sm">نزّل JSON</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">لا طلبات بانتظار المراجعة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($done !== [])
        <section aria-labelledby="done-heading">
            <h2 id="done-heading" class="section-title">آخر ما رُوجع يدويًا</h2>
            <ul class="list">
                @foreach ($done as $row)
                    <li class="list__item">
                        <strong>{{ $row['tool'] }}</strong>
                        <span class="muted">{{ $row['project'] }}</span>
                        <span class="badge badge--live">مراجَع يدويًا</span>
                        <span class="muted">{{ $row['reviewed_at'] }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection

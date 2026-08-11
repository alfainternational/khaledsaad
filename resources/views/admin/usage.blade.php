@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'استهلاك الذكاء الاصطناعي')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>تكلفة الذكاء الاصطناعي</h1>
            <p class="muted">آخر {{ $days }} يومًا. استخدم هذه الأرقام لمقارنة تكلفة كل تشخيص بحجم استخدامه.</p>
        </div>
    </header>

    <section class="layout-metrics" aria-label="الإجماليات الأساسية">
        <article class="stat">
            <span class="stat__value">{{ number_format($totals['cost_usd'], 3) }}$</span>
            <span class="stat__label">التكلفة الإجمالية</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $totals['runs'] }}</span>
            <span class="stat__label">عملية تحليل</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $totals['calls'] }}</span>
            <span class="stat__label">استدعاء نموذج</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $totals['avg_latency_ms'] }}ms</span>
            <span class="stat__label">متوسط الزمن</span>
        </article>
    </section>

    {{--
        سقوف الاستعلامات: ما يمنع الإنفاق، لا ما يسجّله بعد وقوعه.

        الجدولان أدناه يقولان ما أُنفق. هذا يقول من اقترب من حدّه ومن توقّف —
        وهو القرار الوحيد الذي يمكن اتخاذه قبل الفاتورة لا بعدها.
    --}}
    <section aria-labelledby="budgets-heading">
        <h2 id="budgets-heading" class="section-title">سقوف الاستعلامات — شهر {{ now()->format('Y-m') }}</h2>

        @if ($budgets === [])
            <p class="muted">لا مساحة استهلكت استعلامًا هذا الشهر. السقف يُنشأ عند أول حجز.</p>
        @else
            <p class="muted">الأقرب إلى حدّه أولًا. النسبة على المحجوز والمستهلك معًا — الحجز التزامٌ بالإنفاق لا نيّة.</p>

            <div class="table-wrap">
                <table class="table" data-table="matrix">
                    <thead>
                        <tr>
                            <th>مساحة العمل</th>
                            <th>المستهلك</th>
                            <th>المتبقي</th>
                            <th>النسبة</th>
                            <th>التكلفة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($budgets as $budget)
                            <tr>
                                <td>{{ $budget['workspace'] }}</td>
                                {{-- الرقم مع أساسه دائمًا (§١٣). --}}
                                <td>{{ $budget['committed'] }} من {{ $budget['limit'] }}</td>
                                <td>{{ $budget['remaining'] }}</td>
                                <td>
                                    {{-- شريط مرئي بعتبتي §4.4: تنبيه 80٪ وتوقف 100٪ (بند ١٢) --}}
                                    <div class="budget-bar" role="img" aria-label="{{ __('استهلاك :percent٪', ['percent' => $budget['usage_percent']]) }}">
                                        <span @class([
                                            'budget-bar__fill',
                                            'budget-bar__fill--warn' => $budget['usage_percent'] >= 80 && ! $budget['exhausted'],
                                            'budget-bar__fill--stop' => $budget['exhausted'],
                                        ]) style="inline-size: {{ min(100, $budget['usage_percent']) }}%"></span>
                                    </div>
                                    {{ $budget['usage_percent'] }}٪
                                </td>
                                <td>{{ number_format($budget['cost_usd'], 4) }}$</td>
                                <td>
                                    @if ($budget['exhausted'])
                                        متوقّفة حتى الشهر القادم
                                    @elseif ($budget['warned'])
                                        نُبِّهت عند 80٪
                                    @else
                                        تعمل
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="layout-main-aside">
    <div class="layout-flow">
    <section aria-labelledby="models-heading">
        <h2 id="models-heading" class="section-title">حسب النموذج</h2>
        <div class="table-wrap">
            <table class="table" data-table="matrix">
                <thead>
                    <tr><th>النموذج</th><th>الاستدعاءات</th><th>التكلفة</th><th>متوسط الزمن</th></tr>
                </thead>
                <tbody>
                    @forelse ($by_model as $row)
                        <tr>
                            <td>{{ $row['model'] }}</td>
                            <td>{{ $row['calls'] }}</td>
                            <td>{{ number_format($row['cost_usd'], 4) }}$</td>
                            <td>{{ $row['avg_latency_ms'] }}ms</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">لا استدعاءات مسجلة في هذه المدة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="stages-heading">
        <h2 id="stages-heading" class="section-title">حسب المرحلة</h2>
        <div class="table-wrap">
            <table class="table" data-table="matrix">
                <thead>
                    <tr><th>المرحلة</th><th>الاستدعاءات</th><th>التكلفة</th></tr>
                </thead>
                <tbody>
                    @forelse ($by_stage as $row)
                        <tr>
                            <td>{{ $row['stage'] }}</td>
                            <td>{{ $row['calls'] }}</td>
                            <td>{{ number_format($row['cost_usd'], 4) }}$</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">لا توجد استدعاءات مسجلة حسب المرحلة في هذه المدة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    </div>

    <aside class="layout-aside layout-flow" aria-label="مؤشرات وحالة إضافية">
        <article class="stat">
            <span class="stat__value">{{ $totals['invalid_outputs'] }}</span>
            <span class="stat__label">مخرج رُفض بالمخطط</span>
        </article>

    <section aria-labelledby="tools-heading">
        <h2 id="tools-heading" class="section-title">حالة الأدوات</h2>
        <div class="table-wrap">
            <table class="table" data-table="matrix">
                <thead>
                    <tr><th>الأداة</th><th>المفتاح</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    @foreach ($tools as $tool)
                        <tr>
                            <td>{{ $tool->title }}</td>
                            <td>{{ $tool->key }}</td>
                            <td>{{ $tool->status === 'published' ? 'مشغّلة' : 'قريبًا' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    </aside>
    </div>
@endsection

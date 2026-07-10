@extends('layouts.app', ['title' => 'توصيات المشروع', 'pageTitle' => 'التوصيات', 'pageKicker' => $project->name])

@section('content')
@php
    $impactLabels = ['high' => 'أثر عالٍ', 'medium' => 'أثر متوسط', 'low' => 'أثر منخفض'];
@endphp

<section class="studio-gen-header mb-6">
    <div>
        <h2 class="heading-lg">التوصيات المستخرجة من التحليل</h2>
        <p class="text-muted">توصيات مرتّبة حسب الأولوية والأثر، كل منها مبني على دليل من تدقيق مشروعك.</p>
    </div>
    <div class="studio-gen-actions">
        <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">العودة للمشروع</a>
    </div>
</section>

@if ($recommendations->isEmpty())
    <div class="card exec-empty">
        <h3 class="heading-sm">لا توجد توصيات بعد</h3>
        <p class="text-muted">شغّل تحليل المشروع أولاً، وستظهر التوصيات تلقائياً بعد اكتماله.</p>
        <p><a href="{{ route('projects.show', $project) }}" class="btn btn-primary">اذهب لتشغيل التحليل</a></p>
    </div>
@else
    <section class="exec-priority-summary" aria-labelledby="exec-priority-summary-title">
        <div class="exec-priority-summary-head">
            <div>
                <p class="section-kicker">ملخص القرار</p>
                <h3 id="exec-priority-summary-title" class="heading-md">ابدأ بهذه الأولويات الثلاث</h3>
                <p class="text-muted">هذه ليست كل التفاصيل، لكنها أقصر طريق لتحويل التشخيص إلى فعل واضح وقابل للقياس.</p>
            </div>
            <span class="exec-priority-count">{{ $recommendations->take(3)->count() }} إجراءات</span>
        </div>

        <div class="exec-priority-grid">
            @foreach ($recommendations->take(3) as $summaryIndex => $rec)
                @php($pkg = $rec->executionPackages->first())
                <article class="exec-priority-card" data-priority-summary-title="{{ $rec->title }}">
                    <div class="exec-priority-card-head">
                        <span class="exec-rank">{{ $summaryIndex + 1 }}</span>
                        <div>
                            <p class="exec-priority-label">المشكلة</p>
                            <h4>{{ $rec->title }}</h4>
                        </div>
                    </div>

                    <div class="exec-priority-body">
                        <p><strong>الدليل:</strong> {{ $rec->evidence ?: 'لا يوجد دليل تفصيلي بعد، راجع نتيجة التدقيق الأصلية قبل التنفيذ.' }}</p>
                        <p><strong>الإجراء العملي:</strong> {{ $rec->rationale ?: 'حوّل هذه الأولوية إلى حزمة تنفيذ لتحديد الخطوات المطلوبة.' }}</p>
                        <p><strong>الأثر المتوقع:</strong> {{ $impactLabels[$rec->estimated_impact] ?? $rec->estimated_impact }} · ثقة {{ round($rec->confidence * 100) }}%</p>
                    </div>

                    <div class="exec-priority-foot">
                        @if ($pkg)
                            <a href="{{ route('execution-packages.show', $pkg) }}" class="btn btn-primary btn-sm">عرض التنفيذ</a>
                        @else
                            <form method="POST" action="{{ route('projects.recommendations.package', [$project, $rec]) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">ابدأ التنفيذ</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <div class="exec-list">
        @foreach ($recommendations as $i => $rec)
            @php($pkg = $rec->executionPackages->first())
            <article class="exec-rec">
                <div class="exec-rec-head">
                    <span class="exec-rank">{{ $i + 1 }}</span>
                    <h3 class="exec-rec-title">{{ $rec->title }}</h3>
                    <span class="exec-chip exec-chip--{{ $rec->severity }}">{{ $rec->severity === 'high' ? 'حرج' : ($rec->severity === 'low' ? 'منخفض' : 'متوسط') }}</span>
                    <span class="exec-chip exec-chip--muted">{{ $impactLabels[$rec->estimated_impact] ?? $rec->estimated_impact }}</span>
                    <span class="exec-chip exec-chip--muted">ثقة {{ round($rec->confidence * 100) }}%</span>
                </div>

                @if ($rec->evidence)
                    <p class="exec-evidence"><strong>الدليل:</strong> {{ $rec->evidence }}</p>
                @endif
                @if ($rec->rationale)
                    <p class="exec-evidence"><strong>الإجراء المقترح:</strong> {{ $rec->rationale }}</p>
                @endif

                <div class="exec-rec-foot">
                    @if ($pkg)
                        <span class="exec-chip exec-chip--low">حزمة تنفيذ جاهزة</span>
                        <a href="{{ route('execution-packages.show', $pkg) }}" class="btn btn-primary">عرض حزمة التنفيذ</a>
                    @else
                        <form method="POST" action="{{ route('projects.recommendations.package', [$project, $rec]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">حوّل لحزمة تنفيذ</button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection

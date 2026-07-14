@extends('layouts.app', ['title' => 'مصادر المعرفة', 'pageTitle' => 'مصادر المعرفة', 'pageKicker' => $project->name])

@php
    $statusLabels = [
        'stored' => 'مخزّن',
        'indexed' => 'مُستخرج',
        'failed' => 'فشل الاستخراج',
        'needs_worker' => 'قيد المعالجة',
    ];
    $maxMegabytes = number_format($maxBytes / 1048576, 1);
    $acceptAttr = '.'.implode(',.', $extensions);
@endphp

@section('content')
<section class="card mb-8">
    <div class="app-section-head">
        <div>
            <p class="section-kicker">تغذية الذكاء</p>
            <h3 class="heading-sm">أضف مصدر معرفة لهذا المشروع</h3>
            <p class="text-caption">ارفع ملفاً (نص، PDF، Word، Excel، أو صورة) ليقرأه النظام ويبني عليه تحليله ومخرجاته. الحد الأقصى {{ $maxMegabytes }} ميجابايت للملف.</p>
        </div>
        <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary btn-sm">رجوع للمشروع</a>
    </div>

    <form method="POST" action="{{ route('projects.knowledge.store', $project) }}" enctype="multipart/form-data" class="app-form-grid">
        @csrf
        <label class="app-field">
            <span>اختر الملف</span>
            <input class="app-input" type="file" name="file" accept="{{ $acceptAttr }}" required>
            <small class="text-caption">الصيغ المدعومة: {{ implode('، ', $extensions) }}</small>
        </label>
        <div class="app-form-actions">
            <button type="submit" class="btn btn-primary btn-sm">رفع واستخراج المعرفة</button>
        </div>
    </form>
</section>

<section class="card mb-8">
    <div class="app-section-head">
        <div>
            <h3 class="heading-sm">الملفات المرفوعة</h3>
            <p class="text-caption">مصادر المعرفة المرتبطة بهذا المشروع وحالتها الحالية.</p>
        </div>
        <span class="app-badge">{{ $uploads->count() }}</span>
    </div>

    <div class="app-list">
        @forelse ($uploads as $upload)
            <div class="app-list-item">
                <div>
                    <strong>{{ $upload->original_name }}</strong>
                    <small>
                        {{ strtoupper($upload->extension) }}
                        · {{ number_format($upload->byte_size / 1024, 1) }} كيلوبايت
                        · {{ $upload->created_at?->diffForHumans() }}
                    </small>
                    @if ($upload->status === 'failed' && $upload->error_code)
                        <small>سبب الفشل: {{ $upload->error_code }}</small>
                    @endif
                </div>
                <div class="app-inline-actions">
                    <span class="app-badge">{{ $statusLabels[$upload->status] ?? $upload->status }}</span>

                    @if ($upload->status === 'failed')
                        <form method="POST" action="{{ route('projects.knowledge.retry', [$project, $upload]) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">إعادة المحاولة</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('projects.knowledge.destroy', [$project, $upload]) }}" onsubmit="return confirm('حذف مصدر المعرفة نهائياً؟');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="app-empty">لم تُرفع أي مصادر معرفة لهذا المشروع بعد. ابدأ برفع أول ملف من الأعلى.</p>
        @endforelse
    </div>
</section>
@endsection

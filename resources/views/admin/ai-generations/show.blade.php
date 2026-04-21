@extends('layouts.admin', ['title' => 'تفاصيل مخرج AI', 'pageTitle' => 'مخرج AI #' . $generation->id, 'pageKicker' => 'مخرجات AI'])

@section('content')
<section class="admin-detail-header mb-6">
    <div>
        <h2>{{ $generation->template?->name ?? 'مخرج AI' }} — {{ $generation->project?->name ?? '—' }}</h2>
        <p>{{ $generation->status }} · {{ number_format($generation->tokens_used ?? 0) }} توكن · {{ $generation->created_at?->format('Y-m-d H:i') }}</p>
        @if ($generation->ops_review_status)
            <p class="text-caption">حالة التشغيل: <span class="app-badge">{{ $generation->ops_review_status }}</span></p>
        @endif
    </div>
    <div class="admin-actions-cell">
        @if ($generation->ops_review_status !== 'voided')
            <form method="POST" action="{{ route('admin.ai-generations.retry', $generation) }}" onsubmit="return confirm('توليد نسخة جديدة بنفس المدخلات؟')">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">إعادة التوليد</button>
            </form>
        @endif
        <a href="{{ route('admin.ai-generations.index') }}" class="btn btn-secondary">العودة</a>
    </div>
</section>

<section class="admin-panel panel-modern mb-8">
    <div class="admin-panel-head"><h2>وسم وتحقيق تشغيلي</h2></div>
    <form method="POST" action="{{ route('admin.ai-generations.ops', $generation) }}" class="admin-form-grid cols-2">
        @csrf
        @method('PATCH')
        <label class="admin-field">
            <span>حالة المراجعة</span>
            <select name="ops_review_status" class="admin-input">
                <option value="">— بدون —</option>
                @foreach (['open', 'investigating', 'resolved', 'voided'] as $st)
                    <option value="{{ $st }}" @selected(old('ops_review_status', $generation->ops_review_status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>وسوم (مفصولة بفاصلة)</span>
            <input type="text" name="ops_tags" class="admin-input" value="{{ old('ops_tags', is_array($generation->ops_tags) ? implode(', ', $generation->ops_tags) : '') }}">
        </label>
        <label class="admin-field cols-span-2">
            <span>ملاحظة داخلية</span>
            <textarea name="ops_note" class="admin-input" rows="3">{{ old('ops_note', $generation->ops_note) }}</textarea>
        </label>
        <div class="admin-form-actions cols-span-2">
            <button type="submit" class="btn btn-secondary btn-lg">حفظ وسوم التشغيل</button>
        </div>
    </form>
</section>

<section class="admin-grid admin-two-col mb-8">
    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>المعلومات</h2></div>
        <div class="admin-list">
            <div class="admin-list-item"><div><strong>المعرف</strong></div><span>{{ $generation->public_id }}</span></div>
            <div class="admin-list-item"><div><strong>القالب</strong></div><span>{{ $generation->template?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>الحساب</strong></div><span>{{ $generation->account?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>المالك</strong></div><span>{{ $generation->account?->owner?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>المستخدم</strong></div><span>{{ $generation->author?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>مساحة العمل</strong></div><span>{{ $generation->workspace?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>المشروع</strong></div><span>{{ $generation->project?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>التوكنات</strong></div><span>{{ number_format($generation->tokens_used ?? 0) }}</span></div>
            <div class="admin-list-item"><div><strong>الحالة</strong></div><span class="app-badge">{{ $generation->status }}</span></div>
            @if ($generation->error)
                <div class="admin-list-item">
                    <div><strong>الخطأ</strong></div>
                    <div class="app-alert danger">{{ $generation->error }}</div>
                </div>
            @endif
        </div>
    </article>

    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>المدخلات</h2></div>
        <pre class="admin-code-block">{{ json_encode($generation->inputs_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
    </article>
</section>

<section class="admin-panel panel-modern mb-8">
    <div class="admin-panel-head"><h2>المخرجات</h2></div>
    <pre class="admin-code-block">{{ $generation->output ?? 'لا يوجد مخرج.' }}</pre>
</section>
@endsection

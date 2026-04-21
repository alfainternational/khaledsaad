@extends('layouts.admin', ['title' => 'تفاصيل التشغيل', 'pageTitle' => ($toolRun->tool?->name ?? $toolRun->tool_code), 'pageKicker' => 'سجل الأدوات'])

@section('content')
<section class="admin-detail-header mb-6">
    <div>
        <h2>{{ $toolRun->tool?->name ?? $toolRun->tool_code }} — {{ $toolRun->project?->name ?? '—' }}</h2>
        <p>{{ $toolRun->mode }} · {{ $toolRun->completeness_score ?? 0 }}% · {{ $toolRun->created_at?->format('Y-m-d H:i') }}</p>
        @if ($toolRun->ops_review_status)
            <p class="text-caption">حالة التشغيل: <span class="app-badge">{{ $toolRun->ops_review_status }}</span></p>
        @endif
    </div>
    <div class="admin-actions-cell">
        @if ($toolRun->ops_review_status !== 'voided')
            <form method="POST" action="{{ route('admin.tool-runs.retry', $toolRun) }}" onsubmit="return confirm('إنشاء تشغيل جديد بنفس المدخلات؟')">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">إعادة التشغيل</button>
            </form>
        @endif
        <a href="{{ route('admin.tool-runs.index') }}" class="btn btn-secondary">العودة للسجل</a>
    </div>
</section>

<section class="admin-panel panel-modern mb-8">
    <div class="admin-panel-head"><h2>وسم وتحقيق تشغيلي</h2></div>
    <form method="POST" action="{{ route('admin.tool-runs.ops', $toolRun) }}" class="admin-form-grid cols-2">
        @csrf
        @method('PATCH')
        <label class="admin-field">
            <span>حالة المراجعة</span>
            <select name="ops_review_status" class="admin-input">
                <option value="">— بدون —</option>
                @foreach (['open', 'investigating', 'resolved', 'voided'] as $st)
                    <option value="{{ $st }}" @selected(old('ops_review_status', $toolRun->ops_review_status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>وسوم (مفصولة بفاصلة)</span>
            <input type="text" name="ops_tags" class="admin-input" value="{{ old('ops_tags', is_array($toolRun->ops_tags) ? implode(', ', $toolRun->ops_tags) : '') }}" placeholder="billing, quality, …">
        </label>
        <label class="admin-field cols-span-2">
            <span>ملاحظة داخلية</span>
            <textarea name="ops_note" class="admin-input" rows="3">{{ old('ops_note', $toolRun->ops_note) }}</textarea>
        </label>
        <div class="admin-form-actions cols-span-2">
            <button type="submit" class="btn btn-secondary btn-lg">حفظ وسوم التشغيل</button>
        </div>
    </form>
</section>

<section class="admin-grid admin-two-col mb-8">
    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>المعلومات الأساسية</h2></div>
        <div class="admin-list">
            <div class="admin-list-item"><div><strong>المعرف</strong></div><span>{{ $toolRun->public_id }}</span></div>
            <div class="admin-list-item"><div><strong>الأداة</strong></div><span>{{ $toolRun->tool?->name ?? $toolRun->tool_code }}</span></div>
            <div class="admin-list-item"><div><strong>الوضع</strong></div><span class="app-badge">{{ $toolRun->mode }}</span></div>
            <div class="admin-list-item"><div><strong>المشروع</strong></div><span>{{ $toolRun->project?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>مساحة العمل</strong></div><span>{{ $toolRun->workspace?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>الحساب</strong></div><span>{{ $toolRun->workspace?->account?->name ?? '—' }}</span></div>
            <div class="admin-list-item"><div><strong>المستخدم</strong></div><span>{{ $toolRun->author?->name ?? '—' }} ({{ $toolRun->author?->email ?? '' }})</span></div>
            <div class="admin-list-item"><div><strong>الاكتمال</strong></div><span>{{ $toolRun->completeness_score ?? 0 }}%</span></div>
            <div class="admin-list-item"><div><strong>التاريخ</strong></div><span>{{ $toolRun->created_at?->format('Y-m-d H:i:s') }}</span></div>
        </div>
    </article>

    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>الملخص</h2></div>
        <div class="admin-list">
            @if (!empty($toolRun->summary_json))
                <div class="admin-list-item" style="flex-direction:column;align-items:flex-start;gap:8px">
                    <strong>{{ $toolRun->summary_json['headline'] ?? '—' }}</strong>
                    <p style="margin:0;opacity:.8">{{ $toolRun->summary_json['text'] ?? '' }}</p>
                    @if (!empty($toolRun->summary_json['bullets']))
                        <ul style="margin:4px 0 0;padding-inline-start:16px">
                            @foreach ($toolRun->summary_json['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @else
                <p class="admin-empty">لا يوجد ملخص.</p>
            @endif
        </div>
    </article>
</section>

<section class="admin-grid admin-two-col mb-8">
    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>المدخلات (inputs_json)</h2></div>
        <pre class="admin-code-block">{{ json_encode($toolRun->inputs_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
    </article>

    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>المخرجات (output_json)</h2></div>
        <pre class="admin-code-block">{{ json_encode($toolRun->output_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
    </article>
</section>
@endsection

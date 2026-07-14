@extends('layouts.admin', ['title' => 'مختبر الذكاء', 'pageTitle' => 'مختبر الذكاء الداخلي', 'pageKicker' => 'تطوير'])

@section('content')

@if (session('success'))
    <div class="admin-alert success mb-6">{{ session('success') }}</div>
@endif

{{-- تشغيل النواة (Brain Playground) --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>تشغيل النواة (Brain)</h2></div>
    <form method="POST" action="{{ route('admin.ai-lab.run') }}" class="admin-form-stack">
        @csrf
        <label class="admin-field">
            <span>النيّة (intent)</span>
            <select name="intent" class="admin-input">
                @foreach (['web_research' => 'بحث حيّ', 'insight' => 'تحليل واستدلال', 'next_step' => 'الخطوة التالية', 'tool_analysis' => 'تحليل أداة'] as $val => $label)
                    <option value="{{ $val }}" @selected(($labInput['intent'] ?? '') === $val)>{{ $label }} ({{ $val }})</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>الاستعلام / الإشارة (query)</span>
            <input type="text" name="query" class="admin-input" value="{{ $labInput['query'] ?? '' }}" placeholder="مثال: استراتيجية تسعير SaaS">
        </label>
        <label class="admin-field">
            <span>معرّف المساحة (اختياري)</span>
            <input type="number" name="workspace_id" class="admin-input" value="{{ $labInput['workspace_id'] ?? '' }}" min="1" placeholder="workspace_id">
        </label>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-lg">تشغيل</button>
        </div>
    </form>

    @if ($labResult)
        <div class="admin-alert {{ isset($labResult['error']) ? 'danger' : 'success' }} mt-4">
            @if (isset($labResult['error']))
                خطأ: {{ $labResult['error'] }}
            @else
                <strong>{{ $labResult['code'] }}</strong> · ثقة {{ $labResult['confidence'] }}% · مصدر: {{ $labResult['source'] }}
            @endif
        </div>
        @if (! isset($labResult['error']))
            <div class="admin-table-wrap mt-2">
                <table class="admin-table">
                    <tbody>
                        <tr><th>العنوان</th><td>{{ $labResult['headline'] }}</td></tr>
                        @if (! empty($labResult['body']))<tr><th>النص</th><td>{{ $labResult['body'] }}</td></tr>@endif
                        @if (! empty($labResult['bullets']))
                            <tr><th>النقاط</th><td><ul class="admin-list">@foreach ($labResult['bullets'] as $b)<li>{{ $b }}</li>@endforeach</ul></td></tr>
                        @endif
                        <tr><th>meta</th><td><pre class="admin-code">{{ json_encode($labResult['meta'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre></td></tr>
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</section>

{{-- قاضي الجودة (تقييم على المضمون لا الطول) --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>قاضي الجودة (Gemini) <small>— يقيس المضمون لا الطول</small></h2></div>
    <form method="POST" action="{{ route('admin.ai-lab.judge') }}" class="admin-form-stack">
        @csrf
        <label class="admin-field">
            <span>اسم الحقل</span>
            <input type="text" name="label" class="admin-input" placeholder="مثال: العميل المثالي" value="{{ old('label') }}" required>
        </label>
        <label class="admin-field">
            <span>تعليمات الحقل (ما المطلوب)</span>
            <input type="text" name="instructions" class="admin-input" placeholder="مثال: حدّد من يدفع فعلاً وأين تجده" value="{{ old('instructions') }}">
        </label>
        <label class="admin-field">
            <span>النص المراد تقييمه</span>
            <textarea name="value" class="admin-input" rows="3" required>{{ old('value') }}</textarea>
        </label>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">قيّم الجودة</button>
        </div>
    </form>
    @if ($judgeResult)
        <div class="admin-alert {{ isset($judgeResult['error']) ? 'danger' : 'success' }} mt-4">
            @if (isset($judgeResult['error']))
                {{ $judgeResult['error'] }}
            @else
                <strong>الجودة: {{ $judgeResult['score'] }}/100</strong> — {{ $judgeResult['reason'] }}
            @endif
        </div>
    @endif
</section>

{{-- مدقّق حقول الأدوات --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>مدقّق حقول الأدوات</h2></div>
    <div class="admin-stats-grid mb-4">
        <div class="admin-stat-card"><span class="admin-stat-label">الأدوات</span><strong class="admin-stat-value">{{ $toolAudit['tool_count'] }}</strong></div>
        <div class="admin-stat-card"><span class="admin-stat-label">حقول نصّية</span><strong class="admin-stat-value">{{ $toolAudit['text_count'] }}</strong></div>
        <div class="admin-stat-card"><span class="admin-stat-label">حقول اختيار</span><strong class="admin-stat-value">{{ $toolAudit['select_count'] }}</strong></div>
        <div class="admin-stat-card"><span class="admin-stat-label">مشاكل محتملة</span><strong class="admin-stat-value">{{ $toolAudit['issues_total'] }}</strong></div>
    </div>
    @if (! empty($toolAudit['select_fields']))
        <h3 class="admin-subhead">حقول اختيار (راجع أيّها يجب أن يكون كتابة حرّة)</h3>
        <ul class="admin-list">
            @foreach ($toolAudit['select_fields'] as $sf)
                <li>{{ $sf['ref'] }} <span class="app-badge">{{ $sf['options'] }} خيارات</span></li>
            @endforeach
        </ul>
    @endif
    @if (! empty($toolAudit['enumerated_text_fields']))
        <h3 class="admin-subhead mt-2">حقول نصّية تعليماتها تعدّد خيارات (مرشّحة للمراجعة)</h3>
        <ul class="admin-list">
            @foreach ($toolAudit['enumerated_text_fields'] as $ef)
                <li>{{ $ef['ref'] }} <small>— {{ \Illuminate\Support\Str::limit($ef['tip'], 80) }}</small></li>
            @endforeach
        </ul>
    @endif
    @if (! empty($toolAudit['missing_instructions']))
        <h3 class="admin-subhead mt-2">حقول بلا تعليمات</h3>
        <ul class="admin-list">@foreach ($toolAudit['missing_instructions'] as $mi)<li>{{ $mi }}</li>@endforeach</ul>
    @endif
    @if ($toolAudit['issues_total'] === 0 && empty($toolAudit['select_fields']))
        <p class="admin-empty">لا مشاكل في تعريفات الحقول.</p>
    @endif
</section>

{{-- أوامر النظام --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>أوامر المحرّك</h2></div>
    <div class="admin-inline-actions">
        @foreach (['ai:learn' => 'تشغيل التعلّم', 'ai:distill' => 'تشغيل التقطير', 'ai:compile' => 'تجميع الذكاء'] as $cmd => $label)
            <form method="POST" action="{{ route('admin.ai-lab.command') }}">
                @csrf
                <input type="hidden" name="command" value="{{ $cmd }}">
                <button type="submit" class="btn btn-secondary">{{ $label }}</button>
            </form>
        @endforeach
    </div>
    <p class="admin-hint mt-2">التقطير قد يستغرق وقتاً (نداءات LLM). الأوامر تعمل تلقائياً عبر cron أيضاً.</p>
</section>

{{-- سجلّ المهارات --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>سجلّ المهارات <small>({{ count($skills) }})</small></h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>#</th><th>الكود</th><th>الكلاس</th></tr></thead>
            <tbody>
                @foreach ($skills as $i => $skill)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><span class="app-badge">{{ $skill['code'] }}</span></td>
                        <td><code>{{ $skill['class'] }}</code></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="admin-hint mt-2">الترتيب = أولوية التفعيل (MoE): النواة تختار أول مهارة مطابقة للسياق.</p>
</section>

{{-- قاعدة المعرفة الخام --}}
<section class="admin-panel panel-modern">
    <div class="admin-panel-head"><h2>قاعدة المعرفة الخام <small>({{ count($knowledge) }})</small></h2></div>
    @forelse ($knowledge as $entry)
        <details class="admin-details">
            <summary>{{ $entry['key'] ?? '—' }} <small>{{ \Illuminate\Support\Str::limit($entry['learned_at'] ?? '', 16, '') }}</small></summary>
            <pre class="admin-code">{{ json_encode($entry['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        </details>
    @empty
        <p class="admin-empty">قاعدة المعرفة فارغة. شغّل التعلّم أو التقطير أو البحث الحيّ.</p>
    @endforelse
</section>

@endsection

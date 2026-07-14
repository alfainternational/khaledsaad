@extends('layouts.admin', ['title' => 'تسجيل الدخول الاجتماعي', 'pageTitle' => 'تسجيل الدخول الاجتماعي', 'pageKicker' => 'المصادقة'])

@section('content')

@if (session('success'))
    <div class="admin-alert success mb-6">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="admin-alert danger mb-6">{{ $errors->first() }}</div>
@endif

{{-- الحالة --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>حالة المزوّدين</h2></div>
    <div class="admin-stats-grid">
        @foreach ($providers as $key => $p)
            <div class="admin-stat">
                <span class="admin-stat-label">{{ $p['label'] }}</span>
                <span class="app-badge {{ $p['ready'] ? 'app-badge-success' : 'app-badge-danger' }}">{{ $p['ready'] ? 'مهيّأ' : 'غير مكتمل' }}</span>
            </div>
        @endforeach
    </div>
</section>

{{-- النموذج --}}
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>المفاتيح (تعمل فوراً بعد الحفظ)</h2></div>
    <form method="POST" action="{{ route('admin.social-auth.update') }}" class="admin-form-stack">
        @csrf
        @method('PATCH')

        <p class="report-muted">
            أنشئ تطبيق OAuth لدى كل مزوّد، والصق Client ID و Client Secret هنا. القيم تُخزَّن مشفّرة الحفظ
            وتُطبَّق فوراً دون لمس ملف <code>.env</code> أو إعادة نشر. Client Secret سرّ: اتركه فارغاً للإبقاء على الحالي.
            <strong>مهم:</strong> سجّل رابط الـ callback الظاهر تحت كل مزوّد في لوحة المزوّد نفسه.
        </p>

        @foreach ($providers as $key => $p)
            <h3 class="admin-subhead">{{ $p['label'] }}</h3>
            <p class="report-muted">
                رابط الـ callback المطلوب تسجيله: <code>{{ $p['callback'] }}</code>
            </p>
            <label class="admin-field">
                <span>Client ID</span>
                <input type="text" name="{{ $key }}_client_id" class="admin-input" autocomplete="off"
                       value="{{ old($key.'_client_id', $p['client_id']) }}" placeholder="Client ID من لوحة المزوّد">
            </label>
            <label class="admin-field">
                <span>Client Secret <small>(الحالي: {{ $p['secret_hint'] }} — اتركه فارغاً للإبقاء)</small></span>
                <input type="password" name="{{ $key }}_client_secret" class="admin-input" autocomplete="off"
                       placeholder="أدخل سرّاً جديداً لتغييره">
            </label>
        @endforeach

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-lg">حفظ المفاتيح</button>
        </div>
    </form>
</section>

@endsection

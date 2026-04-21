@extends('layouts.admin', ['title' => 'صلاحيات الـ Workspace', 'pageTitle' => 'Workspace Entitlements', 'pageKicker' => 'Overrides'])

@section('content')
<section class="admin-grid admin-two-col mb-6">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>{{ $workspace->name }}</h2>
            <a href="{{ route('admin.workspaces.show', $workspace) }}" class="btn btn-secondary btn-sm">صفحة المساحة</a>
        </div>
        <div class="admin-meta-list">
            <div><span>النوع</span><strong>{{ $workspace->type }}</strong></div>
            <div><span>الحساب</span><strong>{{ $workspace->account?->name }}</strong></div>
            <div><span>الخطة</span><strong>{{ $workspace->account?->subscription?->plan?->name_ar ?? 'بدون خطة' }}</strong></div>
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>إضافة override</h2>
        </div>
        <form method="POST" action="{{ route('admin.workspaces.entitlements.store', $workspace) }}" class="admin-form-grid cols-2">
            @csrf
            <label class="admin-field">
                <span>المفتاح</span>
                <input class="admin-input" name="key">
            </label>
            <label class="admin-field">
                <span>نوع القيمة</span>
                <select class="admin-input" name="value_type">
                    @foreach (['boolean', 'integer', 'float', 'string', 'json'] as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field cols-span-2">
                <span>القيمة</span>
                <input class="admin-input" name="value" placeholder="true / 10 / نص / JSON">
            </label>
            <button type="submit" class="btn btn-primary btn-lg">حفظ الـ Override</button>
        </form>
    </article>
</section>

<section class="admin-grid admin-two-col">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>صلاحيات الخطة</h2>
        </div>
        <div class="admin-list">
            @forelse ($planEntitlements as $key => $value)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $key }}</strong>
                        <small>plan_default</small>
                    </div>
                    <span>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : var_export($value, true) }}</span>
                </div>
            @empty
                <p class="admin-empty">لا توجد صلاحيات افتراضية من الخطة.</p>
            @endforelse
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>Workspace Overrides</h2>
        </div>
        <div class="admin-list">
            @forelse ($workspaceEntitlements as $entitlement)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $entitlement->key }}</strong>
                        <small>{{ $entitlement->value_type }}</small>
                    </div>
                    <div class="admin-actions-cell">
                        <span>{{ is_array($entitlement->value) ? json_encode($entitlement->value['value'] ?? $entitlement->value, JSON_UNESCAPED_UNICODE) : $entitlement->decodedValue() }}</span>
                        <form method="POST" action="{{ route('admin.workspaces.entitlements.destroy', [$workspace, $entitlement]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="admin-empty">لا توجد Overrides بعد.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection

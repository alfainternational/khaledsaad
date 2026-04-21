@extends('layouts.admin', ['title' => 'تفاصيل مساحة العمل', 'pageTitle' => 'تفاصيل مساحة العمل', 'pageKicker' => 'Workspace'])

@section('content')
<section class="admin-grid admin-two-col mb-6">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>{{ $workspace->name }}</h2>
            <div class="admin-actions-cell">
                <a href="{{ route('admin.workspaces.edit', $workspace) }}" class="btn btn-secondary btn-sm">تعديل البيانات</a>
                <a href="{{ route('admin.workspaces.entitlements.index', $workspace) }}" class="btn btn-ghost btn-sm">إدارة الـ Overrides</a>
            </div>
        </div>
        <div class="admin-meta-list">
            <div><span>Public ID</span><strong>{{ $workspace->public_id }}</strong></div>
            <div><span>الحساب</span><strong>{{ $workspace->account?->name }}</strong></div>
            <div><span>المالك</span><strong>{{ $workspace->account?->owner?->email ?? '—' }}</strong></div>
            <div><span>النوع</span><strong>{{ $workspace->type }}</strong></div>
            <div><span>الخطة</span><strong>{{ $workspace->account?->subscription?->plan?->name_ar ?? 'بدون خطة' }}</strong></div>
            <div><span>الحالة</span><strong>{{ $workspace->status }}</strong></div>
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>تشغيل المساحة</h2>
        </div>
        <form method="POST" action="{{ route('admin.workspaces.status', $workspace) }}" class="admin-form-grid cols-2">
            @csrf
            @method('PATCH')
            <label class="admin-field">
                <span>حالة المساحة</span>
                <select name="status" class="admin-input">
                    @foreach ($workspaceStatuses as $status)
                        <option value="{{ $status }}" @selected($workspace->status === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <div class="admin-form-actions admin-align-end">
                <button type="submit" class="btn btn-primary btn-lg">حفظ الحالة</button>
            </div>
        </form>

        <div class="admin-list mt-4">
            <div class="admin-list-item">
                <div>
                    <strong>الأعضاء</strong>
                    <small>جميع أعضاء مساحة العمل</small>
                </div>
                <span>{{ $workspace->members_count }}</span>
            </div>
            <div class="admin-list-item">
                <div>
                    <strong>المشاريع</strong>
                    <small>المشاريع المرتبطة بهذه المساحة</small>
                </div>
                <span>{{ $workspace->projects_count }}</span>
            </div>
            <div class="admin-list-item">
                <div>
                    <strong>العملاء</strong>
                    <small>عملاء المساحة الحاليون</small>
                </div>
                <span>{{ $workspace->clients_count }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.workspaces.destroy', $workspace) }}" class="mt-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-ghost btn-lg">حذف مساحة العمل نهائياً</button>
        </form>
    </article>
</section>

<section class="admin-grid admin-two-col mb-6">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>أعضاء المساحة</h2>
        </div>
        <div class="admin-list">
            @forelse ($workspace->members as $member)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $member->user?->name ?? 'مستخدم محذوف' }}</strong>
                        <small>{{ $member->user?->email ?? '—' }}</small>
                    </div>
                    <span>{{ $member->role }} | {{ $member->status }}</span>
                </div>
            @empty
                <p class="admin-empty">لا يوجد أعضاء في هذه المساحة حتى الآن.</p>
            @endforelse
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>العملاء</h2>
        </div>
        <div class="admin-list">
            @forelse ($workspace->clients as $client)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $client->name }}</strong>
                        <small>{{ $client->public_id }}</small>
                    </div>
                    <span>{{ $client->status }}</span>
                </div>
            @empty
                <p class="admin-empty">لا يوجد عملاء مرتبطون بهذه المساحة.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="admin-grid admin-two-col">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>المشاريع</h2>
        </div>
        <div class="admin-list">
            @forelse ($workspace->projects as $project)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $project->name }}</strong>
                        <small>Stage {{ $project->stage }} | {{ $project->client?->name ?? 'بدون عميل' }}</small>
                    </div>
                    <span>{{ $project->status }}</span>
                </div>
            @empty
                <p class="admin-empty">لا توجد مشاريع داخل هذه المساحة.</p>
            @endforelse
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>الصلاحيات</h2>
        </div>
        <div class="admin-list">
            @forelse ($planEntitlements as $entitlement)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $entitlement->key }}</strong>
                        <small>plan_default</small>
                    </div>
                    <span>{{ is_array($entitlement->decodedValue()) ? json_encode($entitlement->decodedValue(), JSON_UNESCAPED_UNICODE) : var_export($entitlement->decodedValue(), true) }}</span>
                </div>
            @empty
                <p class="admin-empty">لا توجد صلاحيات افتراضية من الخطة.</p>
            @endforelse
        </div>

        @if ($workspaceOverrides->isNotEmpty())
            <div class="admin-panel-head mt-4">
                <h2>الـ Overrides الحالية</h2>
            </div>
            <div class="admin-list">
                @foreach ($workspaceOverrides as $entitlement)
                    <div class="admin-list-item">
                        <div>
                            <strong>{{ $entitlement->key }}</strong>
                            <small>{{ $entitlement->value_type }}</small>
                        </div>
                        <span>{{ is_array($entitlement->decodedValue()) ? json_encode($entitlement->decodedValue(), JSON_UNESCAPED_UNICODE) : var_export($entitlement->decodedValue(), true) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </article>
</section>
@endsection

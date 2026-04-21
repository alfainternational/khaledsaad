@extends('layouts.admin', ['title' => 'تفاصيل المستخدم', 'pageTitle' => 'تفاصيل المستخدم', 'pageKicker' => 'User'])

@section('content')
<section class="admin-grid admin-two-col mb-6">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>{{ $managedUser->name }}</h2>
        </div>
        <div class="admin-meta-list">
            <div><span>البريد</span><strong>{{ $managedUser->email }}</strong></div>
            <div><span>الحالة</span><strong>{{ $managedUser->status->value }}</strong></div>
            <div><span>مدير عام</span><strong>{{ $managedUser->is_super_admin ? 'نعم' : 'لا' }}</strong></div>
            <div><span>آخر دخول</span><strong>{{ $managedUser->last_login_at?->toDateTimeString() ?? '—' }}</strong></div>
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>إجراءات التشغيل</h2>
            <a href="{{ route('admin.users.edit', $managedUser) }}" class="btn btn-secondary btn-sm">تعديل البيانات</a>
        </div>

        <div class="admin-form-stack">
            <form method="POST" action="{{ route('admin.users.status', $managedUser) }}" class="admin-inline-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $managedUser->status->value === 'active' ? 'frozen' : 'active' }}">
                <button type="submit" class="btn {{ $managedUser->status->value === 'active' ? 'btn-ghost' : 'btn-primary' }} btn-lg">
                    {{ $managedUser->status->value === 'active' ? 'تجميد المستخدم' : 'إعادة التفعيل' }}
                </button>
            </form>

            <form method="POST" action="{{ route('admin.users.reset-password', $managedUser) }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-lg">إعادة تعيين كلمة المرور</button>
            </form>

            @if ($managedUser->id !== auth()->id() && ! $managedUser->is_super_admin)
                <form method="POST" action="{{ route('admin.users.impersonate', $managedUser) }}" onsubmit="return confirm('ستدخل المنصة باسم هذا المستخدم. متابعة؟')">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg">انتحال الجلسة (دعم)</button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-lg">حذف المستخدم نهائياً</button>
            </form>
        </div>
    </article>
</section>

<section class="admin-grid admin-two-col">
    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>الحسابات المرتبطة</h2>
        </div>
        <div class="admin-list">
            @forelse ($managedUser->ownedAccounts as $account)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $account->name }}</strong>
                        <small>{{ $account->subscription?->plan?->name_ar ?? 'بدون خطة' }}</small>
                    </div>
                    <a href="{{ route('admin.accounts.show', $account) }}" class="btn btn-secondary btn-sm">عرض الحساب وترقية الباقة</a>
                </div>
            @empty
                <p class="admin-empty">لا توجد حسابات مملوكة لهذا المستخدم.</p>
            @endforelse
        </div>
    </article>

    <article class="admin-panel">
        <div class="admin-panel-head">
            <h2>عضويات الـ Workspaces</h2>
        </div>
        <div class="admin-list">
            @forelse ($managedUser->workspaceMemberships as $membership)
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $membership->workspace->name }}</strong>
                        <small>{{ $membership->role }}</small>
                    </div>
                    <div class="admin-actions-cell">
                        <a href="{{ route('admin.workspaces.show', $membership->workspace) }}" class="btn btn-secondary btn-sm">التفاصيل</a>
                        <a href="{{ route('admin.workspaces.entitlements.index', $membership->workspace) }}" class="btn btn-ghost btn-sm">الصلاحيات</a>
                    </div>
                </div>
            @empty
                <p class="admin-empty">لا توجد عضويات Workspaces لهذا المستخدم.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection

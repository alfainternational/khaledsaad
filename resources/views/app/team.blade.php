@extends('layouts.app', ['title' => 'الفريق', 'pageTitle' => 'الفريق والدعوات', 'pageKicker' => 'Team'])

@section('content')
<section class="app-grid app-two-col mb-8">
    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">إضافة عضو جديد</h3>
        </div>
        <form method="POST" action="{{ route('team.invitations.store') }}" class="app-form-grid cols-2">
            @csrf
            <label class="app-field">
                <span>البريد</span>
                <input class="app-input" name="email" value="{{ old('email') }}">
            </label>
            <label class="app-field">
                <span>الدور</span>
                <select class="app-input" name="role">
                    @foreach (['owner', 'admin', 'editor', 'contributor', 'viewer', 'client'] as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>مدة الصلاحية بالأيام</span>
                <input class="app-input" type="number" name="expires_in_days" value="{{ old('expires_in_days', 7) }}">
            </label>
            <div class="app-form-actions app-align-end">
                <button type="submit" class="btn btn-primary btn-lg">إنشاء دعوة</button>
            </div>
        </form>
    </article>

    <article class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">الأعضاء الحاليون</h3>
        </div>
        <div class="app-list">
            @forelse ($members as $member)
                <div class="app-list-item">
                    <div>
                        <strong>{{ $member->user?->name ?? 'مستخدم محذوف' }}</strong>
                        <small>{{ $member->user?->email ?? '—' }} · {{ $member->role }}</small>
                    </div>
                    <form method="POST" action="{{ route('team.members.destroy', $member) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">إزالة</button>
                    </form>
                </div>
            @empty
                <p class="app-empty">لا يوجد أعضاء آخرون في هذه المساحة.</p>
            @endforelse
        </div>
    </article>
</section>

<section class="card">
    <div class="app-section-head">
        <h3 class="heading-sm">الدعوات المفتوحة</h3>
    </div>
    <div class="app-list">
        @forelse ($invitations as $invitation)
            <div class="app-list-item">
                <div>
                    <strong>{{ $invitation->email }}</strong>
                    <small>{{ $invitation->role }} · {{ $invitation->status }} · {{ $invitation->expires_at?->toDateString() ?? 'بدون تاريخ' }}</small>
                </div>
                <div class="app-inline-actions">
                    @if ($invitation->status === 'pending' && strtolower($invitation->email) === strtolower(auth()->user()->email))
                        <form method="POST" action="{{ route('team.invitations.accept', $invitation->token) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">قبول الدعوة</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('team.invitations.destroy', $invitation) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="app-empty">لا توجد دعوات معلقة حالياً.</p>
        @endforelse
    </div>
</section>
@endsection

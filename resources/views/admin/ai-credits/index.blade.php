@extends('layouts.admin', ['title' => 'رصيد AI', 'pageTitle' => 'سجل رصيد AI Credits', 'pageKicker' => 'المراقبة'])

@section('content')
@if ($lowBalanceRows->isNotEmpty())
<section class="admin-panel panel-modern mb-6">
    <div class="admin-panel-head"><h2>حسابات برصيد منخفض</h2></div>
    <div class="admin-list">
        @foreach ($lowBalanceRows as $row)
            @if ($row['account'])
                <div class="admin-list-item">
                    <div>
                        <strong>{{ $row['account']->name }}</strong>
                        <small>{{ $row['account']->owner?->email ?? '—' }}</small>
                    </div>
                    <span class="app-badge app-badge-danger">{{ $row['balance'] }}</span>
                    <a href="{{ route('admin.accounts.show', $row['account']) }}" class="btn btn-secondary btn-sm">الحساب</a>
                </div>
            @endif
        @endforeach
    </div>
</section>
@endif

<section class="admin-grid admin-two-col mb-6">
    <article class="admin-panel panel-modern">
        <div class="admin-panel-head"><h2>تعديل رصيد يدوي</h2></div>
        <form method="POST" action="{{ route('admin.ai-credits.store') }}" class="admin-form-stack">
            @csrf
            <label class="admin-field">
                <span>الحساب</span>
                <select name="account_id" class="admin-input" required>
                    <option value="">— اختر —</option>
                    @foreach ($accountsForGrant as $acc)
                        <option value="{{ $acc->id }}" @selected(old('account_id') == $acc->id)>{{ $acc->name }} — {{ $acc->owner?->email }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>التغيير (موجب يشحن، سالب يخصم)</span>
                <input type="number" name="delta" class="admin-input" value="{{ old('delta', 100) }}" required>
            </label>
            <label class="admin-field">
                <span>السبب (يظهر في السجل)</span>
                <input type="text" name="reason" class="admin-input" value="{{ old('reason', 'admin.manual_adjustment') }}" required maxlength="255">
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-primary btn-lg">تطبيق على الرصيد</button>
            </div>
        </form>
    </article>
</section>

<section class="admin-toolbar">
    <form method="GET" class="admin-filters">
        <input type="number" name="account_id" value="{{ request('account_id') }}" placeholder="معرّف الحساب" class="admin-input" min="1">
        <input type="text" name="reason" value="{{ request('reason') }}" placeholder="بحث بالسبب…" class="admin-input">
        <button type="submit" class="btn btn-secondary">بحث</button>
    </form>
</section>

<section class="admin-panel panel-modern">
    <div class="admin-panel-head">
        <h2>سجل الرصيد <small>({{ $entries->total() }})</small></h2>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الحساب</th>
                    <th>المالك</th>
                    <th>التغيير</th>
                    <th>السبب</th>
                    <th>المرجع</th>
                    <th>التاريخ</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry->id }}</td>
                        <td>{{ $entry->account?->name ?? '—' }}</td>
                        <td>{{ $entry->account?->owner?->name ?? '—' }}</td>
                        <td>
                            <span class="app-badge {{ $entry->delta >= 0 ? 'app-badge-success' : 'app-badge-danger' }}">
                                {{ $entry->delta >= 0 ? '+' : '' }}{{ $entry->delta }}
                            </span>
                        </td>
                        <td>{{ $entry->reason }}</td>
                        <td>{{ $entry->ref_id ?? '—' }}</td>
                        <td>{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="admin-empty">لا توجد سجلات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $entries->links() }}</div>
</section>
@endsection

@extends('layouts.app')

@section('title', 'المدفوعات')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>سجل المدفوعات</h1>
            <p class="muted">التحويلات اليدوية لا تمنح رصيدًا حتى تعتمدها هنا.</p>
        </div>
    </header>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert--error">{{ $errors->first() }}</div>
    @endif

    <section class="stat-row">
        <article class="stat"><span class="stat__value">{{ $totals['paid'] }}</span><span class="stat__label">إجمالي المدفوع</span></article>
        <article class="stat"><span class="stat__value">{{ $totals['count'] }}</span><span class="stat__label">عملية ناجحة</span></article>
        <article class="stat"><span class="stat__value">{{ $totals['pending'] }}</span><span class="stat__label">قيد الانتظار</span></article>
    </section>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th><th>المستخدم</th><th>الغرض</th><th>السعر</th><th>المحصَّل</th>
                    <th>الأرصدة</th><th>البوابة</th><th>الحالة</th><th>التاريخ</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment['id'] }}</td>
                        <td>{{ $payment['user'] }}</td>
                        <td>{{ $payment['purpose'] }}</td>
                        <td>{{ $payment['amount'] }}</td>
                        <td>{{ $payment['charged'] }}</td>
                        <td>{{ $payment['credits'] }}</td>
                        <td>{{ $payment['provider'] }}</td>
                        <td>
                            {{ $payment['status'] }}
                            @if ($payment['reason'])
                                <p class="muted">{{ $payment['reason'] }}</p>
                            @endif
                        </td>
                        <td>{{ $payment['at'] }}</td>
                        <td class="table__actions">
                            @if ($payment['awaiting'])
                                <form method="POST" action="{{ route('admin.payments.approve', $payment['id']) }}"
                                    onsubmit="return confirm('تأكيد استلام التحويل ومنح ما يقابله؟')">
                                    @csrf
                                    <button type="submit" class="btn btn--primary btn--sm">اعتماد</button>
                                </form>
                                <form method="POST" action="{{ route('admin.payments.reject', $payment['id']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn--ghost btn--sm">رفض</button>
                                </form>
                            @endif
                            @if ($payment['refundable'] > 0)
                                <form method="POST" action="{{ route('admin.payments.refund', $payment['id']) }}"
                                    onsubmit="return confirm('تنفيذ الاسترداد عبر بوابة الدفع؟')">
                                    @csrf
                                    <input type="number" name="amount" min="0.01" step="0.01" max="{{ $payment['refundable'] }}" value="{{ $payment['refundable'] }}" required>
                                    <input type="text" name="reason" value="requested_by_customer" required>
                                    <button type="submit" class="btn btn--ghost btn--sm">استرداد</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10">لا مدفوعات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

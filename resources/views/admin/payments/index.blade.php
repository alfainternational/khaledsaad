@extends('layouts.app')

@section('title', 'المدفوعات')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>سجل المدفوعات</h1>
        </div>
    </header>

    <section class="stat-row">
        <article class="stat"><span class="stat__value">{{ $totals['paid'] }}</span><span class="stat__label">إجمالي المدفوع</span></article>
        <article class="stat"><span class="stat__value">{{ $totals['count'] }}</span><span class="stat__label">عملية ناجحة</span></article>
    </section>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>#</th><th>المستخدم</th><th>الغرض</th><th>المبلغ</th><th>الأرصدة</th><th>البوابة</th><th>الحالة</th><th>التاريخ</th></tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment['id'] }}</td>
                        <td>{{ $payment['user'] }}</td>
                        <td>{{ $payment['purpose'] }}</td>
                        <td>{{ $payment['amount'] }}</td>
                        <td>{{ $payment['credits'] }}</td>
                        <td>{{ $payment['provider'] }}</td>
                        <td>{{ $payment['status'] }}</td>
                        <td>{{ $payment['at'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">لا مدفوعات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

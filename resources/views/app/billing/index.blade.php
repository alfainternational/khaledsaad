@extends('layouts.app')

@section('title', 'الأرصدة والخطط')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الأرصدة والخطط</p>
            <h1>رصيدك وخطتك</h1>
        </div>
    </header>

    <section class="stat-row" aria-label="ملخص">
        <article class="stat">
            <span class="stat__value">{{ $balance }}</span>
            <span class="stat__label">رصيد متاح</span>
        </article>
        <article class="stat">
            <span class="stat__value">{{ $project_count }} / {{ $project_limit }}</span>
            <span class="stat__label">المشاريع</span>
        </article>
    </section>

    <section aria-labelledby="plans-heading">
        <h2 id="plans-heading" class="section-title">الخطط</h2>
        <div class="card-grid">
            @foreach ($plans as $plan)
                <article @class(['card', 'card--active' => $plan['is_current']])>
                    <h3>{{ $plan['name'] }}</h3>
                    <p class="score-big">{{ $plan['price'] }}<small> ريال/شهر</small></p>
                    <ul class="bullets">
                        <li>{{ $plan['monthly_credits'] }} رصيد شهريًا</li>
                        <li>{{ $plan['project_limit'] }} مشاريع</li>
                        @foreach ($plan['features'] as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>

                    @if ($plan['is_current'])
                        <p class="badge">خطتك الحالية</p>
                    @else
                        <form method="POST" action="{{ route('app.checkout.plan', $plan['key']) }}">
                            @csrf
                            <button type="submit" class="btn btn--primary btn--sm">
                                {{ $plan['price'] === 0 ? 'التبديل إليها' : 'اشترك — '.$plan['price'].' ريال' }}
                            </button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    @if ($packs !== [])
        <section aria-labelledby="packs-heading">
            <h2 id="packs-heading" class="section-title">حزم الأرصدة</h2>
            <p class="muted">تحتاج رصيدًا إضافيًا دون تغيير خطتك؟ اشترِ حزمة.</p>
            <div class="card-grid">
                @foreach ($packs as $pack)
                    <article class="card">
                        <h3>{{ $pack['name'] }}</h3>
                        <p class="score-big">{{ $pack['credits'] }}<small> رصيد</small></p>
                        <p class="muted">{{ $pack['price'] }} {{ $pack['currency'] }}</p>
                        <form method="POST" action="{{ route('app.checkout.pack', $pack['id']) }}">
                            @csrf
                            <button type="submit" class="btn btn--primary btn--sm">اشترِ الآن</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @unless ($payments_enabled)
        <p class="alert alert--info">الدفع الإلكتروني غير مفعّل حاليًا. تُفعّله الإدارة من لوحة التحكم.</p>
    @endunless

    <section aria-labelledby="ledger-heading">
        <h2 id="ledger-heading" class="section-title">حركات الرصيد</h2>

        @if ($transactions === [])
            <p class="muted">لا حركات بعد.</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>النوع</th><th>المقدار</th><th>الرصيد بعدها</th><th>السبب</th><th>التاريخ</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction['type_label'] }}</td>
                                <td>{{ $transaction['amount'] > 0 ? '+' : '' }}{{ $transaction['amount'] }}</td>
                                <td>{{ $transaction['balance_after'] }}</td>
                                <td>{{ $transaction['reason'] }}</td>
                                <td>{{ $transaction['at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

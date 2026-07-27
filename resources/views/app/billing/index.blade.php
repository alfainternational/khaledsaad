@extends('layouts.app')
@section('layout', 'dashboard')

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

    @if ($subscription_period_end || $scheduled_plan)
        <p class="alert alert--info">
            @if ($subscription_period_end)تنتهي الفترة الحالية في {{ $subscription_period_end }}.@endif
            @if ($scheduled_plan) الخطة المجدولة بعدها: {{ $scheduled_plan }}.@endif
        </p>
    @endif

    <section aria-labelledby="plans-heading">
        <h2 id="plans-heading" class="section-title">الخطط</h2>
        <div class="card-grid">
            @foreach ($plans as $plan)
                <article @class(['card', 'card--active' => $plan['is_current']])>
                    <h3>{{ $plan['name'] }}</h3>
                    <p class="score-big">{{ $plan['price'] }}<small> ريال/شهر</small></p>
                    <ul class="bullets">
                        <li>{{ $plan['monthly_credits'] }} رصيد شهريًا</li>
                        {{-- ما يُعرض هنا هو عناصر الخطة نفسها؛ حد المشاريع أحدها. --}}
                        @forelse ($plan['features'] as $feature)
                            <li>{{ $feature }}</li>
                        @empty
                            <li>{{ $plan['project_limit'] }} مشاريع</li>
                        @endforelse
                    </ul>

                    @if ($plan['is_current'])
                        <p class="badge">خطتك الحالية</p>
                    @else
                        <form method="POST" action="{{ route('app.checkout.plan', $plan['key']) }}">
                            @csrf
                            @if ($plan['price'] > 0 && count($gateways) > 1)
                                <fieldset class="field">
                                    <legend class="field__label">اختر وسيلة الدفع</legend>
                                    @foreach ($gateways as $gateway)
                                        <label class="choice">
                                            <input type="radio" name="gateway_id" value="{{ $gateway['id'] }}" @checked($gateway['is_default']) required>
                                            <span>{{ $gateway['label'] }}</span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @elseif ($plan['price'] > 0 && count($gateways) === 1)
                                <input type="hidden" name="gateway_id" value="{{ $gateways[0]['id'] }}">
                            @endif
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
                            @if (count($gateways) > 1)
                                <fieldset class="field">
                                    <legend class="field__label">اختر وسيلة الدفع</legend>
                                    @foreach ($gateways as $gateway)
                                        <label class="choice">
                                            <input type="radio" name="gateway_id" value="{{ $gateway['id'] }}" @checked($gateway['is_default']) required>
                                            <span>{{ $gateway['label'] }}</span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @elseif (count($gateways) === 1)
                                <input type="hidden" name="gateway_id" value="{{ $gateways[0]['id'] }}">
                            @endif
                            <button type="submit" class="btn btn--primary btn--sm">اشترِ الآن</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($payments_enabled)
        <p class="muted">وسائل الدفع المتاحة: {{ collect($gateways)->pluck('label')->join('، ') }}.</p>
        @foreach ($gateways as $gateway)
            @if ($gateway['instructions'])
                <p class="alert alert--info"><strong>{{ $gateway['label'] }}:</strong> {{ $gateway['instructions'] }}</p>
            @endif
        @endforeach
    @else
        <p class="alert alert--info">الدفع الإلكتروني غير متاح حاليًا. يمكنك العودة لاحقًا أو التواصل للاستفسار عن خيارات الدفع.</p>
    @endif

    <section aria-labelledby="ledger-heading">
        <h2 id="ledger-heading" class="section-title">حركات الرصيد</h2>

        @if ($transactions === [])
            <p class="muted">لا حركات بعد.</p>
        @else
            <div class="table-wrap">
                <table class="table" data-table="entity">
                    <thead>
                        <tr><th>النوع</th><th>المقدار</th><th>الرصيد بعدها</th><th>السبب</th><th>التاريخ</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            <tr>
                                <td data-label="النوع">{{ $transaction['type_label'] }}</td>
                                <td data-label="المقدار">{{ $transaction['amount'] > 0 ? '+' : '' }}{{ $transaction['amount'] }}</td>
                                <td data-label="الرصيد بعدها">{{ $transaction['balance_after'] }}</td>
                                <td data-label="السبب">{{ $transaction['reason'] }}</td>
                                <td data-label="التاريخ">{{ $transaction['at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="payments-heading">
        <h2 id="payments-heading" class="section-title">سجل المدفوعات</h2>
        @if ($payments === [])
            <p class="muted">لا توجد مدفوعات بعد.</p>
        @else
            <div class="table-wrap"><table class="table" data-table="entity">
                <thead><tr><th>#</th><th>الغرض</th><th>وسيلة الدفع</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                <tbody>@foreach ($payments as $payment)<tr>
                    <td data-label="#">{{ $payment['id'] }}</td><td data-label="الغرض">{{ $payment['purpose'] }}</td><td data-label="وسيلة الدفع">{{ $payment['provider'] }}</td>
                    <td data-label="المبلغ">{{ $payment['amount'] }} @if($payment['refunded'] > 0)<small>· مسترد {{ $payment['refunded'] }}</small>@endif</td>
                    <td data-label="الحالة">{{ $payment['status'] }}</td><td data-label="التاريخ">{{ $payment['at'] }}</td>
                </tr>@endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection

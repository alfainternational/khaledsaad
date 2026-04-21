@extends('layouts.admin', ['title' => 'الخطط', 'pageTitle' => 'إدارة الخطط', 'pageKicker' => 'Plans'])

@section('content')
<div class="admin-help mb-6">
    <p><strong>PayPal:</strong> اربط كل باقة مدفوعة بمعرّف Billing Plan (<code>P-…</code>) من حقلي التعديل، أو عبّئ <code>PAYPAL_PLAN_<em>CODE</em>_MONTHLY</code> / <code>_ANNUAL</code> في <code>.env</code>. باقتا <code>starter</code> و <code>team</code> تستخدمان تلقائياً معرّفات <code>PRO</code> إن تركت حقولهما فارغة. باقة <code>agency</code> تقبل أيضاً <code>PAYPAL_PLAN_ENT_*</code>. لسرد المعرفات من API: <code>php artisan paypal:list-plans</code>.</p>
</div>
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>الخطط الحالية</h2>
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary btn-lg">خطة جديدة</a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>الكود</th>
                    <th>الاسم</th>
                    <th>السعر الشهري</th>
                    <th>الحالة</th>
                    <th>اشتراكات</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td>{{ $plan->code }}</td>
                        <td>
                            <strong>{{ $plan->name_ar }}</strong>
                            <small>{{ $plan->name_en }}</small>
                        </td>
                        <td>{{ number_format((float) $plan->monthly_price, 2) }}</td>
                        <td>{{ $plan->status->value }}</td>
                        <td>{{ $plan->subscriptions_count }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا توجد خطط بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-pagination">
        {{ $plans->links() }}
    </div>
</section>
@endsection

@extends('layouts.app')
@section('layout', 'index')

@section('title', 'بوابات الدفع')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>بوابات الدفع</h1>
            <p class="muted">يمكن تفعيل عدة بوابات معًا واختيار بوابة افتراضية. المفاتيح مشفّرة ولا تُعرض بعد الحفظ.</p>
        </div>
        <a href="{{ route('admin.gateways.create') }}" class="btn btn--primary">بوابة جديدة</a>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>البوابة</th><th>المزوّد</th><th>الوضع</th><th>الربط</th><th>الفحص</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($gateways as $gateway)
                    <tr>
                        <td>{{ $gateway->label }}</td>
                        <td>{{ $gateway->provider }}</td>
                        <td>{{ $gateway->mode === 'live' ? 'مباشر' : 'اختبار' }}</td>
                        <td>{{ $gateway->hasRequiredCredentials() ? 'مكتمل' : 'ناقص' }}</td>
                        <td>
                            {{ $gateway->isHealthy() ? 'سليم' : ($gateway->health_status === 'unhealthy' ? 'فشل' : 'لم يُختبر') }}
                            @if ($gateway->last_health_message)<small class="muted">{{ $gateway->last_health_message }}</small>@endif
                        </td>
                        <td>
                            <span @class(['badge', 'badge--assumption' => ! $gateway->is_active])>
                                {{ $gateway->is_active ? ($gateway->is_default ? 'مفعّلة · افتراضية' : 'مفعّلة') : 'معطّلة' }}
                            </span>
                        </td>
                        <td class="table__actions">
                            <form method="POST" action="{{ route('admin.gateways.test', $gateway) }}">
                                @csrf
                                <button type="submit" class="btn btn--ghost btn--sm">اختبار الربط</button>
                            </form>
                            <form method="POST" action="{{ route('admin.gateways.toggle', $gateway) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn--ghost btn--sm">{{ $gateway->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                            </form>
                            @if ($gateway->is_active && ! $gateway->is_default)
                                <form method="POST" action="{{ route('admin.gateways.default', $gateway) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn--ghost btn--sm">اجعلها الافتراضية</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.gateways.edit', $gateway) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.gateways.destroy', $gateway) }}" onsubmit="return confirm('حذف هذه البوابة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn--ghost btn--sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">لا بوابات بعد. أضف بوابة واحفظ بيانات الربط ثم اختبرها وفعّلها.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

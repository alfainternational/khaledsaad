@extends('layouts.app')

@section('title', 'بوابات الدفع')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>بوابات الدفع</h1>
            <p class="muted">بوابة واحدة مفعّلة في كل وقت. المفاتيح مشفّرة ولا تُعرض بعد الحفظ.</p>
        </div>
        <a href="{{ route('admin.gateways.create') }}" class="btn btn--primary">بوابة جديدة</a>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>البوابة</th><th>المزوّد</th><th>الوضع</th><th>مهيأة</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($gateways as $gateway)
                    <tr>
                        <td>{{ $gateway->label }}</td>
                        <td>{{ $gateway->provider }}</td>
                        <td>{{ $gateway->mode === 'live' ? 'مباشر' : 'اختبار' }}</td>
                        <td>{{ $gateway->isConfigured() || $gateway->provider === 'manual' ? 'نعم' : 'ناقصة مفاتيح' }}</td>
                        <td>
                            <span @class(['badge', 'badge--assumption' => ! $gateway->is_active])>
                                {{ $gateway->is_active ? 'مفعّلة' : 'معطّلة' }}
                            </span>
                        </td>
                        <td class="table__actions">
                            <form method="POST" action="{{ route('admin.gateways.toggle', $gateway) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn--ghost btn--sm">{{ $gateway->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                            </form>
                            <a href="{{ route('admin.gateways.edit', $gateway) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.gateways.destroy', $gateway) }}" onsubmit="return confirm('حذف هذه البوابة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn--ghost btn--sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا بوابات بعد. أضف بوابة (PayPal مثلاً) وفعّلها.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

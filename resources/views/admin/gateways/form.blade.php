@extends('layouts.app')

@section('title', $gateway->exists ? 'تعديل بوابة' : 'بوابة جديدة')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · بوابات الدفع</p>
            <h1>{{ $gateway->exists ? 'تعديل: '.$gateway->label : 'بوابة جديدة' }}</h1>
        </div>
        <a href="{{ route('admin.gateways.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ $gateway->exists ? route('admin.gateways.update', $gateway) : route('admin.gateways.store') }}" class="form form--wide">
        @csrf
        @if ($gateway->exists) @method('PUT') @endif

        @unless ($gateway->exists)
            <label class="field">
                <span class="field__label">المزوّد</span>
                <select name="provider" id="provider-select" required>
                    @foreach ($catalogue as $key => $meta)
                        <option value="{{ $key }}" @selected(old('provider') === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endunless

        <label class="field">
            <span class="field__label">الاسم الظاهر</span>
            <input type="text" name="label" value="{{ old('label', $gateway->label) }}" required>
        </label>

        <label class="field">
            <span class="field__label">الوضع</span>
            <select name="mode">
                <option value="test" @selected(old('mode', $gateway->mode) === 'test')>اختبار (Sandbox)</option>
                <option value="live" @selected(old('mode', $gateway->mode) === 'live')>مباشر (Live)</option>
            </select>
        </label>

        @if ($gateway->exists && ! empty($catalogue[$gateway->provider]['fields']))
            <fieldset class="field">
                <legend class="field__label">مفاتيح {{ $catalogue[$gateway->provider]['label'] }}</legend>
                <p class="field__help">اتركها فارغة للإبقاء على المفاتيح الحالية (لا تُعرض بعد الحفظ لأمانها).</p>
                @foreach ($catalogue[$gateway->provider]['fields'] as $credKey)
                    <label class="field">
                        <span class="field__label">{{ $credKey }}</span>
                        <input type="password" name="credentials[{{ $credKey }}]" autocomplete="off"
                            placeholder="{{ $gateway->credential($credKey) ? '•••••• (محفوظ)' : 'غير مضبوط' }}">
                    </label>
                @endforeach
            </fieldset>
        @endif

        <button type="submit" class="btn btn--primary">{{ $gateway->exists ? 'حفظ' : 'إنشاء ثم إضافة المفاتيح' }}</button>
    </form>
@endsection

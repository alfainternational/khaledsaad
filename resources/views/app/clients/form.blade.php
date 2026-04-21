@extends('layouts.app', ['title' => $client->exists ? 'تعديل العميل' : 'إضافة عميل', 'pageTitle' => $client->exists ? 'تعديل العميل' : 'إضافة عميل', 'pageKicker' => 'Clients'])

@section('content')
<form method="POST" action="{{ $action }}" class="app-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="card">
        <div class="app-form-grid cols-2">
            <label class="app-field">
                <span>اسم العميل</span>
                <input class="app-input" name="name" value="{{ old('name', $client->name) }}">
            </label>
            <label class="app-field">
                <span>البريد</span>
                <input class="app-input" name="email" value="{{ old('email', $client->contact_info['email'] ?? null) }}">
            </label>
            <label class="app-field">
                <span>الهاتف</span>
                <input class="app-input" name="phone" value="{{ old('phone', $client->contact_info['phone'] ?? null) }}">
            </label>
            <label class="app-field">
                <span>الشركة</span>
                <input class="app-input" name="company" value="{{ old('company', $client->contact_info['company'] ?? null) }}">
            </label>
            <label class="app-field">
                <span>الحالة</span>
                <select class="app-input" name="status">
                    @foreach (['active', 'lead', 'inactive', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $client->status ?: 'active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field cols-span-2">
                <span>ملاحظات</span>
                <textarea class="app-input" name="notes" rows="4">{{ old('notes', $client->contact_info['notes'] ?? null) }}</textarea>
            </label>
        </div>
    </section>

    <div class="app-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $client->exists ? 'حفظ التعديلات' : 'إنشاء العميل' }}</button>
        <a href="{{ route('clients.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection

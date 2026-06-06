@extends('layouts.admin', ['title' => 'تعديل العميل', 'pageTitle' => 'تعديل: ' . $client->name, 'pageKicker' => 'العملاء'])

@section('content')
<section class="admin-panel panel-modern" style="max-width:640px">
    <div class="admin-panel-head"><h2>تعديل العميل</h2></div>
    <form method="POST" action="{{ $action }}" class="admin-form">
        @csrf @method($method)

        <label class="admin-field">
            <span>الاسم</span>
            <input type="text" name="name" value="{{ old('name', $client->name) }}" class="admin-input" required>
            @error('name') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <label class="admin-field">
            <span>البريد الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email', data_get($client->contact_info, 'email')) }}" class="admin-input">
            @error('email') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <label class="admin-field">
            <span>الهاتف</span>
            <input type="text" name="phone" value="{{ old('phone', data_get($client->contact_info, 'phone')) }}" class="admin-input">
            @error('phone') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <label class="admin-field">
            <span>الشركة</span>
            <input type="text" name="company" value="{{ old('company', data_get($client->contact_info, 'company')) }}" class="admin-input">
            @error('company') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <label class="admin-field">
            <span>ملاحظات</span>
            <textarea name="notes" class="admin-input" rows="3">{{ old('notes', data_get($client->contact_info, 'notes')) }}</textarea>
            @error('notes') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <label class="admin-field">
            <span>الحالة</span>
            <select name="status" class="admin-input" required>
                @foreach (['active', 'archived'] as $s)
                    <option value="{{ $s }}" @selected(old('status', $client->status) === $s)>{{ $s }}</option>
                @endforeach
            </select>
            @error('status') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</section>
@endsection

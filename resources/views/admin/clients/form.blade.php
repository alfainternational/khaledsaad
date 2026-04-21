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
            <span>معلومات التواصل</span>
            <textarea name="contact_info" class="admin-input" rows="3">{{ old('contact_info', $client->contact_info) }}</textarea>
            @error('contact_info') <small class="admin-error">{{ $message }}</small> @enderror
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

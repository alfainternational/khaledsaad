@extends('layouts.admin', ['title' => 'شريك', 'pageTitle' => $partner->exists ? 'تعديل شريك' : 'شريك جديد', 'pageKicker' => 'Partners'])

@section('content')
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head"><h2>البيانات</h2></div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>الاسم</span>
                <input class="admin-input" name="name" value="{{ old('name', $partner->name) }}" required maxlength="200">
            </label>
            <label class="admin-field">
                <span>شعار (صورة)</span>
                <input class="admin-input" type="file" name="logo" accept="image/*">
                @if($partner->logo_path)
                    <small class="block mt-1"><img src="{{ asset('storage/'.$partner->logo_path) }}" alt="" style="max-height:48px"></small>
                @endif
            </label>
            <label class="admin-field">
                <span>ترتيب</span>
                <input class="admin-input" type="number" name="sort_order" value="{{ old('sort_order', $partner->sort_order) }}" min="0">
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>وصف</span>
                <textarea class="admin-input" name="description" rows="4" maxlength="2000">{{ old('description', $partner->description) }}</textarea>
            </label>
            <label class="admin-field" style="grid-column: 1 / -1;">
                <span>رابط الموقع</span>
                <input class="admin-input" name="website_url" value="{{ old('website_url', $partner->website_url) }}" maxlength="500" placeholder="https://">
            </label>
            <label class="admin-field">
                <span><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $partner->is_published))> منشور</span>
            </label>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg">حفظ</button>
            <a href="{{ route('admin.partners.index') }}" class="btn btn-ghost btn-lg">إلغاء</a>
        </div>
    </section>
</form>
@endsection

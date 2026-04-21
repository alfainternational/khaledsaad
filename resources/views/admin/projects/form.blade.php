@extends('layouts.admin', ['title' => 'تعديل المشروع', 'pageTitle' => 'تعديل: ' . $project->name, 'pageKicker' => 'المشاريع'])

@section('content')
<section class="admin-panel panel-modern" style="max-width:640px">
    <div class="admin-panel-head"><h2>تعديل المشروع</h2></div>
    <form method="POST" action="{{ $action }}" class="admin-form">
        @csrf @method($method)

        <label class="admin-field">
            <span>اسم المشروع</span>
            <input type="text" name="name" value="{{ old('name', $project->name) }}" class="admin-input" required>
            @error('name') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <label class="admin-field">
            <span>المرحلة</span>
            <select name="stage" class="admin-input" required>
                @for ($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}" @selected(old('stage', $project->stage) == $i)>المرحلة {{ $i }}</option>
                @endfor
            </select>
            @error('stage') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <label class="admin-field">
            <span>الحالة</span>
            <select name="status" class="admin-input" required>
                @foreach (['active', 'paused', 'completed', 'archived'] as $s)
                    <option value="{{ $s }}" @selected(old('status', $project->status) === $s)>{{ $s }}</option>
                @endforeach
            </select>
            @error('status') <small class="admin-error">{{ $message }}</small> @enderror
        </label>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            <a href="{{ route('admin.projects.show', $project) }}" class="btn btn-secondary">إلغاء</a>
        </div>
    </form>
</section>
@endsection

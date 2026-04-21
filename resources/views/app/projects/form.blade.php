@extends('layouts.app', ['title' => $project->exists ? 'تعديل المشروع' : 'مشروع جديد', 'pageTitle' => $project->exists ? 'تعديل المشروع' : 'مشروع جديد', 'pageKicker' => 'Projects'])

@section('content')
<form method="POST" action="{{ $action }}" class="app-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="card">
        <div class="app-form-grid cols-2">
            <label class="app-field">
                <span>اسم المشروع</span>
                <input class="app-input" name="name" value="{{ old('name', $project->name) }}">
            </label>
            <label class="app-field">
                <span>العميل المرتبط</span>
                <select class="app-input" name="client_id">
                    <option value="">بدون عميل</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id', $project->client_id) == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>المرحلة الحالية</span>
                <select class="app-input" name="stage">
                    @foreach ([1, 2, 3, 4, 5] as $stage)
                        <option value="{{ $stage }}" @selected((int) old('stage', $project->stage ?: 1) === $stage)>المرحلة {{ $stage }}</option>
                    @endforeach
                </select>
            </label>
            <label class="app-field">
                <span>الحالة</span>
                <select class="app-input" name="status">
                    @foreach (['active', 'paused', 'completed', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $project->status ?: 'active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <div class="app-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $project->exists ? 'حفظ التعديلات' : 'إنشاء المشروع' }}</button>
        <a href="{{ route('projects.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection

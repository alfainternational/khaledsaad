@extends('layouts.admin', ['title' => 'مساحة العمل', 'pageTitle' => $workspace->exists ? 'تعديل مساحة العمل' : 'إنشاء مساحة عمل', 'pageKicker' => 'Workspaces'])

@section('content')
@php
    $defaultOwnerId = $workspace->exists ? $workspace->members->firstWhere('role', 'owner')?->user_id : null;
@endphp

<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>بيانات مساحة العمل</h2>
        </div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>اسم المساحة</span>
                <input class="admin-input" name="name" value="{{ old('name', $workspace->name) }}">
            </label>
            <label class="admin-field">
                <span>الحساب</span>
                <select class="admin-input" name="account_id">
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('account_id', $workspace->account_id) === $account->id)>{{ $account->name }} - {{ $account->owner?->email ?? '—' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>النوع</span>
                <select class="admin-input" name="type">
                    @foreach (['personal', 'team', 'agency'] as $type)
                        <option value="{{ $type }}" @selected(old('type', $workspace->type ?? 'personal') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>الحالة</span>
                <select class="admin-input" name="status">
                    @foreach (['active', 'paused', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $workspace->status ?? 'active') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field cols-span-2">
                <span>مالك المساحة</span>
                <select class="admin-input" name="owner_user_id">
                    <option value="">استخدم مالك الحساب</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('owner_user_id', $defaultOwnerId) === $user->id)>{{ $user->name }} - {{ $user->email }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $workspace->exists ? 'حفظ التعديلات' : 'إنشاء مساحة العمل' }}</button>
        <a href="{{ $workspace->exists ? route('admin.workspaces.show', $workspace) : route('admin.workspaces.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection

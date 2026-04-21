@extends('layouts.admin', ['title' => 'Feature Flag', 'pageTitle' => $featureFlag->exists ? 'تعديل Feature Flag' : 'إنشاء Feature Flag', 'pageKicker' => 'Flags'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>بيانات الفلاج</h2>
        </div>
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>المفتاح</span>
                <input class="admin-input" name="key" value="{{ old('key', $featureFlag->key) }}">
            </label>
            <label class="admin-field">
                <span>الاسم</span>
                <input class="admin-input" name="name" value="{{ old('name', $featureFlag->name) }}">
            </label>
            <label class="admin-field cols-span-2">
                <span>الوصف</span>
                <textarea class="admin-input" name="description" rows="4">{{ old('description', $featureFlag->description) }}</textarea>
            </label>
            <label class="admin-field">
                <span>الموديول</span>
                <select class="admin-input" name="module">
                    <option value="">بدون ربط</option>
                    @foreach ($modules as $key => $module)
                        <option value="{{ $key }}" @selected(old('module', $featureFlag->module) === $key)>{{ $module['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>الحالة</span>
                <select class="admin-input" name="status">
                    @foreach (['off', 'beta', 'on'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $featureFlag->status?->value ?? $featureFlag->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>Rollout %</span>
                <input class="admin-input" type="number" min="0" max="100" name="rollout_percentage" value="{{ old('rollout_percentage', $featureFlag->rollout_percentage ?? 100) }}">
            </label>
            <label class="admin-field">
                <span>تاريخ الانتهاء</span>
                <input class="admin-input" type="datetime-local" name="expires_at" value="{{ old('expires_at', optional($featureFlag->expires_at)->format('Y-m-d\TH:i')) }}">
            </label>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>Audiences</h2>
            <button type="button" class="btn btn-secondary btn-sm" data-dynamic-list-add="flag-audiences" data-template-id="flag-audiences-template">إضافة Audience</button>
        </div>

        @php
            $rows = old('audiences', collect($audiences)->map(fn ($item) => [
                'audience_type' => $item->audience_type,
                'audience_id' => $item->audience_id,
            ])->all() ?: [['audience_type' => 'plan', 'audience_id' => '']]);
        @endphp
        <div class="admin-dynamic-list" id="flag-audiences" data-next-index="{{ count($rows) }}">
            @foreach ($rows as $index => $row)
                <div class="admin-dynamic-row">
                    <select class="admin-input" name="audiences[{{ $index }}][audience_type]">
                        @foreach (['plan', 'workspace', 'user'] as $type)
                            <option value="{{ $type }}" @selected(($row['audience_type'] ?? 'plan') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    <input class="admin-input" name="audiences[{{ $index }}][audience_id]" value="{{ $row['audience_id'] }}">
                    <button type="button" class="btn btn-ghost btn-sm" data-dynamic-remove>حذف</button>
                </div>
            @endforeach
        </div>

        <template id="flag-audiences-template">
            <div class="admin-dynamic-row">
                <select class="admin-input" name="audiences[__INDEX__][audience_type]">
                    <option value="plan">plan</option>
                    <option value="workspace">workspace</option>
                    <option value="user">user</option>
                </select>
                <input class="admin-input" name="audiences[__INDEX__][audience_id]" value="">
                <button type="button" class="btn btn-ghost btn-sm" data-dynamic-remove>حذف</button>
            </div>
        </template>

        <div class="admin-help mt-4">
            <p>Plans: {{ $plans->pluck('id', 'code')->map(fn ($id, $code) => $code.':'.$id)->implode(' | ') }}</p>
            <p>Workspaces: {{ $workspaces->pluck('id', 'name')->map(fn ($id, $name) => $name.':'.$id)->implode(' | ') ?: 'لا يوجد' }}</p>
            <p>Users: {{ $users->pluck('id', 'email')->map(fn ($id, $email) => $email.':'.$id)->implode(' | ') ?: 'لا يوجد' }}</p>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $featureFlag->exists ? 'حفظ التعديلات' : 'إنشاء الفلاج' }}</button>
        <a href="{{ route('admin.feature-flags.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection

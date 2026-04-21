@extends('layouts.admin', ['title' => 'قالب AI', 'pageTitle' => $aiTemplate->exists ? 'تعديل قالب AI' : 'إنشاء قالب AI', 'pageKicker' => 'AI Templates'])

@section('content')
<form method="POST" action="{{ $action }}" class="admin-form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <section class="admin-panel">
        <div class="admin-form-grid cols-2">
            <label class="admin-field">
                <span>الكود</span>
                <input class="admin-input" name="code" value="{{ old('code', $aiTemplate->code) }}">
            </label>
            <label class="admin-field">
                <span>الاسم</span>
                <input class="admin-input" name="name" value="{{ old('name', $aiTemplate->name) }}">
            </label>
            <label class="admin-field cols-span-2">
                <span>الوصف</span>
                <textarea class="admin-input" name="description" rows="3">{{ old('description', $aiTemplate->description) }}</textarea>
            </label>
            <label class="admin-field cols-span-2">
                <span>المجال الفرعي (domain)</span>
                <input class="admin-input" name="domain" value="{{ old('domain', $aiTemplate->domain) }}" placeholder="مثال: تشخيص استراتيجي">
            </label>
            <label class="admin-field cols-span-2">
                <span>دور النظام (system_role)</span>
                <textarea class="admin-input" name="system_role" rows="4" placeholder="صف دور النموذج بجمل بسيطة (مثال: يكتب بلغة مباشرة ويقدّم نقاطاً قابلة للتنفيذ)">{{ old('system_role', $aiTemplate->system_role) }}</textarea>
            </label>
            <label class="admin-field cols-span-2">
                <span>عقد المخرج JSON (output_contract_json)</span>
                <textarea class="admin-input" name="output_contract_json" rows="5" placeholder='{"sections":["قسم 1"],"quality_rubric":"..."}'>{{ old('output_contract_json', $aiTemplate->output_contract_json ? json_encode($aiTemplate->output_contract_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '') }}</textarea>
            </label>
            <label class="admin-field cols-span-2">
                <span>Prompt Template</span>
                <textarea class="admin-input" name="prompt_template" rows="8">{{ old('prompt_template', $aiTemplate->prompt_template) }}</textarea>
            </label>
            <label class="admin-field">
                <span>الموديل</span>
                <input class="admin-input" name="model" value="{{ old('model', $aiTemplate->model) }}">
            </label>
            <label class="admin-field">
                <span>الاعتمادات</span>
                <input class="admin-input" type="number" name="credit_cost" value="{{ old('credit_cost', $aiTemplate->credit_cost ?? 0) }}">
            </label>
            <label class="admin-field">
                <span>الحالة</span>
                <select class="admin-input" name="status">
                    @foreach (['draft', 'published', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $aiTemplate->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="admin-field">
                <span>الموديول</span>
                <select class="admin-input" name="module">
                    <option value="">بدون ربط</option>
                    @foreach ($modules as $key => $module)
                        <option value="{{ $key }}" @selected(old('module', $aiTemplate->module) === $key)>{{ $module['name'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <div class="admin-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">{{ $aiTemplate->exists ? 'حفظ التعديلات' : 'إنشاء القالب' }}</button>
        <a href="{{ route('admin.ai-templates.index') }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection

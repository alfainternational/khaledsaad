@extends('layouts.app')
@section('layout', 'detail')

@section('title', $tool->title)

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · {{ $tool->category }}</p>
            <h1>{{ $tool->title }}</h1>
            <p class="muted">{{ $tool->key }} · الحالة: {{ $tool->status === 'published' ? 'منشورة' : 'قريبًا' }}</p>
        </div>
        <a href="{{ route('admin.tools.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    @if ($version === null)
        <p class="alert alert--info">لا يوجد إصدار منشور لهذه الأداة بعد.</p>
    @else
        <section aria-labelledby="fields-heading">
            <h2 id="fields-heading" class="section-title">الحقول ({{ $fields->count() }})</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>المفتاح</th><th>السؤال</th><th>النوع</th><th>الخطوة</th><th>مطلوب</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($fields as $field)
                            <tr>
                                <td>{{ $field->key }}</td>
                                <td>{{ $field->label }}</td>
                                <td>{{ $field->type }}</td>
                                <td>{{ $field->step }}</td>
                                <td>{{ $field->required ? 'نعم' : 'لا' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="prompts-heading">
            <h2 id="prompts-heading" class="section-title">البرومبتات ({{ $prompts->count() }})</h2>
            <p class="muted">البرومبت يُقفل بعد أول استخدام (BR-012). التعديل ينشئ إصدارًا جديدًا.</p>

            @foreach ($prompts as $prompt)
                <details class="report-section">
                    <summary>{{ $prompt->stage }} · {{ $prompt->tier }} {{ $prompt->locked_at ? '· مقفل' : '' }}</summary>

                    @if ($prompt->locked_at)
                        <p class="muted">هذا البرومبت مقفل بعد الاستخدام (BR-012) ولا يُعدّل.</p>
                        <pre style="white-space: pre-wrap; font-size: 0.85rem;">{{ $prompt->content }}</pre>
                    @else
                        <form method="POST" action="{{ route('admin.tools.prompts.update', [$tool->key, $prompt->id]) }}" class="form">
                            @csrf @method('PUT')
                            <label class="field">
                                <span class="field__label">الفئة</span>
                                <select name="tier">
                                    @foreach (['economy' => 'اقتصاد', 'standard' => 'قياسي', 'advanced' => 'متقدّم'] as $value => $label)
                                        <option value="{{ $value }}" @selected($prompt->tier === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field">
                                <span class="field__label">النص</span>
                                <textarea name="content" rows="8" required>{{ $prompt->content }}</textarea>
                            </label>
                            <button type="submit" class="btn btn--primary btn--sm">حفظ البرومبت</button>
                        </form>
                    @endif
                </details>
            @endforeach
        </section>
    @endif
@endsection

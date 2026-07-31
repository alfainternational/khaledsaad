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

    {{-- بند ١١: تاريخ الإصدارات وسكّ إصدار جديد من اللوحة --}}
    <section aria-labelledby="versions-heading">
        <h2 id="versions-heading" class="section-title">الإصدارات ({{ $versions->count() }})</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>الإصدار</th><th>البرومبتات</th><th>المقفلة</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    @foreach ($versions as $item)
                        <tr>
                            <td>v{{ $item->version }}</td>
                            <td>{{ $item->prompts_count }}</td>
                            <td>{{ $item->locked_prompts_count }}</td>
                            <td>{{ $item->id === $tool->current_version_id ? 'الفعّال الآن' : 'أثر محفوظ' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.tools.release', $tool->key) }}"
            data-confirm="سيصدر إصدار جديد ببرومبتات غير مقفلة ويصير هو الفعّال، والقديم يبقى أثرًا. متأكد؟">
            @csrf
            <button type="submit" class="btn btn--primary">سكّ إصدار جديد (BR-012)</button>
            <p class="muted">تعديل برومبت مقفل لا يتم بصمت — الإصدار الجديد ينشأ من ملف التعريف ببرومبتات قابلة للتحرير حتى أول استخدام.</p>
        </form>
    </section>

    {{-- بند ٢٨: محاكي التكيف — ما يراه كل نوع مشروع من أسئلة هذه الأداة --}}
    <section aria-labelledby="simulation-heading">
        <h2 id="simulation-heading" class="section-title">محاكي التكيف: من يرى ماذا</h2>
        <p class="muted">نفس بروفايلات مدقّق التكيف السبعة. القاعدة: لا نوع مشروع بلا أسئلة، ولا أقل من ثلاث قواعد تقييم فعّالة.</p>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>نوع المشروع</th><th>الأسئلة</th><th>الإلزامية</th><th>قواعد التقييم الفعّالة</th></tr>
                </thead>
                <tbody>
                    @foreach ($simulation as $profile => $result)
                        <tr @class(['is-problem' => $result['questions'] === 0 || $result['scored'] < 3])>
                            <td>{{ $profile }}</td>
                            <td>{{ $result['questions'] }}</td>
                            <td>{{ $result['required'] }}</td>
                            <td>{{ $result['scored'] }}{{ $result['scored'] < 3 ? ' ⚠ الدرجة تفقد معناها' : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- بند ٢٩: ساحة المعاينة — الرسائل الفعلية كما ستصل النموذج، بلا استدعاء --}}
    @if ($preview !== null)
        <section aria-labelledby="preview-heading">
            <h2 id="preview-heading" class="section-title">معاينة ما يستلمه النموذج</h2>
            <details class="report-section">
                <summary>رسائل مرحلة التركيب كاملة (system + برومبت الأداة + مدخل المثال الذهبي)</summary>
                <pre class="prompt-preview" dir="rtl">{{ $preview }}</pre>
            </details>
            <p class="muted">راجعها قبل سكّ أي إصدار — هذا بالضبط ما سيصل النموذج، بلا تكلفة استدعاء.</p>
        </section>
    @endif
@endsection

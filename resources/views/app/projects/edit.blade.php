@extends('layouts.app')
@section('layout', 'form')

@section('title', 'معلومات مشروعك')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">معلومات مشروعك</p>
            <h1>{{ $project->name }}</h1>
            <p class="muted">
                هذه المعلومات تُستخدم في كل خطوة، فلا نسألك عنها مرة أخرى.
                عدّلها متى ما تغيّر شيء — والنتائج التي استلمتها سابقًا تبقى كما هي.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ route('app.projects.update', $project) }}" class="form form--wide form-layout">
        @csrf
        @method('PUT')

        <label class="field">
            <span class="field__label">ما اسم مشروعك؟</span>
            <span class="field__help">اكتب الاسم الذي يعرف به العملاء مشروعك.</span>
            <input type="text" name="name" value="{{ old('name', $project->name) }}" required maxlength="120">
            <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن الاسم يميز مشروعك داخل المنصة، ويظهر في التقارير والملفات التي تنشئها.</p></details>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">في أي مجال يعمل مشروعك؟</span>
                <span class="field__help">اكتب المجال بكلمة أو كلمتين، مثل: تعليم، تجزئة، أو خدمات منزلية.</span>
                <input type="text" name="industry" value="{{ old('industry', $project->industry) }}" maxlength="120" placeholder="تعليم، تجزئة، خدمات…">
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن طبيعة السوق والعملاء والمنافسة تختلف من مجال إلى آخر، وهذا يجعل التشخيص أقرب إلى واقعك.</p></details>
            </label>

            <label class="field">
                <span class="field__label">إلى أي مرحلة وصل مشروعك الآن؟</span>
                <span class="field__help">اختر المرحلة التي تصف الواقع الحالي، لا المرحلة التي تريد الوصول إليها.</span>
                <select name="stage">
                    @foreach (['idea' => 'فكرة قيد الدراسة', 'launch' => 'بدأ المشروع حديثًا', 'growth' => 'يحقق مبيعات حاليًا', 'scale' => 'يحقق مبيعات ويستعد للتوسع'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('stage', $project->stage) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن المشروع في مرحلة الفكرة يحتاج أسئلة مختلفة عن مشروع يبيع أو يستعد للتوسع.</p></details>
            </label>
        </div>

        <label class="field">
            <span class="field__label">ماذا تبيع بالضبط؟</span>
            <span class="field__help">اكتب وصفًا مباشرًا يفهمه شخص يتعرف إلى مشروعك للمرة الأولى.</span>
            <textarea name="description" rows="3" maxlength="2000">{{ old('description', $project->profile?->description) }}</textarea>
            <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن وصف ما تبيعه يحدد أي أسئلة تناسب عرضك، ويمنع بناء التقرير على افتراض غير صحيح.</p></details>
        </label>

        <label class="field">
            <span class="field__label">لماذا يشتري منك العميل بدل غيرك؟</span>
            <span class="field__help">اكتب السبب الحقيقي بجملة أو جملتين. مثال: أوصّل في اليوم نفسه بينما يحتاج غيري إلى ثلاثة أيام.</span>
            <textarea name="value_proposition" rows="3" maxlength="1000">{{ old('value_proposition', $project->profile?->value_proposition) }}</textarea>
            <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن سبب الاختيار يوضح ما إذا كان عرضك يمنح العميل فرقًا يفهمه، أم يحتاج إلى صياغة أو دليل أوضح.</p></details>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">أين يوجد عملاؤك؟</span>
                <span class="field__help">اكتب المدينة أو الدولة أو المنطقة التي تستهدفها الآن.</span>
                <input type="text" name="geography" value="{{ old('geography', $project->profile?->geography) }}" maxlength="120" placeholder="الرياض · السودان · الخليج">
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن السوق المحلي يغيّر المنافسين والقنوات واللغة التي تصل إلى العميل.</p></details>
            </label>

            <label class="field">
                <span class="field__label">كم تصرف على التسويق شهريًا؟</span>
                <span class="field__help">اكتب المبلغ التقريبي بالريال، واتركه فارغًا إذا لم تبدأ الإنفاق بعد.</span>
                <input type="number" name="monthly_budget" value="{{ old('monthly_budget', $project->profile?->monthly_budget) }}" min="0">
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن حجم الإنفاق يحدد ما يمكن قياسه وتنفيذه الآن، ويمنع اقتراح خطوات لا تناسب ميزانيتك.</p></details>
            </label>
        </div>

        <button type="submit" class="btn btn--primary">احفظ التعديلات</button>
    </form>

    @if (($known ?? []) !== [])
        {{-- كل ما كتبه المستخدم داخل الخطوات يظهر هنا في مكان واحد، لا يضيع داخل أداة. --}}
        <section class="card known-summary">
            <h2 class="section-title">معلومات محفوظة من تشخيصاتك</h2>
            <p class="muted">ستُستخدم هذه الإجابات في الخطوات المناسبة حتى لا تحتاج إلى إدخالها مرة أخرى.</p>

            <ul class="kv">
                @foreach ($known as $item)
                    <li>
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ is_array($item['value']) ? implode('، ', $item['value']) : $item['value'] }}</strong>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection

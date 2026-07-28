@extends('layouts.app')
@section('layout', 'form')

@section('title', 'مشروع جديد')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">إضافة مشروع</p>
            <h1>ابدأ بالمعلومات الأساسية</h1>
            <p class="muted">أدخلها مرة واحدة لتخصيص الأسئلة والتقارير، ويمكنك تعديلها لاحقًا من ملف المشروع.</p>
        </div>
    </header>

    @if (($startTool ?? null) !== null)
        <p class="alert alert--info" role="status">
            الخطوة التالية بعد الحفظ: <strong>{{ $startTool['title'] }}</strong> — تُفتح تلقائيًا على هذا المشروع.
        </p>
    @endif

    <form method="POST" action="{{ route('app.projects.store') }}" class="form form--wide form-layout">
        @csrf

        @if (($startTool ?? null) !== null)
            <input type="hidden" name="start_tool" value="{{ $startTool['key'] }}">
        @endif

        <label class="field">
            <span class="field__label">ما اسم مشروعك؟</span>
            <span class="field__help">اكتب الاسم الذي يعرف به العملاء مشروعك، أو اسمًا مؤقتًا إذا كان المشروع ما زال فكرة.</span>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus maxlength="120">
            <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن الاسم يميز مشروعك داخل المنصة، ويظهر في التقارير والملفات التي تنشئها.</p></details>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">في أي مجال يعمل مشروعك؟</span>
                <span class="field__help">اكتب المجال بكلمة أو كلمتين، مثل: تعليم، تجزئة، أو خدمات منزلية.</span>
                <input type="text" name="industry" value="{{ old('industry') }}" maxlength="120" placeholder="تعليم، تجزئة، خدمات…">
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن طبيعة السوق والعملاء والمنافسة تختلف من مجال إلى آخر، وهذا يساعدنا على جعل الأسئلة والتقرير أقرب إلى واقعك.</p></details>
            </label>

            <label class="field">
                <span class="field__label">إلى أي مرحلة وصل مشروعك؟</span>
                <span class="field__help">اختر المرحلة التي تصف وضع المشروع الآن، لا المرحلة التي تريد الوصول إليها.</span>
                <select name="stage">
                    @foreach (['idea' => 'فكرة قيد الدراسة', 'launch' => 'بدأ المشروع حديثًا', 'growth' => 'يحقق مبيعات حاليًا', 'scale' => 'يحقق مبيعات ويستعد للتوسع'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('stage', 'growth') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن المشروع في مرحلة الفكرة يحتاج أسئلة مختلفة عن مشروع يبيع أو يستعد للتوسع.</p></details>
            </label>
        </div>

        <label class="field">
            <span class="field__label">ماذا تبيع بالضبط؟</span>
            <span class="field__help">اكتب وصفًا مباشرًا يفهمه شخص يتعرف إلى مشروعك للمرة الأولى. مثال: نبيع عسلًا طبيعيًا ونوصله داخل المدينة خلال يوم.</span>
            <textarea name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
            <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن وصف ما تبيعه يحدد أي أسئلة تناسب عرضك، ويمنع بناء التقرير على افتراض غير صحيح.</p></details>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">أين يوجد عملاؤك؟</span>
                <span class="field__help">اكتب المدينة أو الدولة أو المنطقة التي تستهدفها الآن.</span>
                <input type="text" name="geography" value="{{ old('geography') }}" maxlength="120">
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن السوق المحلي يغيّر المنافسين والقنوات واللغة التي تصل إلى العميل.</p></details>
            </label>

            <label class="field">
                <span class="field__label">ما رابط موقعك أو حسابك الرئيسي؟</span>
                <span class="field__help">الصق الرابط كاملًا، ويمكنك تركه فارغًا إذا لم يكن لديك رابط بعد.</span>
                <input type="url" name="website" value="{{ old('website') }}" maxlength="255" placeholder="https://">
                <details class="field__why"><summary>لماذا نسأل؟</summary><p>لأن الرابط يساعدنا على فهم ما يراه العميل فعليًا، ويقلل الأسئلة التي يمكن الإجابة عنها من صفحتك.</p></details>
            </label>
        </div>

        <button type="submit" class="btn btn--primary">احفظ المشروع وتابع</button>
    </form>
@endsection

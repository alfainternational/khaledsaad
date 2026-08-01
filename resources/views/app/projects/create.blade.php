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

    <form method="POST" action="{{ route('app.projects.store') }}" class="form form--wide form-layout question-form">
        @csrf

        @if (($startTool ?? null) !== null)
            <input type="hidden" name="start_tool" value="{{ $startTool['key'] }}">
        @endif

        <label class="field">
            <span class="field__label">ما اسم مشروعك؟</span>
            <span class="field__help">اكتب الاسم الذي يعرف به العملاء مشروعك، أو اسمًا مؤقتًا إذا كان المشروع ما زال فكرة.</span>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus maxlength="120">
            <span class="question-reason" aria-label="سبب طرح السؤال">لأن الاسم يميز مشروعك داخل المنصة، ويظهر في التقارير والملفات التي تنشئها.</span>
        </label>

        <label class="field">
            <span class="field__label">في أي قطاع يعمل مشروعك؟</span>
            <span class="field__help">نتعمق أكثر في التعليم والتجارة الإلكترونية والعقارات، وبقية القطاعات نخدمها بالمسار الكامل المعتاد.</span>
            <select name="sector">
                <option value="" @selected(old('sector') === null || old('sector') === '')>اختر القطاع…</option>
                @foreach (\App\Modules\Shared\Sectors\Sector::options() as $option)
                    <option value="{{ $option['value'] }}" @selected(old('sector') === $option['value'])>{{ $option['label'] }} — {{ $option['hint'] }}</option>
                @endforeach
            </select>
            <span class="question-reason" aria-label="سبب طرح السؤال">لأن اختيارك يفتح أسئلة وفحوصات خاصة بقطاعك، ويقارن نتيجتك بأنشطة قطاعك لا بالسوق كله.</span>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">صف مجالك بكلمة أو كلمتين</span>
                <span class="field__help">تفصيل يضيّق القطاع، مثل: مدارس أهلية، متجر عطور، وساطة سكنية.</span>
                <input type="text" name="industry" value="{{ old('industry') }}" maxlength="120" placeholder="مدارس أهلية، متجر عطور…">
                <span class="question-reason" aria-label="سبب طرح السؤال">لأن التفصيل يجعل الأسئلة والتقرير أقرب إلى واقعك داخل قطاعك.</span>
            </label>

            <label class="field">
                <span class="field__label">إلى أي مرحلة وصل مشروعك؟</span>
                <span class="field__help">اختر المرحلة التي تصف وضع المشروع الآن، لا المرحلة التي تريد الوصول إليها.</span>
                <select name="stage">
                    @foreach (['idea' => 'فكرة قيد الدراسة', 'launch' => 'بدأ المشروع حديثًا', 'growth' => 'يحقق مبيعات حاليًا', 'scale' => 'يحقق مبيعات ويستعد للتوسع'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('stage', 'growth') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="question-reason" aria-label="سبب طرح السؤال">لأن المشروع في مرحلة الفكرة يحتاج أسئلة مختلفة عن مشروع يبيع أو يستعد للتوسع.</span>
            </label>
        </div>

        <label class="field">
            <span class="field__label">ماذا تبيع بالضبط؟</span>
            <span class="field__help">اكتب وصفًا مباشرًا يفهمه شخص يتعرف إلى مشروعك للمرة الأولى. مثال: نبيع عسلًا طبيعيًا ونوصله داخل المدينة خلال يوم.</span>
            <textarea name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
            <span class="question-reason" aria-label="سبب طرح السؤال">لأن وصف ما تبيعه يحدد أي أسئلة تناسب عرضك، ويمنع بناء التقرير على افتراض غير صحيح.</span>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">أين يوجد عملاؤك؟</span>
                <span class="field__help">اكتب المدينة أو الدولة أو المنطقة التي تستهدفها الآن.</span>
                <input type="text" name="geography" value="{{ old('geography') }}" maxlength="120">
                <span class="question-reason" aria-label="سبب طرح السؤال">لأن السوق المحلي يغيّر المنافسين والقنوات واللغة التي تصل إلى العميل.</span>
            </label>

            <label class="field">
                <span class="field__label">ما رابط موقعك أو حسابك الرئيسي؟</span>
                <span class="field__help">الصق الرابط كاملًا، ويمكنك تركه فارغًا إذا لم يكن لديك رابط بعد.</span>
                <input type="url" name="website" value="{{ old('website') }}" maxlength="255" placeholder="https://">
                <span class="question-reason" aria-label="سبب طرح السؤال">لأن الرابط يساعدنا على فهم ما يراه العميل فعليًا، ويقلل الأسئلة التي يمكن الإجابة عنها من صفحتك.</span>
            </label>
        </div>

        <button type="submit" class="btn btn--primary">احفظ المشروع وتابع</button>
    </form>
@endsection

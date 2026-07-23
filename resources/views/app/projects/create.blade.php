@extends('layouts.app')

@section('title', 'مشروع جديد')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">مشروع جديد</p>
            <h1>عرّفنا على مشروعك</h1>
            <p class="muted">مرة واحدة فقط. كل ما تكتبه هنا نستخدمه في كل خطوة بعدها، ولن نسألك عنه مجددًا.</p>
        </div>
    </header>

    @if (($startTool ?? null) !== null)
        <p class="alert alert--info" role="status">
            الخطوة التالية بعد الحفظ: <strong>{{ $startTool['title'] }}</strong> — تُفتح تلقائيًا على هذا المشروع.
        </p>
    @endif

    <form method="POST" action="{{ route('app.projects.store') }}" class="form form--wide">
        @csrf

        @if (($startTool ?? null) !== null)
            <input type="hidden" name="start_tool" value="{{ $startTool['key'] }}">
        @endif

        <label class="field">
            <span class="field__label">اسم المشروع</span>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus maxlength="120">
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">مجال المشروع</span>
                <input type="text" name="industry" value="{{ old('industry') }}" maxlength="120" placeholder="تعليم، تجزئة، خدمات…">
            </label>

            <label class="field">
                <span class="field__label">وين وصل مشروعك؟</span>
                <select name="stage">
                    @foreach (['idea' => 'مجرد فكرة', 'launch' => 'بدأت للتو', 'growth' => 'شغّال وأبيع', 'scale' => 'أبيع وأريد التوسع'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('stage', 'growth') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="field">
            <span class="field__label">ماذا تبيع بالضبط؟</span>
            <textarea name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
            <span class="field__help">اكتبها كما تشرحها لصديق. مثال: أبيع عسلًا طبيعيًا وأوصّله داخل المدينة خلال يوم.</span>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">أين يوجد عملاؤك؟</span>
                <input type="text" name="geography" value="{{ old('geography') }}" maxlength="120">
            </label>

            <label class="field">
                <span class="field__label">موقعك أو حسابك (إن وجد)</span>
                <input type="url" name="website" value="{{ old('website') }}" maxlength="255" placeholder="https://">
            </label>
        </div>

        <button type="submit" class="btn btn--primary">احفظ وأكمل</button>
    </form>
@endsection

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
            <span class="field__label">اسم المشروع</span>
            <input type="text" name="name" value="{{ old('name', $project->name) }}" required maxlength="120">
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">مجال المشروع</span>
                <input type="text" name="industry" value="{{ old('industry', $project->industry) }}" maxlength="120" placeholder="تعليم، تجزئة، خدمات…">
            </label>

            <label class="field">
                <span class="field__label">أين وصل مشروعك؟</span>
                <select name="stage">
                    @foreach (['idea' => 'فكرة قيد الدراسة', 'launch' => 'بدأ المشروع حديثًا', 'growth' => 'يحقق مبيعات حاليًا', 'scale' => 'يحقق مبيعات ويستعد للتوسع'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('stage', $project->stage) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="field">
            <span class="field__label">ماذا تبيع بالضبط؟</span>
            <textarea name="description" rows="3" maxlength="2000">{{ old('description', $project->profile?->description) }}</textarea>
            <span class="field__help">اكتب وصفًا مباشرًا يفهمه شخص يتعرف إلى مشروعك للمرة الأولى.</span>
        </label>

        <label class="field">
            <span class="field__label">لماذا يشتري منك العميل بدل غيرك؟</span>
            <textarea name="value_proposition" rows="3" maxlength="1000">{{ old('value_proposition', $project->profile?->value_proposition) }}</textarea>
            <span class="field__help">السبب الحقيقي بجملة أو جملتين. مثال: أوصّل في نفس اليوم بينما غيري يحتاج ثلاثة أيام.</span>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field__label">أين يوجد عملاؤك؟</span>
                <input type="text" name="geography" value="{{ old('geography', $project->profile?->geography) }}" maxlength="120" placeholder="الرياض · السودان · الخليج">
            </label>

            <label class="field">
                <span class="field__label">كم تصرف على التسويق شهريًا؟</span>
                <input type="number" name="monthly_budget" value="{{ old('monthly_budget', $project->profile?->monthly_budget) }}" min="0">
                <span class="field__help">بالريال تقريبًا. اتركه فارغًا إن لم تكن تصرف شيئًا.</span>
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

@extends('layouts.app')
@section('layout', 'detail')

@section('title', 'معالجة يدوية')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">مراجعة يدوية</p>
            <h1>{{ $run['tool'] }} — {{ $run['project'] }}</h1>
        </div>
        <a href="{{ route('admin.manual.index') }}" class="btn btn--ghost">عودة للطابور</a>
    </header>

    <section class="card">
        <h2 class="section-title">1) انسخ هذه الحزمة إلى Claude أو ChatGPT</h2>
        <p class="muted">
            تحتوي على بيانات المشروع، والأسئلة بإجاباتها، والدرجة المحسوبة، والمخطط المطلوب للمخرج.
            التعليمات مضمّنة داخلها، فالصقها كما هي.
        </p>

        <div class="card__actions">
            <button type="button" class="btn btn--primary btn--sm" data-copy-package>انسخ الحزمة</button>
            <a href="{{ route('admin.manual.export', $run['uuid']) }}" class="btn btn--ghost btn--sm">نزّلها ملفًا</a>
        </div>

        <textarea id="manual-package" class="code-block" rows="12" readonly dir="ltr">{{ $package }}</textarea>
    </section>

    <section class="card">
        <h2 class="section-title">2) الصق النتيجة هنا</h2>
        <p class="muted">
            الصق كائن JSON كما أعادته الأداة. نتحقق أنه يطابق المخطط قبل التركيب — فإن خالفه نخبرك بالسبب
            ولا يصل للعميل شيء ناقص.
        </p>

        @error('payload')
            <p class="field__error">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('admin.manual.store', $run['uuid']) }}" class="form form--wide">
            @csrf
            <textarea name="payload" rows="12" class="code-block" dir="ltr" required
                placeholder='{"summary": "...", "findings": [...], "next_step": {...}}'>{{ old('payload') }}</textarea>
            <button type="submit" class="btn btn--primary">ركّب التقرير في حساب العميل</button>
        </form>
    </section>

    @push('scripts')
        <script>
            document.querySelector('[data-copy-package]')?.addEventListener('click', function () {
                var box = document.getElementById('manual-package');
                box.select();
                navigator.clipboard.writeText(box.value).then(function () {
                    var btn = document.querySelector('[data-copy-package]');
                    btn.textContent = @js(__('نُسخت ✓'));
                    setTimeout(function () { btn.textContent = @js(__('انسخ الحزمة')); }, 2000);
                });
            });
        </script>
    @endpush
@endsection

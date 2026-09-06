@extends('layouts.app')
@section('layout', 'index')

@section('title', 'التشخيص الذكي الشامل')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المستشار التسويقي الذكي</p>
            <h1>تشخيص شامل يقودك من الأسئلة إلى خطة تنفيذ</h1>
            <p class="muted">أحدد نطاق مشروعك، وأجمع ما ينطبق عليه، ثم أبني تقريرًا موحدًا بأولويات قابلة للتنفيذ.</p>
        </div>
        <a href="{{ route('app.projects.create') }}" class="btn btn--ghost">أضف مشروعًا</a>
    </header>

    {{-- البوابة قبل السؤال الأول (INV-4).

         هنا وقع العطل: «ابدأ الاستشارة» بلا كلمة عن تكلفتها، فأجاب
         صاحب النشاط عن ستين سؤالًا ثم عرف أن الحزمة تكلّف أكثر مما
         تمنحه خطته. الجدار كان قائمًا قبل أن يضغط الزر. --}}
    <p @class(['alert', 'alert--info' => ! $preflight->isReady()])>
        {{ $preflight->headline() }}
    </p>

    @if ($preflight->isOurFault())
        {{-- المانع منّا: لا رابط فوترة ولا مطالبة (INV-8). --}}
        <p class="muted">
            {{ __('إجاباتك لن تضيع. تفقّد تقاريرك السابقة ريثما نُعيد الخدمة.') }}
        </p>
    @elseif (! $preflight->canStart())
        <p class="muted">
            {{ __('ينقصك :shortfall لتشغيل الاستشارة كاملة.', [
                'shortfall' => \App\Support\Presentation\Num::credits($preflight->shortfall()),
            ]) }}
            <a href="{{ route('app.billing') }}">{{ __('اشحن رصيدك') }}</a>
        </p>
    @endif

    @if ($projects === [])
        <section class="empty">
            <h2>ابدأ بإضافة مشروعك</h2>
            <p>يحتاج التشخيص مشروعًا كي يحفظ إجاباتك ويخصص التوصيات.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أضف المشروع وابدأ</a>
        </section>
    @else
        <div class="card-grid">
            @foreach ($projects as $project)
                <article class="card">
                    <p class="eyebrow">{{ $project['stage'] ?: 'مشروع' }}</p>
                    <h2>{{ $project['name'] }}</h2>
                    @if ($project['consultation'] && in_array($project['consultation']['status'], ['active', 'review', 'analysis_queued', 'failed'], true))
                        <p class="muted">
                            {{ trans_choice('{1} أجبت عن سؤال واحد|{2} أجبت عن سؤالين|[3,10] أجبت عن :count أسئلة|[11,*] أجبت عن :count سؤالًا', $project['consultation']['answered'], ['count' => \App\Support\Presentation\Num::int($project['consultation']['answered'])]) }}
                            · {{ $project['consultation']['status_label'] }}
                        </p>
                        <a class="btn btn--primary" href="{{ route('app.consultations.show', $project['consultation']['uuid']) }}">أكمل الاستشارة</a>
                    @else
                        <p class="muted">ابدأ بأسئلة نطاق مرنة؛ ويمكنك اختيار أكثر من إجابة عندما ينطبق أكثر من خيار.</p>
                        <form method="POST" action="{{ route('app.consultations.start', $project['slug']) }}">
                            @csrf
                            <label class="field"><span class="field__label">مستوى العمق</span>
                                <select name="depth"><option value="standard">قياسي</option><option value="quick">سريع</option><option value="deep">متعمق</option></select>
                            </label>
                            <button class="btn btn--primary" @disabled(! $preflight->canStart())>{{ __('ابدأ الاستشارة') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection

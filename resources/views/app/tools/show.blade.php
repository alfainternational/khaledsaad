@extends('layouts.app')
@section('layout', 'detail')

@section('title', $tool['title'])

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $tool['category'] }}</p>
            <h1>{{ $tool['title'] }}</h1>
            <p class="muted">{{ $tool['description'] }}</p>
        </div>
    </header>

    <section class="split">
        <article class="card">
            <p class="eyebrow">ماذا أطلب منك</p>
            <ul class="bullets">
                @foreach ($tool['inputs'] as $input)
                    <li>{{ $input }}</li>
                @endforeach
            </ul>
            <p class="muted">{{ $tool['step_count'] }} خطوات، تُحفظ أولًا بأول. وما كتبته من قبل لن نسألك عنه مجددًا.</p>
        </article>

        <article class="card">
            <p class="eyebrow">ما الذي ستحصل عليه</p>
            <ul class="bullets">
                <li>رقم واضح يقول لك أين أنت الآن</li>
                @foreach ($tool['outputs'] as $output)
                    <li>{{ $output }}</li>
                @endforeach
                <li>نفرّق بين ما هو مؤكد من كلامك وما يحتاج تأكيدًا</li>
                <li>مهام لها مواعيد تتابعها بنفسك</li>
            </ul>
        </article>
    </section>

    @if ($tool['is_runnable'] && ($engagement['state'] ?? 'new') !== 'new')
        {{-- استئناف قبل أي شيء: لا نطلب منه البدء من الصفر وعنده عمل قائم. --}}
        <section class="card card--resume">
            <p class="eyebrow">لديك عمل قائم هنا</p>
            <p>{{ $engagement['hint'] }}</p>

            @if ($engagement['state'] === 'draft' && $engagement['percent'] > 0)
                <div class="progress__bar progress__bar--slim">
                    <span style="inline-size: {{ $engagement['percent'] }}%"></span>
                </div>
                <p class="muted">أكملت {{ $engagement['percent'] }}%</p>
            @endif

            <div class="card__actions">
                <a href="{{ $engagement['url'] }}" class="btn btn--primary">{{ $engagement['label'] }}</a>
            </div>
        </section>
    @endif

    @if (! $tool['is_runnable'])
        <p class="alert alert--info">هذا التشخيص غير متاح حاليًا. اختر تشخيصًا آخر للبدء.</p>
    @elseif ($projects === [])
        <section class="empty">
            <h2>أضف مشروعك قبل بدء التشخيص</h2>
            <p>سيُحفظ التشخيص والتقرير داخل المشروع لتتمكن من العودة إليهما ومقارنة التقدم.</p>
            <a href="{{ route('app.projects.create') }}" class="btn btn--primary">أضف مشروعك وتابع</a>
        </section>
    @else
        <section class="card">
            <h2 class="section-title">
                {{ ($engagement['state'] ?? 'new') === 'new' ? __('على أي مشروع نبدأ؟') : __('أو ابدأ من جديد على مشروع') }}
            </h2>

            {{-- شاشة ما قبل البدء (INV-4): التكلفة والوقت وعدد الأسئلة
                 والرصيد قبل السؤال الأول. عرضُها بعد آخر سؤال هو ما جعل
                 مستخدمًا يجيب عن ستين سؤالًا ثم يصطدم بجدار كان قائمًا
                 قبل أن يبدأ. --}}
            <p class="preflight__headline">{{ $preflight->headline() }}</p>

            @if ($preflight->questionsSaved() > 0)
                <p class="muted">
                    {{ __('وفّرنا عليك :count لأن مشروعك يعرفها أصلًا.', [
                        'count' => trans_choice(
                            '{1} سؤالًا واحدًا|{2} سؤالين|[3,10] :count أسئلة|[11,*] :count سؤالًا',
                            $preflight->questionsSaved(),
                            ['count' => \App\Support\Presentation\Num::int($preflight->questionsSaved())],
                        ),
                    ]) }}
                </p>
            @endif

            @if ($preflight->isOurFault())
                {{-- المانع منّا: اعتذار بلا مطالبة، ولا رابط فوترة (INV-8).
                     إظهار «اشحن رصيدك» هنا يحمّله عطلًا ليس منه. --}}
                <p class="alert alert--info">
                    {{ __('إجاباتك لن تضيع، لكننا لا نستطيع تشغيل التحليل الآن. سنُعيد الخدمة قريبًا — تفقّد تقاريرك السابقة ريثما نعود.') }}
                </p>
            @elseif (! $preflight->isReady())
                {{-- الحدّ يُعلن قبل البدء ومعه إجراؤه الواحد، لا بعد المجهود. --}}
                <p class="alert alert--info">
                    {{ __('ينقصك :shortfall لتشغيل هذا التشخيص.', [
                        'shortfall' => \App\Support\Presentation\Num::credits($preflight->shortfall()),
                    ]) }}
                    <a href="{{ route('app.billing') }}">{{ __('اشحن رصيدك') }}</a>
                </p>
            @endif

            <div class="run-launcher">
                @foreach ($projects as $project)
                    <form method="POST" action="{{ route('app.runs.start', [$project['slug'], $tool['key']]) }}">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--sm" @disabled(! $preflight->isReady())>
                            {{ $project['name'] }}
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif
@endsection

@extends('layouts.app')
@section('layout', 'detail')

@section('title', 'حضورك في إجابات الذكاء | '.$project->name)

@section('content')
    <header class="page-head">
        <h1>حضورك في إجابات الذكاء</h1>
        <p class="muted">
            نسأل نماذج الذكاء أسئلة يكتبها مشترٍ حقيقي في سوقك، ونقرأ من تذكره في الجواب.
        </p>
    </header>

    @if (session('status'))
        <p class="alert alert--info" role="status">{{ session('status') }}</p>
    @endif

    @error('budget')
        <p class="alert alert--warning" role="alert">{{ $message }}</p>
    @enderror

    <section class="card">
        <h2>الأسئلة التي نسألها عنك</h2>
        <ul class="check-list">
            @foreach ($questions as $question)
                <li><span aria-hidden="true">•</span> {{ $question['text'] }}</li>
            @endforeach
        </ul>

        <p class="muted">
            كل سؤال يُسأل {{ \App\Modules\AiReadiness\Models\PresenceRun::MIN_ATTEMPTS }} مرات على الأقل.
            جواب واحد لا يُبنى عليه رقم.
        </p>

        <p class="muted">
            متبقٍّ من سقف هذا الشهر: {{ $budget->remaining() }} من {{ $budget->monthly_limit }} استعلامًا.
        </p>

        <form method="POST" action="{{ route('app.presence.probe', $project) }}">
            @csrf
            <button type="submit" class="button button--primary">ابدأ استطلاعًا جديدًا</button>
        </form>
    </section>

    @if ($metrics === null)
        <section class="card">
            <p class="muted">لم يُشغَّل استطلاع بعد، فلا رقم يُعرض.</p>
        </section>
    @else
        <section class="card">
            <h2>النتيجة</h2>

            {{--
                المقياسان لا يُعرضان بلا تسميتين ظاهرتين (§١٢): يُقرآن متشابهين
                ومقاماهما مختلفان تمامًا — الأول من محاولاتك، والثاني من ذكر
                السوق كله. خلطهما يعطي قراءة معكوسة عن الموقع.
            --}}
            <dl class="kv">
                <div>
                    <dt>معدّل الذكر — كم مرة ذُكرت من محاولاتنا</dt>
                    <dd>
                        @if ($metrics['mention_rate'] === null)
                            <span class="muted">لم يُقَس</span>
                        @else
                            {{ round($metrics['mention_rate'] * 100) }}٪
                            <span class="muted">من {{ $metrics['basis']['successful_attempts'] }} محاولة</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>حصة الصوت — نصيبك من ذكر كل العلامات</dt>
                    <dd>
                        @if ($metrics['share_of_voice'] === null)
                            <span class="muted">لم تُذكر أي علامة</span>
                        @else
                            {{ round($metrics['share_of_voice'] * 100) }}٪
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>معدّل الاستشهاد — كم مرة ذُكرت ومعك رابط موقعك</dt>
                    <dd>
                        @if ($metrics['citation_rate'] === null)
                            <span class="muted">لم تُذكر بعد</span>
                        @else
                            {{ round($metrics['citation_rate'] * 100) }}٪ من مرات ذكرك
                        @endif
                    </dd>
                </div>
            </dl>

            @unless ($metrics['publishable'])
                <p class="alert alert--info" role="status">
                    الدورة ناقصة: نجحت {{ $metrics['basis']['successful_attempts'] }} محاولة من
                    {{ $metrics['basis']['planned_attempts'] }}. الأرقام أعلاه على ما نجح فعلًا.
                </p>
            @endunless

            <p class="muted">
                المصدر: {{ $metrics['basis']['provider'] }} · {{ $metrics['basis']['model'] }}
                @if ($metrics['basis']['measured_at'])
                    · قيس {{ \Illuminate\Support\Carbon::parse($metrics['basis']['measured_at'])->diffForHumans() }}
                @endif
            </p>
        </section>

        <section class="card">
            <h2>سؤالًا بسؤال</h2>
            <table class="table">
                <thead>
                    <tr><th>السؤال</th><th>ظهورك</th></tr>
                </thead>
                <tbody>
                @foreach ($metrics['per_question'] as $row)
                    <tr>
                        <td>{{ $row['question'] }}</td>
                        {{-- «٢ من ٣» لا «ظاهر / غائب» (§٤.٢). --}}
                        <td>{{ $row['mentions'] }} من {{ $row['attempts'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section class="card">
        <h2>المصادر التي تستشهد بها النماذج</h2>

        @if (! $sourceMap['available'])
            <p class="muted">{{ $sourceMap['reason'] }}</p>
        @else
            <p class="muted">من {{ $sourceMap['attempts'] }} محاولة مقروءة.</p>

            <table class="table">
                <thead>
                    <tr><th>المصدر</th><th>مرات الاستشهاد</th></tr>
                </thead>
                <tbody>
                @foreach ($sourceMap['sources'] as $source)
                    <tr>
                        <td>{{ $source['host'] }} @if ($source['is_own'])<span class="tag tag--measured">موقعك</span>@endif</td>
                        <td>{{ $source['citations'] }} <span class="muted">({{ round($source['share'] * 100) }}٪)</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if ($sourceMap['own_site_ranked'] === null)
                <p class="muted">
                    موقعك لم يُستشهد به في أي محاولة. هذه المصادر أعلاه هي أين ينبغي أن تُنشر لتُقرأ.
                </p>
            @endif
        @endif
    </section>
@endsection

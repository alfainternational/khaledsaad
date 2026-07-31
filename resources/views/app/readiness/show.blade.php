@extends('layouts.app')

@section('layout', 'detail')

@section('title', 'الجاهزية للذكاء الاصطناعي — '.$project->name)

@section('content')
<div class="page">
    <header class="page-head">
        <p class="eyebrow">{{ $project->name }}</p>
        <h1>هل تظهر في إجابات النماذج؟</h1>
        <p class="lede">
            نفحص موقعك كما تقرأه النماذج: بياناته المنظَّمة، أسعاره، سياساته، وبنيته العربية.
            ثم نقرأ سجل خادمك لنعرف أي بوت زارك فعلًا.
        </p>
    </header>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @error('website')
        <div class="notice notice--warn">{{ $message }}</div>
    @enderror

    @error('log')
        <div class="notice notice--warn">{{ $message }}</div>
    @enderror

    <section class="card">
        @if ($score->isActive())
            <div class="score-row">
                <div>
                    <span class="score-num">{{ $score->score }}</span>
                    <span class="score-den">/ 100</span>
                </div>
                <div>
                    {{-- الوسم يقول مصدر الرقم: هذا ما يفرّقه عن درجة مبنيّة على كلامك. --}}
                    <span class="tag tag--measured">مقيس من موقعك</span>
                    <p class="muted">
                        فُحص {{ (int) round($score->coverage * count($score->breakdown)) }} بندًا من
                        {{ count($score->breakdown) }} — تغطية {{ (int) round($score->coverage * 100) }}٪.
                    </p>
                </div>
            </div>
        @else
            <p class="muted">لم يُفحص موقعك بعد. شغّل الفحص لتظهر درجتك.</p>
        @endif

        <div class="actions">
            <form method="POST" action="{{ route('app.readiness.audit', $project) }}">
                @csrf
                <button type="submit" class="btn btn--primary">
                    {{ $score->isActive() ? 'أعد الفحص' : 'افحص موقعي' }}
                </button>
            </form>

            @if ($score->isActive())
                <a href="{{ route('app.readiness.download', $project) }}" class="btn">حمّل البطاقة PDF</a>
            @endif
        </div>

        @if (blank($website))
            <p class="muted">لا يوجد رابط موقع في ملف المشروع، وبدونه لا يوجد ما يُفحص.</p>
        @else
            <p class="muted">الموقع المفحوص: {{ $website }}</p>
        @endif
    </section>

    {{--
        التعارض يُعرض بقوليه ولا يُحسم صامتًا (§٩). أن يقول نشاطك شيئًا وتقول
        بياناتك غيره معلومةٌ حقيقية عنه — إخفاؤها خلف «آخر قيمة تفوز» يمحو
        أنفع ما في الدماغ.
    --}}
    @if ($conflicts !== [])
        <section class="card">
            <h2>تحتاج مراجعتك</h2>
            <p class="muted">مصدران قالا شيئين مختلفين عن نفس المعلومة. لم نحسم أيّهما أصدق.</p>

            @foreach ($conflicts as $conflict)
                <h3 class="review-step">{{ $conflict['key'] }}</h3>
                <ul class="kv">
                    @foreach ($conflict['sides'] as $side)
                        <li>
                            <span>{{ $side['source'] }}</span>
                            <strong>
                                @if (is_array($side['value']))
                                    {{ implode('، ', $side['value']) }}
                                @else
                                    {{ $side['value'] ?? '—' }}
                                @endif
                            </strong>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </section>
    @endif

    <section class="card">
        <h2>محاور التشخيص</h2>

        @if ($maturity['axes_active'] > 0)
            <p class="muted">
                درجة النضج {{ $maturity['maturity_score'] }}/100،
                محسوبة من {{ $maturity['axes_active'] }} محاور مقيسة من {{ $maturity['axes_total'] }}.
                <span class="muted">آخر حساب: {{ \Illuminate\Support\Carbon::parse($maturity['computed_at'])->diffForHumans() }}</span>
            </p>

            {{--
                الاتجاه لا يُرسم قبل أربع نقاط (§١٣). قبلها تُعرض النقاط كما هي
                مع سببٍ صريح، لأن خطًّا من نقطتين يُقرأ اتجاهًا ويُتَّخذ عليه قرار.
            --}}
            @if ($plottable)
                <ol class="score-trend">
                    @foreach ($history as $point)
                        <li>
                            <span>{{ \Illuminate\Support\Carbon::parse($point['occurred_at'])->translatedFormat('j M') }}</span>
                            <strong>{{ $point['maturity_score'] }}</strong>
                        </li>
                    @endforeach
                </ol>
            @elseif ($history->count() > 1)
                <p class="muted">
                    عندك {{ $history->count() }} قياسات. الاتجاه يُرسم عند أربعة قياسات
                    فأكثر — أقل من ذلك لا يفرّق بين تحسّن حقيقي وتذبذب عادي.
                </p>
            @endif

            {{-- موقعه من قطاعه، أو سبب غياب المقارنة صراحةً — لا متوسط تقريبي. --}}
            @if ($benchmark['available'])
                <p class="muted">
                    متوسط «{{ $benchmark['industry'] }}» {{ $benchmark['industry_average'] }}/100
                    من {{ $benchmark['sample_size'] }} نشاطًا مقيسًا.
                    @if ($benchmark['delta'] !== null)
                        أنت {{ $benchmark['delta'] >= 0 ? 'أعلى بـ'.$benchmark['delta'] : 'أدنى بـ'.abs($benchmark['delta']) }}
                        نقطة، فوق {{ $benchmark['percentile'] }}٪ من أنشطة قطاعك.
                    @endif
                </p>
            @else
                <p class="muted">{{ $benchmark['reason'] }}</p>
            @endif
        @else
            <p class="muted">لم يُقَس أي محور بعد. ابدأ بفحص موقعك أعلاه.</p>
        @endif

        <table class="table">
            <thead>
                <tr><th>المحور</th><th>الدرجة</th><th>التغطية</th><th>المصدر</th></tr>
            </thead>
            <tbody>
            @foreach ($maturity['axes'] as $axis)
                <tr>
                    <td>
                        {{ $axis['label'] }}
                        <div class="muted">{{ $axis['question'] }}</div>
                    </td>
                    <td>
                        {{-- المحور غير المقيس لا يُعرض بصفر: الصفر حكم، والغياب إقرار (§٤.٣). --}}
                        @if ($axis['active'])
                            <strong>{{ $axis['axis_score'] }}</strong>/100
                        @else
                            <span class="muted">لم يُقَس</span>
                        @endif
                    </td>
                    <td>{{ (int) round($axis['axis_coverage'] * 100) }}٪</td>
                    <td>
                        @if ($axis['is_assumption'])
                            <span class="tag tag--assumption">فرضية</span>
                        @else
                            <span class="tag tag--measured">مقيس</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <p class="muted">
            المحاور المقيسة مصدرها بيانات مستقلة عنك. وما وُسم «فرضية» مبنيّ على ما كتبته
            عن نشاطك، فهو رأي منهجي لا عيب مرصود.
        </p>
    </section>

    {{--
        أثر الإصلاحات: هل تحرّكت درجتك بعد ما غيّرته؟ (SPEC-advanced-impact)

        القاعدة الحاكمة §٤.١: الحركة مرصودة والنسبة فرضية. لا جملة سببية بصيغة
        الجزم — «تحرّكت بعد إصلاحك» لا «إصلاحك حرّكها». القسم يختفي حتى تنضج
        نافذة ٤ أسابيع، وغيابه صحيح لا نقص.
    --}}
    @if (! empty($impact))
        <section class="card">
            <h2>أثر إصلاحاتك</h2>
            <p class="muted">
                قارنّا درجتك في الأربعة أسابيع قبل كل تغيير أجريته وبعده.
                <span class="tag tag--assumption">فرضية</span>
                النسبة إلى إصلاحك تزامنٌ زمنيّ لا سبب مثبت.
            </p>

            @foreach ($impact as $card)
                <div class="impact-row">
                    <h3 class="review-step">{{ $card['intervention'] }}</h3>
                    <ul class="kv">
                        <li><span>قبل</span> <strong>{{ $card['signal_before'] }}/100</strong></li>
                        <li><span>بعد</span> <strong>{{ $card['signal_after'] }}/100</strong></li>
                        <li>
                            <span>الفرق</span>
                            <strong>
                                {{ $card['signal_delta'] > 0 ? '+'.$card['signal_delta'] : $card['signal_delta'] }} نقطة
                            </strong>
                        </li>
                    </ul>
                    <p class="muted">{{ $card['attribution_note'] }}</p>
                </div>
            @endforeach
        </section>
    @endif

    <section class="card">
        <h2>سجل الزحف</h2>

        @if ($crawl === null)
            <p class="muted">
                لم يُرفع سجل بعد. ارفع ملف سجل الوصول من لوحة استضافتك لتعرف أي بوت زار موقعك،
                ومتى، وأي صفحات رُفضت أمامه.
            </p>
        @elseif ($crawl['parsed_lines'] === 0)
            <p class="notice notice--warn">
                {{-- «صفر زيارة» من ملف لم يُقرأ يصف الملف لا الموقع. --}}
                تعذّرت قراءة السجل: لم يُفهم أي سطر من {{ $crawl['unparsed_lines'] }}.
                تأكّد أنه سجل وصول بصيغة Combined.
            </p>
        @else
            <p class="muted">
                {{ $crawl['total_visits'] }} زيارة بوت خلال ٣٠ يومًا، من {{ $crawl['parsed_lines'] }} سطرًا مقروءًا.
                @if ($crawl['unparsed_lines'] > 0)
                    ({{ $crawl['unparsed_lines'] }} سطرًا لم يُفهم.)
                @endif
            </p>

            @if ($crawl['bots'] === [])
                <p class="notice notice--warn">
                    لم يزر موقعك أي بوت ذكاء اصطناعي في هذه النافذة. الموقع الذي لا يُزار لا يظهر في الإجابات.
                </p>
            @else
                <table class="table">
                    <thead><tr><th>البوت</th><th>الزيارات</th><th>رُفض أمامه</th></tr></thead>
                    <tbody>
                    @foreach ($crawl['bots'] as $bot)
                        <tr>
                            <td>{{ $bot['bot'] }}</td>
                            <td>{{ $bot['visits'] }}</td>
                            <td>{{ $bot['blocked'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        @endif

        <form method="POST" action="{{ route('app.readiness.log', $project) }}" enctype="multipart/form-data" class="upload">
            @csrf
            <label for="log">ملف السجل</label>
            <input type="file" name="log" id="log" required>
            <button type="submit" class="btn">ارفع وحلّل</button>
        </form>
    </section>

    @if (! empty($fixes))
        <section class="card">
            <h2>ما أصلحه هذا الأسبوع</h2>
            <p class="muted">مرتّبة على الأثر ثم الجهد — ابدأ من الأعلى.</p>

            @php
                // مصفوفة الأثر × الجهد (بند ٢٦): الترجمة البصرية لتعريف المخرج في
                // الدستور §٦. عرض فقط — الأثر محسوب في FixList لا هنا.
                $shown = array_slice($fixes, 0, 10);
                $impacts = array_column($shown, 'impact');
                sort($impacts);
                $median = $impacts === [] ? 0 : $impacts[intdiv(count($impacts), 2)];
                $quadrant = fn (array $fix) => (($fix['impact'] >= $median ? 'high' : 'low')
                    .'-'.($fix['effort'] === 'low' ? 'easy' : 'hard'));
                $groups = collect($shown)->groupBy($quadrant);
            @endphp

            <div class="fix-matrix" role="table" aria-label="الفجوات مرتبة على الأثر والجهد">
                @foreach ([
                    'high-easy' => ['label' => 'اكسب سريعًا — ابدأ هنا', 'tone' => 'win'],
                    'high-hard' => ['label' => 'خطط له هذا الشهر', 'tone' => 'plan'],
                    'low-easy' => ['label' => 'نفّذه حين تفرغ', 'tone' => 'later'],
                    'low-hard' => ['label' => 'أجّله بلا ندم', 'tone' => 'skip'],
                ] as $key => $cell)
                    <div class="fix-matrix__cell fix-matrix__cell--{{ $cell['tone'] }}">
                        <h3>{{ $cell['label'] }}</h3>
                        @forelse ($groups->get($key, collect()) as $fix)
                            <p>{{ $fix['title'] }}</p>
                        @empty
                            <p class="muted">لا شيء هنا</p>
                        @endforelse
                    </div>
                @endforeach
            </div>

            <ol class="fix-list">
                @foreach ($shown as $fix)
                    <li>
                        <strong>{{ $fix['title'] }}</strong>
                        <span class="tag tag--{{ $fix['effort'] }}">{{ $fix['effort_label'] }}</span>
                        @if ($fix['fix'])
                            <p class="muted">{{ $fix['fix'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</div>
@endsection

<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: hacentunisia; color: #1a2233; font-size: 9.5pt; line-height: 1.75; }
        h1 { color: #fff; font-size: 18pt; margin: 0; }
        h2 { color: #071f5b; font-size: 13pt; border-bottom: 2px solid #2575ff; padding-bottom: 3pt; margin: 16pt 0 7pt; }
        h3 { color: #071f5b; font-size: 10.5pt; margin: 0 0 3pt; }
        h4 { color: #071f5b; font-size: 9.5pt; margin: 8pt 0 2pt; }
        p { margin: 0 0 5pt; }
        ul, ol { margin: 3pt 0 8pt; }
        .cover { background: #071f5b; color: #fff; padding: 20pt; margin-bottom: 12pt; }
        .cover p { color: #c7d4f2; }
        .muted { color: #5d6b82; }
        .score { font-size: 27pt; color: #2575ff; font-weight: bold; }
        .card { border: 1px solid #dfe8f5; padding: 9pt; margin: 6pt 0; page-break-inside: avoid; }
        .priority { border-right: 3px solid #09d7e5; }
        .problem { border-right: 3px solid #f2704f; }
        .warn { background: #fff8f2; border-color: #f6cfa4; }
        table { width: 100%; border-collapse: collapse; margin: 6pt 0; }
        th, td { border: 1px solid #dfe8f5; padding: 5pt; vertical-align: top; }
        th { background: #f5f9ff; color: #071f5b; }
        .small { font-size: 8pt; color: #5d6b82; }
        .chip { background: #f5f9ff; border: 1px solid #dfe8f5; padding: 1pt 4pt; font-size: 8pt; color: #071f5b; }
        .theme { page-break-inside: avoid; margin-bottom: 8pt; }
    </style>
</head>
<body>
    <div class="cover">
        <h1>{{ $agencyReport->title }}</h1>
        <p>{{ $snapshot['project']['name'] }} · مستند حالة جاهز لبناء الخطة عليه مباشرة</p>
        <p>الإصدار {{ $agencyReport->version }} · {{ $agencyReport->generated_at?->format('Y-m-d H:i') }}</p>
    </div>

    {{--
        الصفحة الأولى لقارئ واحد: من يقرر قبول العميل. تنتهي بفاصل صفحة
        مقصود كي تُطبع وتُمرَّر وحدها دون بقية الملف.
    --}}
    @include('agency-reports.partials.decision-card', ['snapshot' => $snapshot, 'print' => true])

    <div style="page-break-after: always;"></div>

    <h2>الملخص التنفيذي</h2>
    <table>
        <tr>
            <td style="width: 25%; text-align:center;">
                @if ($snapshot['readiness']['score'] !== null)
                    <div class="score">{{ $snapshot['readiness']['score'] }}/100</div>
                    <b>{{ $snapshot['readiness']['band'] }}</b>
                @else
                    <div class="score">—</div>
                    <b class="small">بلا درجة رقمية</b>
                @endif
            </td>
            <td>
                <h3>{{ $snapshot['project']['name'] }}</h3>
                <p>{{ $snapshot['project']['description'] }}</p>
                <p><b>الهدف:</b> {{ $snapshot['project']['primary_goal_label'] ?? $snapshot['project']['primary_goal'] }}</p>
                <p><b>عرض القيمة:</b> {{ $snapshot['project']['value_proposition'] }}</p>
                <p><b>الميزانية:</b>
                    {{ $snapshot['project']['monthly_budget'] !== null ? number_format($snapshot['project']['monthly_budget']) : ($snapshot['project']['budget_summary'] ?? 'غير متاحة') }}
                </p>
            </td>
        </tr>
    </table>

    @if (isset($snapshot['executive']))
        <p>{{ $snapshot['executive']['position'] }}</p>
        @if ($snapshot['executive']['context'] !== '')<p class="small">{{ $snapshot['executive']['context'] }}</p>@endif

        <h4>أبرز ما يحتاج معالجة</h4>
        @forelse ($snapshot['executive']['problems'] as $problem)
            <div class="card problem">
                <h3>{{ $problem['title'] }}</h3>
                <p>{{ $problem['description'] }}</p>
                <p class="small">{{ $problem['source_tool'] }} · الخطورة: {{ $problem['severity_label'] ?? $problem['severity'] }} · {{ $problem['basis'] }}</p>
            </div>
        @empty
            <p class="muted">لم تُسجَّل مشكلات ذات خطورة في التشخيصات المضمّنة.</p>
        @endforelse

        <h4>أسرع ما يمكن البدء به</h4>
        @forelse ($snapshot['executive']['opportunities'] as $item)
            <div class="card priority">
                <h3>{{ $item['title'] }}</h3>
                <p>{{ $item['description'] }}</p>
                <p class="small">{{ $item['source_tool'] }} · الأثر: {{ $item['impact_label'] ?? $item['impact'] }} · الجهد: {{ $item['effort_label'] ?? $item['effort'] }}</p>
            </div>
        @empty
            <p class="muted">لا توجد مكاسب سريعة مسجّلة بعد.</p>
        @endforelse

        <p class="small">{{ $snapshot['executive']['reading_note'] }}</p>
    @endif

    {{-- التكليف والمال: بدونهما يبقى المستند وصف حالة لا طلب عمل قابلًا للتسعير. --}}
    @if (! empty($snapshot['mandate']))
        <h2>التكليف: المطلوب من الوكالة</h2>
        @if ($snapshot['mandate']['scope_declared'])
            <p><b>الخدمات المطلوبة:</b> {{ implode('، ', $snapshot['mandate']['services']) }}</p>
        @else
            <p class="small">لم يُحدَّد نطاق الخدمات بعد — اطلبوا تحديده قبل التسعير.</p>
        @endif

        @if (! empty($snapshot['mandate']['success_metric']))
            <p><b>تعريف النجاح كما كتبه صاحب المشروع:</b> {{ $snapshot['mandate']['success_metric'] }}</p>
        @endif

        @foreach ($snapshot['mandate']['answered'] as $answer)
            @continue($answer['key'] === 'success_metric')
            <p><b>{{ $answer['label'] }}</b> {{ $answer['value'] }}</p>
        @endforeach

        @if ($snapshot['mandate']['unanswered'] !== [])
            <h4>لم يُجب بعد — احسموها في أول اجتماع</h4>
            <ul>@foreach ($snapshot['mandate']['unanswered'] as $question)<li>{{ $question }}</li>@endforeach</ul>
        @endif
    @endif

    @if (! empty($snapshot['commercials']))
        @php($money = $snapshot['commercials'])
        <h2>البند التجاري: ما يصل إلى الإعلان فعلًا</h2>

        @if ($money['mode'] === 'full' && $money['stated_budget'] !== null)
            <p>
                <b>الميزانية الشهرية المعلنة:</b>
                {{ number_format((float) $money['stated_budget']) }}
                {{ $money['budget_currency'] ?? '(العملة غير محددة)' }} —
                @if ($money['includes_agency_fee'] === true)
                    شاملة أتعاب الوكالة.
                @elseif ($money['includes_agency_fee'] === false)
                    للوسائط فقط، وأتعاب الإدارة فوقها.
                @else
                    لم يُحسم بعد هل تشمل أتعاب الإدارة.
                @endif
            </p>

            @if ($money['breakdown']['media'] !== null)
                <table>
                    <thead><tr><th>البند</th><th>الشهري</th></tr></thead>
                    <tbody>
                        <tr><td>إنفاق إعلاني (الوسائط)</td><td>{{ number_format((float) $money['breakdown']['media']) }}</td></tr>
                        <tr><td>أتعاب الإدارة</td><td>{{ number_format((float) $money['breakdown']['agency_fee']) }}</td></tr>
                        <tr><td>الإنتاج</td><td>{{ number_format((float) $money['breakdown']['production']) }}</td></tr>
                        <tr><td>المنصات والاشتراكات</td><td>{{ number_format((float) $money['breakdown']['tools']) }}</td></tr>
                        <tr><td><b>التكلفة الشهرية الكلية</b></td><td><b>{{ number_format((float) $money['breakdown']['total_cost_of_ownership']) }}</b></td></tr>
                    </tbody>
                </table>
                <p class="small">أي رقم متوقع في هذا المستند محسوب على الإنفاق الإعلاني وحده لا على المبلغ الإجمالي.</p>

                @if ($money['currency_matches_market'] === false)
                    <p class="small">
                        الأرقام المرجعية للسوق بـ{{ $money['market']['currency_label'] }} والمبلغ أعلاه
                        بـ{{ $money['budget_currency'] }}؛ المقارنة تحتاج تحويلًا بسعر اليوم.
                    </p>
                @elseif ($money['currency_matches_market'] === null)
                    <p class="small">لم تُحدَّد عملة المبلغ، فاعتبروه رقمًا يحتاج تأكيدًا قبل التسعير.</p>
                @endif
            @endif

            @if (! empty($money['verdict']) && $money['verdict']['level'] !== 'sufficient')
                <p class="small"><b>{{ $money['verdict']['headline'] }}</b> — {{ $money['verdict']['detail'] }}</p>
            @endif
        @else
            <p class="small">تفاصيل الميزانية غير معروضة في هذه النسخة بطلب صاحب المشروع.</p>
        @endif

        <p class="small"><b>النطاق المفترض للتسعير:</b> {{ $money['tier']['label'] }} · سوق {{ $money['market']['label'] }}.</p>
    @endif

    @include('agency-reports.partials.operations', ['snapshot' => $snapshot, 'print' => true])

    @if (! empty($snapshot['ledger']['themes']))
        <h2>حالة المشروع كما وثّقها صاحبه</h2>
        <p class="small">
            تغطية المعرفة: {{ $snapshot['ledger']['coverage']['percent'] }}٪
            ({{ $snapshot['ledger']['coverage']['answered'] }} بندًا مُجابًا،
            {{ $snapshot['ledger']['coverage']['unanswered'] }} سؤالًا عُرض ولم يُجب بعد).
            هذا القسم يغني عن إعادة جلسة الاستكشاف من الصفر.
        </p>
        @if (! empty($snapshot['ledger']['coverage']['basis']))
            <p class="small">النسبة مقيسة على: {{ $snapshot['ledger']['coverage']['basis'] }}</p>
        @endif

        @if (! empty($snapshot['ledger']['not_covered']))
            <p class="small"><b>نطاقات لم تُغطَّ بعد</b> — تشخيصات لم تكتمل، لا فراغات في الإجابة:</p>
            <ul>
                @foreach ($snapshot['ledger']['not_covered'] as $gap)
                    <li>{{ $gap['tool'] }} — يضيف {{ $gap['adds'] }} بندًا</li>
                @endforeach
            </ul>
        @endif

        @foreach ($snapshot['ledger']['themes'] as $theme)
            <div class="theme">
                <h3>{{ $theme['title'] }} <span class="chip">{{ $theme['coverage_percent'] }}٪</span></h3>
                <p class="small">{{ $theme['intent'] }}</p>

                @if ($theme['answered'] !== [])
                    <table>
                        {{-- الإسناد في ملحق المنهجية ونسخة البيانات، لا عمودًا في كل صف. --}}
                        <thead><tr><th style="width:34%">البند</th><th>ما هو مسجّل</th></tr></thead>
                        <tbody>
                            @foreach ($theme['answered'] as $entry)
                                <tr>
                                    <td>{{ $entry['label'] }}</td>
                                    <td>{{ $entry['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($theme['unanswered'] !== [])
                    <p class="small"><b>لم يُجب بعد:</b>
                        {{ collect($theme['unanswered'])->pluck('label')->implode('، ') }}
                    </p>
                @endif
            </div>
        @endforeach
    @endif

    @if (! empty($snapshot['audiences']))
        <h2>شرائح الجمهور المسجّلة</h2>
        <table>
            <thead><tr><th>الشريحة</th><th>الأوجاع</th><th>المكاسب</th><th>السلوك</th></tr></thead>
            <tbody>
                @foreach ($snapshot['audiences'] as $audience)
                    <tr>
                        <td>{{ $audience['name'] }}</td>
                        <td>{{ $audience['pains'] }}</td>
                        <td>{{ $audience['gains'] }}</td>
                        <td>{{ $audience['behaviors'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>التشخيصات المضمّنة ونتائجها</h2>
    <table>
        <thead><tr><th>التشخيص</th><th style="width:18%">الدرجة</th><th>الخلاصة</th></tr></thead>
        <tbody>
            @foreach ($snapshot['tools'] as $tool)
                <tr>
                    <td>{{ $tool['title'] }}<br><span class="small">{{ $tool['review'] ?? '' }} · {{ $tool['produced_at'] ?? '' }}</span></td>
                    <td>
                        @if (($tool['scored'] ?? true) && $tool['score'] !== null)
                            {{ $tool['score'] }}/100<br>{{ $tool['score_band'] }}
                        @else
                            <span class="small">{{ $tool['score_note'] ?? 'بلا درجة' }}</span>
                        @endif
                    </td>
                    <td>{{ $tool['summary'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($snapshot['tools'] as $tool)
        @if (! empty($tool['findings']))
            <div class="card">
                <h3>{{ $tool['title'] }} — التفصيل</h3>
                @foreach ($tool['findings'] as $finding)
                    <h4>{{ $finding['title'] }}</h4>
                    <p>{{ $finding['description'] }}</p>
                    <p class="small">
                        {{ $finding['category'] }} · الخطورة: {{ $finding['severity_label'] ?? $finding['severity'] }} ·
                        {{ $finding['is_assumption'] ? 'افتراض' : 'مدعوم بدليل' }}
                        @if (! empty($finding['evidence'])) · {{ $finding['evidence'] }}@endif
                    </p>
                @endforeach
            </div>
        @endif
    @endforeach

    <h2>الأولويات التنفيذية</h2>
    @foreach ($snapshot['priorities'] as $priority)
        <div class="card priority">
            <h3>{{ $priority['title'] }}</h3>
            <p>{{ $priority['description'] }}</p>
            <p class="small">
                المصدر: {{ $priority['source_tool'] }} · الأثر: {{ $priority['impact_label'] ?? $priority['impact'] }} · الجهد: {{ $priority['effort_label'] ?? $priority['effort'] }}
                @if (! empty($priority['kpi'])) · المؤشر: {{ $priority['kpi'] }}@endif
            </p>
            @if ($priority['evidence'])<p class="small">الدليل: {{ $priority['evidence'] }}</p>@endif
        </div>
    @endforeach

    <h2>خطة 30 / 60 / 90 يومًا</h2>
    <table>
        <tr>
            @foreach (['30 يومًا', '60 يومًا', '90 يومًا'] as $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach (['30_days', '60_days', '90_days'] as $key)
                <td>
                    <ul>
                        @foreach ($snapshot['plan'][$key] as $item)
                            <li>{{ $item['title'] }}<br><span class="small">{{ $item['source'] }}</span></li>
                        @endforeach
                    </ul>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>مؤشرات الأداء وخط الأساس</h2>
    @if (! empty($snapshot['kpis']))
        <table>
            <thead><tr><th>المؤشر</th><th>الوحدة</th><th>خط الأساس</th><th>الهدف</th><th>آخر قراءة</th></tr></thead>
            <tbody>
                @foreach ($snapshot['kpis'] as $kpi)
                    <tr>
                        <td>{{ $kpi['name'] }}</td>
                        <td>{{ $kpi['unit'] }}</td>
                        <td>{{ $kpi['baseline'] ?? '—' }}</td>
                        <td>{{ $kpi['target'] ?? '—' }}</td>
                        <td>{{ $kpi['latest'] ?? 'لم تُسجَّل بعد' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">لم يُسجَّل أي مؤشر بخط أساس بعد. تثبيت مؤشر واحد على الأقل مُدرج في خطة الأفق الأول.</p>
    @endif

    <h2>المنافسون</h2>
    @if ($snapshot['competitors']['mode'] === 'full' && $snapshot['competitors']['items'] !== [])
        <table>
            <thead><tr><th>المنافس</th><th>الرابط</th><th>المستوى</th></tr></thead>
            <tbody>
                @foreach ($snapshot['competitors']['items'] as $competitor)
                    <tr><td>{{ $competitor['name'] }}</td><td class="small">{{ $competitor['url'] }}</td><td>{{ $competitor['tier_label'] ?? $competitor['tier'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($snapshot['competitors']['mode'] === 'summary')
        <p>{{ $snapshot['competitors']['count'] }} منافسًا مؤكدًا مسجّلين. التفاصيل محجوبة بطلب صاحب المشروع.</p>
    @elseif ($snapshot['competitors']['mode'] === 'private')
        <p class="muted">قائمة المنافسين داخلية ولم تُدرج في هذه النسخة.</p>
    @else
        <p class="muted">لم يُؤكَّد أي منافس بعد.</p>
    @endif

    <h2>سجل الأدلة</h2>
    @if ($snapshot['evidence']['mode'] === 'full' && $snapshot['evidence']['items'] !== [])
        <ul>@foreach ($snapshot['evidence']['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
    @else
        <p class="muted">
            {{ $snapshot['evidence']['count'] }} دليلًا مسجّلًا،
            {{ $snapshot['evidence']['mode'] === 'private' ? 'محجوبة بطلب صاحب المشروع' : 'معروضة كملخص دون نصوصها' }}.
        </p>
    @endif

    <h2>النطاق والملكية</h2>
    <p><b>الحسابات والأصول:</b> {{ $snapshot['scope']['account_ownership'] }}</p>
    <p><b>إيقاع المراجعة:</b> {{ $snapshot['scope']['review_cadence'] }}</p>
    <div class="card warn">
        <h3>خارج النطاق ما لم يُذكر صراحة</h3>
        <ul>@foreach ($snapshot['scope']['out_of_scope'] as $item)<li>{{ $item }}</li>@endforeach</ul>
    </div>

    <h2>ما يجب أن يتضمنه عرضكم</h2>
    <p class="small">متطلبات العرض لا أسئلة اختيارية: العرض الذي لا يغطيها لا يمكن مقارنته بغيره.</p>
    <ol>
        @foreach ($snapshot['proposal_requirements'] ?? $snapshot['agency_questions'] ?? [] as $requirement)
            <li>{{ $requirement }}</li>
        @endforeach
    </ol>

    @include('agency-reports.partials.appendix', ['snapshot' => $snapshot, 'print' => true])

    @if ($snapshot['assumptions'] !== [] || $snapshot['data_gaps'] !== [])
        <h2>حدود المعرفة: الافتراضات والبيانات الناقصة</h2>
        <ul>
            @foreach ($snapshot['assumptions'] as $item)<li>{{ $item }}</li>@endforeach
            @foreach ($snapshot['data_gaps'] as $item)<li>بيان ناقص: {{ $item }}</li>@endforeach
        </ul>
    @endif

    @if (isset($snapshot['methodology']))
        <h2>ملحق ج — المنهجية والمصادر</h2>
        <table>
            <thead><tr><th>التشخيص</th><th>تاريخ الإنتاج</th><th>نوع المراجعة</th><th>درجة</th></tr></thead>
            <tbody>
                @foreach ($snapshot['methodology']['sources'] as $source)
                    <tr>
                        <td>{{ $source['tool'] }}</td>
                        <td>{{ $source['produced_at'] }}</td>
                        <td>{{ $source['review'] }}</td>
                        <td>{{ $source['scored'] ? 'نعم' : 'لا' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="small">
            الميزانية: {{ $snapshot['methodology']['visibility']['budget'] }} ·
            المنافسون: {{ $snapshot['methodology']['visibility']['competitors'] }} ·
            الأدلة: {{ $snapshot['methodology']['visibility']['evidence'] }}
        </p>
        <ul class="small">
            @foreach ($snapshot['methodology']['limits'] as $limit)<li>{{ $limit }}</li>@endforeach
        </ul>
    @endif

    <p class="small">
        {{ $snapshot['meta']['method'] }} · لقطة
        {{ \Illuminate\Support\Carbon::parse($snapshot['meta']['snapshot_at'])->locale('ar')->translatedFormat('j F Y، H:i') }}.
    </p>
</body>
</html>

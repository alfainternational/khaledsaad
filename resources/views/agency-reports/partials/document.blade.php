{{--
    جسم موجز التكليف: مصدر واحد تعرضه لوحة صاحب المشروع وصفحة الرابط المشترك.

    القارئ هنا هو الوكالة. كل ما يخاطب صاحب المشروع (كيف يقارن العروض، أين
    يساوم، علامات الإنذار) خارج هذا الملف عمدًا — في partials.owner-guide.

    الترتيب مقصود: بطاقة القرار، فالتكليف، فالمال، فالأرقام والأصول — قبل
    الجمهور والعلامة. الوكالة تقرأ لتسعّر لا لتتثقف.
--}}

<section>
    @include('agency-reports.partials.decision-card', ['snapshot' => $snapshot, 'print' => false])
</section>

@if (! empty($snapshot['mandate']))
    <section class="card">
        <h2 class="section-title">التكليف: المطلوب من الوكالة</h2>

        @if ($snapshot['mandate']['scope_declared'])
            <p><b>الخدمات المطلوبة:</b> {{ implode('، ', $snapshot['mandate']['services']) }}</p>
        @else
            <p class="muted">لم يُحدَّد نطاق الخدمات بعد — اطلبوا تحديده قبل التسعير.</p>
        @endif

        @if (! empty($snapshot['mandate']['success_metric']))
            <p><b>تعريف النجاح كما كتبه صاحب المشروع:</b> {{ $snapshot['mandate']['success_metric'] }}</p>
        @endif

        @foreach ($snapshot['mandate']['answered'] as $answer)
            @continue($answer['key'] === 'success_metric')
            <p><b>{{ $answer['label'] }}</b> {{ $answer['value'] }}</p>
        @endforeach

        @if ($snapshot['mandate']['unanswered'] !== [])
            <h3>لم يُجب بعد</h3>
            <p class="muted">أسئلة مفتوحة تُحسم في أول اجتماع، لا فراغات تُملأ بالافتراض.</p>
            <ul class="bullets">
                @foreach ($snapshot['mandate']['unanswered'] as $question)<li>{{ $question }}</li>@endforeach
            </ul>
        @endif
    </section>
@endif

@if (! empty($snapshot['commercials']))
    @php($money = $snapshot['commercials'])
    <section class="card">
        <h2 class="section-title">البند التجاري: ما يصل إلى الإعلان فعلًا</h2>

        @if ($money['mode'] === 'full' && $money['stated_budget'] !== null)
            <p>
                <b>الميزانية الشهرية المعلنة:</b>
                {{ number_format((float) $money['stated_budget']) }}
                {{ $money['budget_currency'] ?? '(العملة غير محددة)' }}
                —
                @if ($money['includes_agency_fee'] === true)
                    شاملة أتعاب الوكالة.
                @elseif ($money['includes_agency_fee'] === false)
                    للوسائط فقط، وأتعاب الإدارة فوقها.
                @else
                    لم يُحسم بعد هل تشمل أتعاب الإدارة.
                @endif
            </p>

            @if ($money['breakdown']['media'] !== null)
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>البند</th><th>الشهري</th></tr></thead>
                        <tbody>
                            <tr><td>إنفاق إعلاني (الوسائط)</td><td>{{ number_format((float) $money['breakdown']['media']) }}</td></tr>
                            <tr><td>أتعاب الإدارة</td><td>{{ number_format((float) $money['breakdown']['agency_fee']) }}</td></tr>
                            <tr><td>الإنتاج</td><td>{{ number_format((float) $money['breakdown']['production']) }}</td></tr>
                            <tr><td>المنصات والاشتراكات</td><td>{{ number_format((float) $money['breakdown']['tools']) }}</td></tr>
                            <tr>
                                <td><b>التكلفة الشهرية الكلية</b></td>
                                <td><b>{{ number_format((float) $money['breakdown']['total_cost_of_ownership']) }}</b></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="muted">
                    أي رقم متوقع في هذا المستند محسوب على الإنفاق الإعلاني وحده، لا على المبلغ الإجمالي.
                </p>

                @if ($money['currency_matches_market'] === false)
                    <p class="muted">
                        الأرقام المرجعية للسوق بـ{{ $money['market']['currency_label'] }}، والمبلغ أعلاه
                        بـ{{ $money['budget_currency'] }}. المقارنة تحتاج تحويلًا بسعر اليوم — لم نحوّل نيابة
                        عن أحد حتى لا يبدو رقم تقديري وكأنه دقيق.
                    </p>
                @elseif ($money['currency_matches_market'] === null)
                    <p class="muted">لم تُحدَّد عملة المبلغ، فاعتبروه رقمًا يحتاج تأكيدًا قبل التسعير.</p>
                @endif
            @endif

            @if (! empty($money['verdict']) && $money['verdict']['level'] !== 'sufficient')
                <p class="evidence"><b>{{ $money['verdict']['headline'] }}</b> — {{ $money['verdict']['detail'] }}</p>
            @endif
        @else
            <p class="muted">
                تفاصيل الميزانية غير معروضة في هذه النسخة بطلب صاحب المشروع.
                @if ($money['includes_agency_fee'] === false)
                    المعلن فقط: المبلغ المخصص للوسائط لا يشمل أتعاب الإدارة.
                @elseif ($money['includes_agency_fee'] === true)
                    المعلن فقط: المبلغ المخصص يشمل أتعاب الإدارة.
                @endif
            </p>
        @endif

        <p class="muted">
            <b>النطاق المفترض للتسعير:</b> {{ $money['tier']['label'] }} · سوق {{ $money['market']['label'] }}.
        </p>
    </section>
@endif

<section class="agency-doc">
    @include('agency-reports.partials.operations', ['snapshot' => $snapshot, 'print' => false])
</section>

<section class="report-head">
    <article class="card card--score">
        <p class="eyebrow">الجاهزية العامة</p>
        @if ($snapshot['readiness']['score'] !== null)
            <p class="score-big">{{ $snapshot['readiness']['score'] }}<small>/100</small></p>
            <p class="score-chip">{{ $snapshot['readiness']['band'] }}</p>
        @else
            <p class="score-big">—</p>
            <p class="score-chip">بلا درجة رقمية بعد</p>
        @endif
    </article>
    <article class="card">
        <p class="eyebrow">المشروع والهدف</p>
        <h2>{{ $snapshot['project']['name'] }}</h2>
        <p>{{ $snapshot['project']['description'] }}</p>
        <p class="muted">{{ $snapshot['project']['value_proposition'] }}</p>
        @if ($snapshot['project']['monthly_budget'] !== null)
            <p><b>الميزانية الشهرية:</b> {{ number_format($snapshot['project']['monthly_budget']) }}</p>
        @elseif ($snapshot['project']['budget_summary'])
            <p><b>الميزانية:</b> {{ $snapshot['project']['budget_summary'] }}</p>
        @endif
    </article>
</section>

@if (isset($snapshot['executive']))
    <section class="card">
        <h2 class="section-title">الملخص التنفيذي</h2>
        <p>{{ $snapshot['executive']['position'] }}</p>
        @if ($snapshot['executive']['context'] !== '')
            <p class="muted">{{ $snapshot['executive']['context'] }}</p>
        @endif
        <p class="muted">
            تغطية المعرفة الموثقة: {{ $snapshot['executive']['knowledge_coverage']['percent'] }}٪
            ({{ $snapshot['executive']['knowledge_coverage']['answered'] }} بندًا مُجابًا).
        </p>
    </section>

    <section>
        <h2 class="section-title">أبرز ما يحتاج معالجة</h2>
        @forelse ($snapshot['executive']['problems'] as $problem)
            <article class="finding">
                <header class="finding__head">
                    <h3>{{ $problem['title'] }}</h3>
                    <span class="badge">{{ $problem['source_tool'] }}</span>
                </header>
                <p>{{ $problem['description'] }}</p>
                <p class="tags"><span>الخطورة: {{ $problem['severity_label'] ?? $problem['severity'] }}</span><span>{{ $problem['basis'] }}</span></p>
            </article>
        @empty
            <p class="muted">لم تُسجَّل مشكلات ذات خطورة في التشخيصات المضمّنة.</p>
        @endforelse
    </section>

    <section>
        <h2 class="section-title">أسرع ما يمكن البدء به</h2>
        <div class="card-grid card-grid--prose">
            @forelse ($snapshot['executive']['opportunities'] as $item)
                <article class="card">
                    <p class="eyebrow">{{ $item['impact_label'] ?? $item['impact'] }} · {{ $item['effort_label'] ?? $item['effort'] }}</p>
                    <h3>{{ $item['title'] }}</h3>
                    <p class="muted">{{ $item['description'] }}</p>
                </article>
            @empty
                <p class="muted">لا توجد مكاسب سريعة مسجّلة بعد.</p>
            @endforelse
        </div>
    </section>
@endif

@include('agency-reports.partials.unified-context', ['snapshot' => $snapshot])

@if (! empty($snapshot['ledger']['themes']))
    <section>
        <h2 class="section-title">حالة المشروع كما وثّقها صاحبه</h2>
        <p class="muted">
            {{ $snapshot['ledger']['coverage']['answered'] }} بندًا مُجابًا،
            و{{ $snapshot['ledger']['coverage']['unanswered'] }} سؤالًا عُرض ولم يُجب بعد.
            هذا القسم يغني عن إعادة جلسة الاستكشاف من الصفر.
        </p>
        @if (! empty($snapshot['ledger']['coverage']['basis']))
            <p class="muted">النسبة مقيسة على: {{ $snapshot['ledger']['coverage']['basis'] }}</p>
        @endif

        @if (! empty($snapshot['ledger']['not_covered']))
            <p class="muted">
                <b>نطاقات لم تُغطَّ بعد</b> — ليست فراغات في الإجابة بل تشخيصات لم تكتمل، وكل واحدة تضيف بنودًا:
            </p>
            <ul class="bullets">
                @foreach ($snapshot['ledger']['not_covered'] as $gap)
                    <li>{{ $gap['tool'] }} <span class="muted">— يضيف {{ $gap['adds'] }} بندًا</span></li>
                @endforeach
            </ul>
        @endif

        @foreach ($snapshot['ledger']['themes'] as $theme)
            <article class="card">
                <header class="finding__head">
                    <h3>{{ $theme['title'] }}</h3>
                    <span class="badge">{{ $theme['coverage_percent'] }}٪</span>
                </header>
                <p class="muted">{{ $theme['intent'] }}</p>

                @if ($theme['answered'] !== [])
                    <div class="table-scroll">
                        <table class="data-table">
                            {{--
                                المصدر والتاريخ محفوظان في اللقطة ونسخة البيانات
                                وملحق المنهجية، ولا يُطبعان في كل صف: عمود يتكرر
                                بنفس القيمة في كل سطر ضجيج لا إسناد.
                            --}}
                            <thead><tr><th>البند</th><th>ما هو مسجّل</th></tr></thead>
                            <tbody>
                                @foreach ($theme['answered'] as $entry)
                                    <tr>
                                        <td>{{ $entry['label'] }}</td>
                                        <td>{{ $entry['value'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($theme['unanswered'] !== [])
                    <p class="muted"><b>لم يُجب بعد:</b> {{ collect($theme['unanswered'])->pluck('label')->implode('، ') }}</p>
                @endif
            </article>
        @endforeach
    </section>
@endif

@if (! empty($snapshot['audiences']))
    <section>
        <h2 class="section-title">شرائح الجمهور المسجّلة</h2>
        <div class="card-grid card-grid--prose">
            @foreach ($snapshot['audiences'] as $audience)
                <article class="card">
                    <h3>{{ $audience['name'] }}</h3>
                    <p><b>الأوجاع:</b> {{ $audience['pains'] }}</p>
                    <p><b>المكاسب:</b> {{ $audience['gains'] }}</p>
                    <p class="muted">{{ $audience['behaviors'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif

<section>
    <h2 class="section-title">التشخيصات المضمّنة ونتائجها</h2>
    <div class="card-grid card-grid--prose">
        @foreach ($snapshot['tools'] as $tool)
            <article class="card">
                <p class="eyebrow">
                    @if (($tool['scored'] ?? true) && $tool['score'] !== null)
                        {{ $tool['score'] }}/100 · {{ $tool['score_band'] }}
                    @else
                        {{ $tool['score_note'] ?? 'بلا درجة رقمية' }}
                    @endif
                </p>
                <h3>{{ $tool['title'] }}</h3>
                <p class="muted">{{ $tool['summary'] }}</p>
                <p class="muted">{{ $tool['review'] ?? '' }} · {{ $tool['produced_at'] ?? '' }}</p>
            </article>
        @endforeach
    </div>
</section>

<section>
    <h2 class="section-title">الأولويات التنفيذية</h2>
    @foreach ($snapshot['priorities'] as $priority)
        <article class="finding">
            <header class="finding__head">
                <h3>{{ $priority['title'] }}</h3>
                <span class="badge">{{ $priority['source_tool'] }}</span>
            </header>
            <p>{{ $priority['description'] }}</p>
            <p class="tags">
                <span>الأثر: {{ $priority['impact_label'] ?? $priority['impact'] }}</span>
                <span>الجهد: {{ $priority['effort_label'] ?? $priority['effort'] }}</span>
                @if (! empty($priority['kpi']))<span>المؤشر: {{ $priority['kpi'] }}</span>@endif
            </p>
            @if ($priority['evidence'])<p class="evidence">الدليل: {{ $priority['evidence'] }}</p>@endif
        </article>
    @endforeach
</section>

<section>
    <h2 class="section-title">خطة 30 / 60 / 90 يومًا</h2>
    <div class="card-grid card-grid--prose">
        @foreach (['30_days' => 'أول 30 يومًا', '60_days' => 'حتى 60 يومًا', '90_days' => 'حتى 90 يومًا'] as $key => $label)
            <article class="card">
                <h3>{{ $label }}</h3>
                <ul class="bullets">
                    @foreach ($snapshot['plan'][$key] as $item)
                        <li>{{ $item['title'] }} <span class="muted">— {{ $item['source'] }}</span></li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </div>
</section>

<section class="card">
    <h2 class="section-title">مؤشرات الأداء وخط الأساس</h2>
    @if (! empty($snapshot['kpis']))
        <div class="table-scroll">
            <table class="data-table">
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
        </div>
    @else
        <p class="muted">لم يُسجَّل أي مؤشر بخط أساس بعد. تثبيت مؤشر واحد على الأقل مُدرج في خطة الأفق الأول.</p>
    @endif
</section>

<section class="card">
    <h2 class="section-title">المنافسون</h2>
    @if ($snapshot['competitors']['mode'] === 'full' && $snapshot['competitors']['items'] !== [])
        <ul class="bullets">
            @foreach ($snapshot['competitors']['items'] as $competitor)
                <li>{{ $competitor['name'] }} <span class="muted">{{ $competitor['url'] }} · {{ $competitor['tier_label'] ?? $competitor['tier'] }}</span></li>
            @endforeach
        </ul>
    @elseif ($snapshot['competitors']['mode'] === 'summary')
        <p>{{ $snapshot['competitors']['count'] }} منافسًا مؤكدًا مسجّلين. التفاصيل محجوبة بطلب صاحب المشروع.</p>
    @elseif ($snapshot['competitors']['mode'] === 'private')
        <p class="muted">قائمة المنافسين داخلية ولم تُدرج في هذه النسخة.</p>
    @else
        <p class="muted">لم يُؤكَّد أي منافس بعد.</p>
    @endif
</section>

<section class="card">
    <h2 class="section-title">سجل الأدلة</h2>
    @if ($snapshot['evidence']['mode'] === 'full' && $snapshot['evidence']['items'] !== [])
        <ul class="bullets">@foreach ($snapshot['evidence']['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
    @else
        <p class="muted">
            {{ $snapshot['evidence']['count'] }} دليلًا مسجّلًا،
            {{ $snapshot['evidence']['mode'] === 'private' ? 'محجوبة بطلب صاحب المشروع' : 'معروضة كملخص دون نصوصها' }}.
        </p>
    @endif
</section>

<section class="card">
    <h2 class="section-title">النطاق والملكية وإيقاع المراجعة</h2>
    <p><b>ملكية الحسابات:</b> {{ $snapshot['scope']['account_ownership'] }}</p>
    <p><b>المراجعة:</b> {{ $snapshot['scope']['review_cadence'] }}</p>
    <h3>خارج النطاق تلقائيًا</h3>
    <ul class="bullets">@foreach ($snapshot['scope']['out_of_scope'] as $item)<li>{{ $item }}</li>@endforeach</ul>
</section>

<section class="card">
    <h2 class="section-title">ما يجب أن يتضمنه عرضكم</h2>
    <p class="muted">هذه متطلبات العرض لا أسئلة اختيارية: العرض الذي لا يغطيها لا يمكن مقارنته بغيره.</p>
    <ol class="bullets">
        @foreach ($snapshot['proposal_requirements'] ?? $snapshot['agency_questions'] ?? [] as $requirement)
            <li>{{ $requirement }}</li>
        @endforeach
    </ol>
</section>

@if ($snapshot['assumptions'] !== [] || $snapshot['data_gaps'] !== [])
    <section class="card card--warn">
        <h2 class="section-title">حدود المعرفة: الافتراضات والبيانات الناقصة</h2>
        <ul class="bullets">
            @foreach ($snapshot['assumptions'] as $item)<li>{{ $item }}</li>@endforeach
            @foreach ($snapshot['data_gaps'] as $item)<li>بيان ناقص: {{ $item }}</li>@endforeach
        </ul>
    </section>
@endif

<section class="agency-doc">
    @include('agency-reports.partials.appendix', ['snapshot' => $snapshot, 'print' => false])
</section>

@if (isset($snapshot['methodology']))
    <section class="card">
        <h2 class="section-title">ملحق ج — المنهجية والمصادر</h2>
        <div class="table-scroll">
            <table class="data-table">
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
        </div>
        <p class="muted">
            الميزانية: {{ $snapshot['methodology']['visibility']['budget'] }} ·
            المنافسون: {{ $snapshot['methodology']['visibility']['competitors'] }} ·
            الأدلة: {{ $snapshot['methodology']['visibility']['evidence'] }}
        </p>
        <ul class="bullets">
            @foreach ($snapshot['methodology']['limits'] as $limit)<li>{{ $limit }}</li>@endforeach
        </ul>
    </section>
@endif

<p class="provenance">
    {{ $snapshot['meta']['method'] }} · أُخذت اللقطة في
    {{ \Illuminate\Support\Carbon::parse($snapshot['meta']['snapshot_at'])->locale('ar')->translatedFormat('j F Y، H:i') }}.
</p>

@extends('layouts.app')

@section('title', $report['title'])

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $report['tool']['title'] }} · {{ $report['project']['name'] }}</p>
            <h1>{{ $report['title'] }}</h1>
        </div>
        <div class="page-head__actions">
            @feature(\App\Support\Billing\FeatureKey::REPORTS_PDF)
                <a href="{{ route('app.reports.pdf', $report['id']) }}" class="btn btn--ghost">حمّل PDF</a>
            @else
                {{-- الترقية أوضح من زر يُرفض عند الضغط. --}}
                <a href="{{ route('app.billing') }}" class="btn btn--ghost">تصدير PDF · في الخطط المدفوعة</a>
            @endfeature
            <form method="POST" action="{{ route('app.reports.convert', $report['id']) }}">
                @csrf
                <button type="submit" class="btn btn--primary">حوّل أهم 3 توصيات إلى مهام</button>
            </form>
        </div>
    </header>

    <section class="report-head">
        <article class="card card--score">
            <p class="eyebrow">الدرجة</p>
            <p class="score-big">{{ $report['score'] }}<small>/100</small></p>
            <p class="score-chip">{{ $report['score_band'] }}</p>
            @if ($comparison)
                <p @class(['delta', 'delta--up' => $comparison['direction'] === 'up', 'delta--down' => $comparison['direction'] === 'down'])>
                    {{ $comparison['label'] }}
                </p>
            @endif
        </article>

        <article class="card">
            @if ($report['is_manually_reviewed'])
                {{-- بيان صادق موجز يخاطب خوف العميل: أنها ليست نتيجة آلة.
                     بلا مدح ولا ادّعاء «متعوب عليه» — النص نفسه يثبت ذلك. --}}
                <p class="verified-badge">
                    <b>مراجعة يدوية بواسطة {{ $report['reviewer_name'] }}</b>
                    <span>قُرئت إجاباتك ودُقّق هذا التحليل{{ $report['reviewed_at'] ? ' في '.$report['reviewed_at'] : '' }}.</span>
                </p>
            @endif

            <p class="eyebrow">الخلاصة</p>
            <p>{{ $report['summary'] }}</p>

            @if ($report['next_step'])
                <div class="next-step">
                    <p class="eyebrow">الخطوة التالية</p>
                    <strong>{{ $report['next_step']['title'] }}</strong>
                    <p class="muted">{{ $report['next_step']['description'] }}</p>
                </div>
            @endif
        </article>
    </section>

    @include('app.reports.partials.charts')

    {{-- جسر محرك النمو: التقرير الساكن يتحول بضغطة إلى مراقبة مستمرة --}}
    <section @class(['card', 'watch-card', 'card--warn' => ($watcher?->isActive() && $watcher->changes)])>
        @if ($watcher?->isActive())
            @if ($watcher->changes)
                <p class="eyebrow">تغيّرت بيانات تؤثر في التقرير</p>
                <ul class="bullets">
                    @foreach ($watcher->changes as $change)
                        <li>{{ $change['text'] }}</li>
                    @endforeach
                </ul>
                <div class="card__actions">
                    <form method="POST" action="{{ route('app.runs.start', [$report['project']['slug'], $report['tool']['key']]) }}">
                        @csrf
                        <input type="hidden" name="fresh" value="1">
                        <button type="submit" class="btn btn--primary btn--sm">أعد التحليل ببياناتك الجديدة</button>
                    </form>
                    <form method="POST" action="{{ route('app.reports.unwatch', $report['id']) }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm">أوقف المتابعة</button>
                    </form>
                </div>
            @else
                <p class="eyebrow">متابعة التقرير مفعّلة</p>
                <p class="muted">
                    سننبهك إذا تغيّرت البيانات التي بُني عليها هذا التقرير.
                    @if ($watcher->last_checked_at)
                        آخر فحص {{ $watcher->last_checked_at->diffForHumans() }}.
                    @endif
                </p>
                <form method="POST" action="{{ route('app.reports.unwatch', $report['id']) }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">أوقف المتابعة</button>
                </form>
            @endif
        @else
            <p class="eyebrow">تابع تغيّر البيانات المهمة</p>
            <p class="muted">
                فعّل المتابعة لتصلك تنبيهات عندما تتغيّر بيانات المشروع التي بُنيت عليها هذه النتائج.
            </p>
            <form method="POST" action="{{ route('app.reports.watch', $report['id']) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm">فعّل متابعة التقرير</button>
            </form>
        @endif
    </section>

    {{-- اسم النموذج شأن داخلي يخص لوحة الإدارة، لا العميل.
         ما يهم العميل هو على ماذا بُنيت النتيجة. --}}
    <p class="provenance">
        {{ $report['counts']['evidence_backed'] }} نتيجة مبنية على ما كتبته،
        و{{ $report['counts']['assumptions'] }} معلومة تحتاج إلى تأكيد منك.
    </p>

    {{-- حلقة التعلّم: تقييم واحد بسيط يعلّم المنصة ما ينفع فعلًا --}}
    <div class="feedback-row">
        <span class="muted">هل أفادك هذا التقرير؟</span>
        <form method="POST" action="{{ route('app.reports.feedback', $report['id']) }}">
            @csrf
            <input type="hidden" name="verdict" value="up">
            <button type="submit" @class(['feedback-btn', 'is-chosen' => $myVerdict === 'up']) aria-label="أفادني">👍</button>
        </form>
        <form method="POST" action="{{ route('app.reports.feedback', $report['id']) }}">
            @csrf
            <input type="hidden" name="verdict" value="down">
            <button type="submit" @class(['feedback-btn', 'is-chosen' => $myVerdict === 'down']) aria-label="لم يفدني">👎</button>
        </form>
    </div>

    @if ($report['assumptions'] !== [])
        <section class="card card--warn">
            <h2 class="section-title">معلومات تحتاج إلى تأكيد منك</h2>
            <ul class="bullets">
                @foreach ($report['assumptions'] as $assumption)
                    <li>{{ $assumption }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="findings-heading">
        <h2 id="findings-heading" class="section-title">أهم ما وجدناه والخطوات المقترحة</h2>

        @forelse ($report['findings'] as $finding)
            <article class="finding">
                <header class="finding__head">
                    <h3>{{ $finding['title'] }}</h3>
                    <span class="badge badge--{{ $finding['severity'] }}">{{ $finding['severity_label'] }}</span>
                    <span @class(['badge', 'badge--assumption' => $finding['is_assumption']])>{{ $finding['basis_label'] }}</span>
                </header>

                <p>{{ $finding['description'] }}</p>

                @if ($finding['evidence'])
                    <p class="evidence"><span>الدليل:</span> {{ $finding['evidence'] }}</p>
                @endif

                <ul class="recommendations">
                    @foreach ($finding['recommendations'] as $recommendation)
                        <li class="recommendation">
                            <strong>{{ $recommendation['title'] }}</strong>
                            <p class="muted">{{ $recommendation['description'] }}</p>
                            <p class="tags">
                                <span>{{ $recommendation['impact_label'] }}</span>
                                <span>{{ $recommendation['effort_label'] }}</span>
                                @if ($recommendation['kpi_hint'])
                                    <span>المؤشر: {{ $recommendation['kpi_hint'] }}</span>
                                @endif
                            </p>

                            @if ($recommendation['task_id'])
                                <p class="badge">أصبحت مهمة</p>
                            @else
                                <form method="POST" action="{{ route('app.reports.convert', $report['id']) }}">
                                    @csrf
                                    <input type="hidden" name="recommendation_id" value="{{ $recommendation['id'] }}">
                                    <button type="submit" class="btn btn--ghost btn--sm">حوّلها إلى مهمة</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            {{-- طلب «أعد التحليل» بلا زر يترك المستخدم أمام تعليمة لا يقدر ينفذها. --}}
            <section class="card card--resume">
                <h3>لم يكتمل التحليل الموسّع هذه المرة</h3>
                <p class="muted">
                    درجتك وإجاباتك محفوظة. يمكنك طلب التحليل مرة أخرى من دون إعادة كتابة المعلومات.
                </p>

                <div class="card__actions">
                    <form method="POST" action="{{ route('app.runs.retry', $report['run_uuid']) }}">
                        @csrf
                        <button type="submit" class="btn btn--primary">اطلب التحليل الموسع الآن</button>
                    </form>

                    <a href="{{ route('app.runs.review', $report['run_uuid']) }}" class="btn btn--ghost">راجع إجاباتك أولًا</a>
                </div>
            </section>
        @endforelse
    </section>

    <section aria-labelledby="sections-heading">
        <h2 id="sections-heading" class="section-title">تفاصيل التحليل</h2>

        @foreach ($report['sections'] as $section)
            <details class="report-section">
                <summary>{{ $section['title'] }}</summary>

                @if ($section['key'] === 'score')
                    <p class="muted">{{ $section['content']['method'] }}</p>
                    <ul class="kv">
                        @foreach ($section['content']['breakdown'] as $row)
                            <li><span>{{ $row['label'] }}</span><strong>{{ $row['points'] }} / {{ $row['weight'] }}</strong></li>
                        @endforeach
                    </ul>
                @elseif ($section['key'] === 'competitors')
                    <p>{{ $section['content']['intro'] }}</p>

                    @if (! empty($section['content']['confirmed']))
                        <p class="muted">منافسوك (الأقرب أثرًا أولًا):</p>
                        <ul class="competitor-list">
                            @foreach ($section['content']['confirmed'] as $competitor)
                                <li>
                                    <span class="competitor-list__tier competitor-list__tier--{{ $competitor['tier'] }}">{{ $competitor['tier_label'] }}</span>
                                    @if ($competitor['url'])
                                        <a href="{{ $competitor['url'] }}" target="_blank" rel="noopener noreferrer">{{ $competitor['name'] }} <b aria-hidden="true">↗</b></a>
                                    @else
                                        <strong>{{ $competitor['name'] }}</strong>
                                    @endif
                                    <form method="POST" action="{{ route('app.competitors.dismiss', $competitor['id']) }}" class="competitor-list__x">
                                        @csrf
                                        <button type="submit" aria-label="استبعاد {{ $competitor['name'] }}" title="استبعاد">×</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($section['content']['prompt_local']))
                        <div class="competitor-prompt">
                            <p>لم تضف منافسيك المحليين بعد. إضافتهم تساعد على مقارنة مشروعك بالجهات الأقرب إلى عملائك وسوقك.</p>
                            <form method="POST" action="{{ route('app.competitors.store', $report['project']['slug']) }}" class="competitor-add">
                                @csrf
                                <input type="text" name="names" placeholder="اسم منافس أو اسمين، أو @حسابهم" maxlength="500" required>
                                <button type="submit" class="btn btn--primary btn--sm">أضِفهم</button>
                            </form>
                        </div>
                    @endif

                    @if (! empty($section['content']['candidates']))
                        <p class="muted">جهات مقترحة للمراجعة. أكّد المنافسين الفعليين واستبعد غير المناسب:</p>
                        <ul class="competitor-list competitor-list--candidates">
                            @foreach ($section['content']['candidates'] as $candidate)
                                <li>
                                    <span class="competitor-list__tier">{{ $candidate['tier_label'] }}</span>
                                    <strong>{{ $candidate['name'] }}</strong>
                                    <span class="competitor-list__actions">
                                        <form method="POST" action="{{ route('app.competitors.confirm', $candidate['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn btn--ghost btn--sm" title="تأكيد">✓ ينافسني</button>
                                        </form>
                                        <form method="POST" action="{{ route('app.competitors.dismiss', $candidate['id']) }}">
                                            @csrf
                                            <button type="submit" class="competitor-list__x" title="استبعاد">×</button>
                                        </form>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($section['content']['watchlist']))
                        <p class="muted">أين ترى إعلاناتهم:</p>
                        <ul class="competitor-watch">
                            @foreach ($section['content']['watchlist'] as $view)
                                <li @class(['is-limited' => $view['limited']])>
                                    <div>
                                        <strong>{{ $view['platforms'] }}</strong>
                                        <span>{{ $view['what'] }}</span>
                                    </div>
                                    @if ($view['url'])
                                        <a href="{{ $view['url'] }}" target="_blank" rel="noopener noreferrer">{{ $view['source'] }} <b aria-hidden="true">↗</b></a>
                                    @else
                                        <em>{{ $view['source'] }}</em>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="muted">ماذا تبحث عنه في كل مكتبة:</p>
                    <ul class="bullets">
                        @foreach ($section['content']['look_for'] as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>
                @else
                    @if (isset($section['content']['headline']))
                        <p class="lead">{{ $section['content']['headline'] }}</p>
                    @endif
                    <ul class="bullets">
                        @foreach ($section['content']['points'] ?? [] as $point)
                            <li>
                                {{ $point['text'] }}
                                @if ($point['is_assumption'] ?? false)
                                    <span class="badge badge--assumption">افتراض</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </details>
        @endforeach
    </section>

    {{-- بذرة التنسيق: مخرج الأداة يقود إلى الأداة التالية تلقائيًا --}}
    @if ($suggestion)
        <section class="card next-step">
            <p class="eyebrow">التشخيص المقترح بعد ذلك</p>
            <h3>{{ $suggestion['tool']->title }}</h3>
            <p class="muted">{{ $suggestion['reason'] }}</p>
            <form method="POST" action="{{ route('app.runs.start', [$report['project']['slug'], $suggestion['tool']->key]) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm">ابدأ التشخيص بالإجابات المحفوظة</button>
            </form>
        </section>
    @endif

    {{-- توقيع صادق في نهاية التقرير اليدوي: بيان من كتبه ومتى، بلا مبالغة. --}}
    @if ($report['is_manually_reviewed'])
        <p class="report-sign">راجعه وكتبه بنفسه: {{ $report['reviewer_name'] }}@if ($report['reviewed_at']) · {{ $report['reviewed_at'] }}@endif</p>
    @endif
@endsection

@extends('layouts.app')

@section('title', $report['title'])

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $report['tool']['title'] }} · {{ $report['project']['name'] }}</p>
            <h1>{{ $report['title'] }}</h1>
        </div>
        <div class="page-head__actions">
            <a href="{{ route('app.reports.pdf', $report['id']) }}" class="btn btn--ghost">حمّل PDF</a>
            <form method="POST" action="{{ route('app.reports.convert', $report['id']) }}">
                @csrf
                <button type="submit" class="btn btn--primary">حوّل أهم 3 توصيات إلى مهام</button>
            </form>
        </div>
    </header>

    <section class="split">
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
                <p class="eyebrow">تقريرك الحي رصد تغييرًا</p>
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
                        <button type="submit" class="btn btn--ghost btn--sm">أوقف المراقبة</button>
                    </form>
                </div>
            @else
                <p class="eyebrow">التقرير الحي</p>
                <p class="muted">
                    المراقبة فعّالة — نفحص مشروعك يوميًا وننبهك إذا تغيّر ما بُني عليه هذا التقرير.
                    @if ($watcher->last_checked_at)
                        آخر فحص {{ $watcher->last_checked_at->diffForHumans() }}.
                    @endif
                </p>
                <form method="POST" action="{{ route('app.reports.unwatch', $report['id']) }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">أوقف المراقبة</button>
                </form>
            @endif
        @else
            <p class="eyebrow">لا تدع هذا التقرير يشيخ</p>
            <p class="muted">
                خلّيه حيًّا: نراقب مشروعك يوميًا — ملفك، منافسيك، إجاباتك —
                وننبهك فور أن يتغيّر ما بُنيت عليه هذه النتائج. بلا أي تكلفة.
            </p>
            <form method="POST" action="{{ route('app.reports.watch', $report['id']) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm">فعّل التقرير الحي</button>
            </form>
        @endif
    </section>

    {{-- اسم النموذج شأن داخلي يخص لوحة الإدارة، لا العميل.
         ما يهم العميل هو على ماذا بُنيت النتيجة. --}}
    <p class="provenance">
        {{ $report['counts']['evidence_backed'] }} نتيجة مبنية على ما كتبته،
        و{{ $report['counts']['assumptions'] }} اجتهاد يحتاج تأكيدًا منك.
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
            <h2 class="section-title">ما لم يُتحقق منه</h2>
            <ul class="bullets">
                @foreach ($report['assumptions'] as $assumption)
                    <li>{{ $assumption }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="findings-heading">
        <h2 id="findings-heading" class="section-title">النتائج والتوصيات</h2>

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
                <h3>التحليل الموسع لم يكتمل هذه المرة</h3>
                <p class="muted">
                    درجتك وكل إجاباتك محفوظة. إعادة الطلب لا تكلفك إدخالًا جديدًا،
                    وتستغرق دقيقتين تقريبًا.
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
                            <p>لم تسمِّ منافسيك المحليين بعد — وهم الأهم. من يأخذ عملاءك في مدينتك يوجّه خطتك أكثر من أي علامة بعيدة.</p>
                            <form method="POST" action="{{ route('app.competitors.store', $report['project']['slug']) }}" class="competitor-add">
                                @csrf
                                <input type="text" name="names" placeholder="اسم منافس أو اسمين، أو @حسابهم" maxlength="500" required>
                                <button type="submit" class="btn btn--primary btn--sm">أضِفهم</button>
                            </form>
                        </div>
                    @endif

                    @if (! empty($section['content']['candidates']))
                        <p class="muted">مرشّحون اكتشفناهم — أكّد من ينافسك فعلًا واستبعد الباقي:</p>
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
            <p class="eyebrow">اقتراحك الجاهز التالي</p>
            <h3>{{ $suggestion['tool']->title }}</h3>
            <p class="muted">{{ $suggestion['reason'] }}</p>
            <form method="POST" action="{{ route('app.runs.start', [$report['project']['slug'], $suggestion['tool']->key]) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm">ابدأها الآن — إجاباتك السابقة تُملأ تلقائيًا</button>
            </form>
        </section>
    @endif
@endsection

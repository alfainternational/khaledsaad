@extends('layouts.app')

@section('title', 'الظهور في محركات الذكاء — '.$project->name)

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">محرك النمو · {{ $project->name }}</p>
            <h1>الظهور في محركات الذكاء</h1>
            <p class="muted">
                عملاؤك اليوم يسألون ChatGPT قبل أن يبحثوا في جوجل.
                هذه الحزمة تجعل مشروعك قابلًا للقراءة — والاقتباس — من الآلات.
            </p>
        </div>
        <div class="page-head__actions">
            @if ($missing === [] )
                <form method="POST" action="{{ route('app.geo.generate', $project) }}">
                    @csrf
                    <button type="submit" class="btn btn--primary">
                        {{ $pack === null ? 'ابنِ الحزمة الآن' : 'حدّث الحزمة' }}
                    </button>
                </form>
            @endif
        </div>
    </header>

    @if ($missing !== [])
        <section class="card card--warn">
            <h2 class="section-title">أكمل ملفك أولًا</h2>
            <p>
                الحزمة تُبنى مما كتبته أنت — لا نخترع عنك حقيقة واحدة.
                ينقصك: <strong>{{ implode('، ', $missing) }}</strong>.
            </p>
            <div class="card__actions">
                <a href="{{ route('app.projects.edit', $project) }}" class="btn btn--primary btn--sm">أكمل ملف المشروع</a>
            </div>
        </section>
    @elseif ($pack === null)
        <section class="empty">
            <h2>حزمتك لم تُبنَ بعد</h2>
            <p class="muted">
                بضغطة واحدة نحوّل ملف مشروعك إلى: أسئلة وأجوبة جاهزة للاقتباس،
                وسم JSON-LD لموقعك، وملف llms.txt تقرأه النماذج مباشرة.
            </p>
        </section>
    @else
        <p class="provenance">
            بُنيت في {{ $pack->generated_at->translatedFormat('j F Y') }}
            من حقائق ملفك أنت — حدّث الملف ثم حدّث الحزمة ليبقيا متطابقين.
        </p>

        <section aria-labelledby="faq-heading">
            <h2 id="faq-heading" class="section-title">أسئلة يجيب عنها الذكاء الاصطناعي — بكلماتك</h2>
            @foreach ($pack->faq as $entry)
                <details class="report-section">
                    <summary>{{ $entry['question'] }}</summary>
                    <p>{{ $entry['answer'] }}</p>
                </details>
            @endforeach
        </section>

        @if ($pack->credibility)
            <section class="card">
                <h2 class="section-title">إشارات المصداقية</h2>
                <p class="muted">ما تتحقق منه الآلات قبل أن ترشّحك — اجعله ظاهرًا في موقعك وحساباتك:</p>
                <ul class="bullets">
                    @foreach ($pack->credibility as $signal)
                        <li>{{ $signal }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="code-heading">
            <h2 id="code-heading" class="section-title">ضعها في موقعك</h2>

            <div class="split">
                <article class="card">
                    <h3>وسم JSON-LD</h3>
                    <p class="muted">انسخه داخل <code>&lt;head&gt;</code> صفحتك الرئيسية — به تفهمك محركات البحث والذكاء بدقة.</p>
                    <pre class="geo-code" dir="ltr"><code>&lt;script type="application/ld+json"&gt;
{{ json_encode($pack->jsonld['organization'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}
&lt;/script&gt;
&lt;script type="application/ld+json"&gt;
{{ json_encode($pack->jsonld['faq_page'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}
&lt;/script&gt;</code></pre>
                </article>

                <article class="card">
                    <h3>ملف llms.txt</h3>
                    <p class="muted">
                        ضعه في جذر موقعك (بجوار robots.txt) — بطاقة تعريفك التي تقرأها
                        النماذج اللغوية مباشرة.
                    </p>
                    <div class="card__actions">
                        <a href="{{ route('app.geo.llms', $project) }}" class="btn btn--primary btn--sm">نزّل llms.txt</a>
                    </div>
                    <pre class="geo-code">{{ $pack->llms_txt }}</pre>
                </article>
            </div>
        </section>
    @endif
@endsection

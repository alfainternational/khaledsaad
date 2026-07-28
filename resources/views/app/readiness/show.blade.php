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

            <ol class="fix-list">
                @foreach (array_slice($fixes, 0, 10) as $fix)
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

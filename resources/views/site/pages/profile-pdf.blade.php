<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { direction: rtl; margin: 0; color: #17233f; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.65; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 5px; color: #071f5b; font-size: 27px; }
        h2 { margin: 18px 0 8px; padding-bottom: 5px; border-bottom: 2px solid #2575ff; color: #071f5b; font-size: 16px; }
        h3 { margin-bottom: 2px; color: #071f5b; font-size: 13px; }
        a { color: #1d5fcc; text-decoration: none; }
        ul { margin: 5px 0 0; padding-right: 18px; }
        .header { padding: 22px 24px; color: #fff; background: #071f5b; }
        .header h1, .header a { color: #fff; }
        .header p { margin-bottom: 5px; }
        .content { padding: 18px 24px; }
        .meta { color: #d6e5ff; }
        .job { margin-bottom: 13px; page-break-inside: avoid; }
        .job-meta { color: #52617d; }
        .tags span { display: inline-block; margin: 0 0 5px 4px; padding: 3px 7px; border: 1px solid #ccd8ee; border-radius: 10px; }
        .two { width: 100%; }
        .two td { width: 50%; vertical-align: top; padding-left: 15px; }
    </style>
</head>
<body>
    <header class="header">
        <h1>{{ $brand['name'] }} <small>— {{ $brand['name_en'] }}</small></h1>
        <p>{{ $brand['professional_headline'] }}</p>
        <p class="meta">{{ $brand['location'] }} · {{ $brand['experience_years'] }} · {{ $brand['contact']['phone_display'] }}</p>
        <a href="{{ $brand['contact']['linkedin'] }}">{{ $brand['contact']['linkedin'] }}</a>
    </header>
    <div class="content">
        <h2>الملخص المهني</h2>
        @foreach ($brand['about'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach

        <h2>الخبرات المهنية</h2>
        @foreach ($brand['experience'] as $job)
            <section class="job">
                <h3>{{ $job['role'] }} — {{ $job['company'] }}</h3>
                <div class="job-meta">{{ $job['period'] }} · {{ $job['location'] }}</div>
                @if (! empty($job['responsibilities']))<ul>@foreach ($job['responsibilities'] as $responsibility)<li>{{ $responsibility }}</li>@endforeach</ul>@endif
            </section>
        @endforeach

        <table class="two"><tr><td>
            <h2>التعليم</h2>
            @foreach ($brand['education'] as $education)<h3>{{ $education['degree'] }}</h3><p>{{ $education['institution'] }}<br>{{ $education['period'] }}</p>@endforeach
        </td><td>
            <h2>الخدمات المهنية</h2>
            <div class="tags">@foreach ($brand['professional_services'] as $service)<span>{{ $service }}</span>@endforeach</div>
        </td></tr></table>

        <h2>الشهادات والتراخيص</h2>
        @foreach ($brand['credentials'] as $credential)
            <section class="job">
                <h3>{{ $credential['name'] }}</h3>
                @if (! empty($credential['issuer']) || ! empty($credential['issued']))
                    <div class="job-meta">{{ $credential['issuer'] ?? '' }}@if (! empty($credential['issuer']) && ! empty($credential['issued'])) · @endif{{ $credential['issued'] ?? '' }}</div>
                @endif
                @if (! empty($credential['credential_id']))<small>مُعرّف الاعتماد: {{ $credential['credential_id'] }}</small>@endif
            </section>
        @endforeach

        <h2>المهارات</h2>
        <div class="tags">@foreach ($brand['skills'] as $skill)<span>{{ $skill }}</span>@endforeach</div>
    </div>
</body>
</html>

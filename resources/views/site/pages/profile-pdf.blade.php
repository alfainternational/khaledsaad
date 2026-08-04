<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { direction: rtl; margin: 0; color: #17233f; font-size: 10.5pt; line-height: 1.72; }
        h1, h2, h3, p { margin-top: 0; }
        a { color: #1d5fcc; text-decoration: none; }
        ul { margin: 5px 0 0; padding-right: 18px; }
        li { margin-bottom: 3px; }
        .hero { margin-bottom: 16px; padding: 19px 21px 17px; color: #fff; background: #071f5b; border-right: 6px solid #09d7e5; }
        .hero h1 { margin: 0 0 3px; color: #fff; font-size: 25pt; line-height: 1.2; }
        .hero h1 span { color: #bcd1f4; font-size: 12pt; font-weight: normal; }
        .hero__headline { margin-bottom: 10px; color: #fff; font-size: 11.5pt; font-weight: bold; }
        .hero__meta { margin: 0; color: #d6e5ff; font-size: 9pt; }
        .hero__links { margin-top: 5px; color: #fff; font-size: 8.5pt; }
        .hero__links a { color: #fff; }
        .section { margin-top: 16px; }
        .section-title { margin: 0 0 9px; padding: 0 0 5px; border-bottom: 2px solid #2575ff; color: #071f5b; font-size: 16pt; line-height: 1.25; }
        .summary { padding: 12px 15px; border: 1px solid #dfe8f5; background: #f5f8ff; }
        .summary p { margin-bottom: 7px; }
        .summary p:last-child { margin-bottom: 0; }
        .job { margin-bottom: 11px; padding: 10px 12px; border-right: 4px solid #09d7e5; background: #fbfdff; page-break-inside: avoid; }
        .job h3 { margin: 0 0 2px; color: #071f5b; font-size: 12pt; line-height: 1.35; }
        .job-meta { margin-bottom: 3px; color: #52617d; font-size: 8.8pt; }
        .two { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .two td { width: 50%; vertical-align: top; padding: 0; }
        .compact-card { min-height: 125px; padding: 11px 13px; border: 1px solid #dfe8f5; background: #f8faff; page-break-inside: avoid; }
        .compact-card h2 { margin: 0 0 8px; color: #071f5b; font-size: 14pt; }
        .compact-card h3 { margin: 0 0 2px; color: #071f5b; font-size: 10.5pt; }
        .compact-card p { margin-bottom: 7px; color: #52617d; font-size: 9pt; }
        .tags span { display: inline-block; margin: 0 0 5px 4px; padding: 3px 7px; border: 1px solid #b9cae8; color: #213c72; background: #fff; font-size: 8.5pt; }
        .credentials { page-break-before: always; }
        .credential-table { width: 100%; border-collapse: separate; border-spacing: 7px 7px; }
        .credential-table td { width: 50%; vertical-align: top; padding: 9px 11px; border: 1px solid #dfe8f5; background: #fbfdff; page-break-inside: avoid; }
        .credential-table h3 { margin: 0 0 4px; color: #071f5b; font-size: 10.3pt; line-height: 1.4; }
        .credential-meta { color: #52617d; font-size: 8.5pt; }
        .credential-id { margin-top: 4px; color: #1d5fcc; font-size: 8pt; }
        .skills { margin-top: 12px; padding: 11px 13px; border: 1px solid #dfe8f5; background: #f5f8ff; page-break-inside: avoid; }
        .skills h2 { margin: 0 0 8px; color: #071f5b; font-size: 14pt; }
        .ltr { direction: ltr; unicode-bidi: embed; }
    </style>
</head>
<body>
    <section class="hero">
        <h1>{{ $brand['name'] }} <span class="ltr">{{ $brand['name_en'] }}</span></h1>
        <p class="hero__headline">{{ $brand['professional_headline'] }}</p>
        <p class="hero__meta">{{ $brand['location'] }} · {{ $brand['experience_years'] }} · <span class="ltr">{{ $brand['contact']['phone_display'] }}</span></p>
        <p class="hero__links"><a class="ltr" href="{{ $brand['contact']['linkedin'] }}">{{ $brand['contact']['linkedin'] }}</a></p>
    </section>

    <section class="section">
        <h2 class="section-title">الملخص المهني</h2>
        <div class="summary">
            @foreach ($brand['about'] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">الخبرات المهنية</h2>
        @foreach ($brand['experience'] as $job)
            <article class="job">
                <h3>{{ $job['role'] }} - {{ $job['company'] }}</h3>
                <div class="job-meta">{{ $job['period'] }} · {{ $job['location'] }}</div>
                @if (! empty($job['responsibilities']))
                    <ul>
                        @foreach ($job['responsibilities'] as $responsibility)
                            <li>{{ $responsibility }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </section>

    <table class="two">
        <tr>
            <td>
                <section class="compact-card">
                    <h2>التعليم</h2>
                    @foreach ($brand['education'] as $education)
                        <h3>{{ $education['degree'] }}</h3>
                        <p>{{ $education['institution'] }}<br>{{ $education['period'] }}</p>
                    @endforeach
                </section>
            </td>
            <td>
                <section class="compact-card">
                    <h2>الخدمات المهنية</h2>
                    <div class="tags">
                        @foreach ($brand['professional_services'] as $service)
                            <span>{{ $service }}</span>
                        @endforeach
                    </div>
                </section>
            </td>
        </tr>
    </table>

    <section class="section credentials">
        <h2 class="section-title">الشهادات والتراخيص</h2>
        <table class="credential-table">
            @foreach (array_chunk($brand['credentials'], 2) as $row)
                <tr>
                    @foreach ($row as $credential)
                        <td>
                            <h3>{{ $credential['name'] }}</h3>
                            @if (! empty($credential['issuer']) || ! empty($credential['issued']))
                                <div class="credential-meta">{{ $credential['issuer'] ?? '' }}@if (! empty($credential['issuer']) && ! empty($credential['issued'])) · @endif{{ $credential['issued'] ?? '' }}</div>
                            @endif
                            @if (! empty($credential['credential_id']))
                                <div class="credential-id">مُعرّف الاعتماد: <span class="ltr">{{ $credential['credential_id'] }}</span></div>
                            @endif
                        </td>
                    @endforeach
                    @if (count($row) === 1)<td></td>@endif
                </tr>
            @endforeach
        </table>
    </section>

    <section class="skills">
        <h2>المهارات</h2>
        <div class="tags">
            @foreach ($brand['skills'] as $skill)
                <span>{{ $skill }}</span>
            @endforeach
        </div>
    </section>
</body>
</html>

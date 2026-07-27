@php($consultation = $snapshot['consultation'] ?? null)
@php($synthesis = $snapshot['cross_tool_synthesis'] ?? null)

@if (! empty($consultation))
    <section class="card">
        <h2 class="section-title">سياق الاستشارة الذكية</h2>
        <p class="muted">
            العمق: {{ $consultation['depth'] ?? '—' }} ·
            الجلسة: {{ $consultation['uuid'] ?? '—' }}
        </p>

        @if (! empty($consultation['scope']))
            <h3>النطاق الذي اختارته الاستشارة</h3>
            <ul class="bullets">
                @foreach ($consultation['scope'] as $item)
                    <li>
                        {{ $item['name'] }} — {{ $item['state'] }}
                        @if (! empty($item['reason']))<span class="muted">· {{ $item['reason'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! empty($consultation['inferences']))
            <h3>الاستنتاجات والافتراضات</h3>
            <ul class="bullets">
                @foreach ($consultation['inferences'] as $item)
                    <li>
                        {{ $item['statement'] }}
                        <span class="muted">— {{ $item['type'] }} · ثقة {{ $item['confidence'] }}٪ · {{ $item['status'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if (! empty($consultation['conflicts']))
            <h3>التعارضات وقرارات الحسم</h3>
            @foreach ($consultation['conflicts'] as $conflict)
                <article class="finding">
                    <p><b>{{ $conflict['message'] }}</b></p>
                    <p class="muted">الحالة: {{ $conflict['status'] }} · الخطورة: {{ $conflict['severity'] }}</p>
                    @if (! empty($conflict['resolution']['statement']))
                        <p class="evidence">قرار الحسم: {{ $conflict['resolution']['statement'] }}</p>
                    @endif
                </article>
            @endforeach
        @endif

        <h3>أدلة الاستشارة</h3>
        @if (is_array($consultation['evidence'] ?? null) && ! empty($consultation['evidence']['hidden']))
            <p class="muted">{{ $consultation['evidence']['message'] }}</p>
        @elseif (! empty($consultation['evidence']))
            @foreach ($consultation['evidence'] as $evidence)
                <article class="finding">
                    <p><b>{{ $evidence['name'] }}</b> · {{ $evidence['extraction_status'] }}</p>
                    <p class="muted">
                        {{ $evidence['mime_type'] }} · {{ number_format((int) ($evidence['size_bytes'] ?? 0)) }} بايت
                        @if (! empty($evidence['sha256']))· البصمة SHA-256: {{ $evidence['sha256'] }}@endif
                    </p>
                    @if (! empty($evidence['text']))<p class="evidence">{{ $evidence['text'] }}</p>@endif
                </article>
            @endforeach
        @else
            <p class="muted">لم تُرفق أدلة في جلسة الاستشارة.</p>
        @endif
    </section>
@endif

@if (! empty($synthesis))
    <section class="card">
        <h2 class="section-title">مقارنة نتائج التشخيصات</h2>
        <p class="muted">كل نتيجة أدناه تحتفظ بالتشخيص والتقرير اللذين صدرت عنهما؛ لا تُدمج المصادر في حكم مجهول.</p>

        @if (! empty($synthesis['agreements']))
            <h3>اتفاق بين التشخيصات</h3>
            <ul class="bullets">
                @foreach ($synthesis['agreements'] as $item)
                    <li>{{ $item['category'] }} — {{ implode('، ', $item['findings']) }}</li>
                @endforeach
            </ul>
        @endif

        @if (! empty($synthesis['divergences']))
            <h3>اختلاف يحتاج حسمًا</h3>
            @foreach ($synthesis['divergences'] as $item)
                <article class="finding">
                    <p><b>{{ $item['category'] }}</b> — {{ implode('، ', $item['findings']) }}</p>
                    <p class="muted">التشخيصات: {{ implode('، ', $item['source_tools']) }} · القراءات: {{ implode('، ', $item['severities']) }}</p>
                    <p>{{ $item['resolution'] }}</p>
                </article>
            @endforeach
        @endif

        @if (! empty($synthesis['findings']))
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>التشخيص</th><th>النتيجة</th><th>النوع</th><th>الثقة</th></tr></thead>
                    <tbody>
                        @foreach ($synthesis['findings'] as $finding)
                            <tr>
                                <td>{{ $finding['source_tool_title'] }}<br><span class="muted">تقرير #{{ $finding['source_report_id'] }}</span></td>
                                <td><b>{{ $finding['title'] }}</b><br>{{ $finding['description'] }}</td>
                                <td>{{ $finding['claim_type'] === 'evidence' ? 'مدعومة بدليل' : 'افتراض' }}</td>
                                <td>{{ $finding['confidence'] ?? '—' }}٪</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif

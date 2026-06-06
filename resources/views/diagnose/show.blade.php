@extends('layouts.marketing', ['title' => 'نتيجة التشخيص', 'description' => 'القراءة الأولية لوضع مشروعك.'])

@section('content')
<section class="dx">
    <div class="dx-shell">

        @if ($case->isFailed())
            <div class="dx-card dx-center">
                <h1 class="dx-title">تعذّر إكمال التشخيص</h1>
                <p class="dx-sub">لم نتمكّن من قراءة المصدر الذي أدخلته. تأكد من الرابط وأعد المحاولة.</p>
                <p><a href="{{ route('diagnose.form') }}" class="dx-submit dx-btn-inline">إعادة التشخيص</a></p>
            </div>

        @elseif ($needsEmail)
            {{-- Analysis done — email gate before revealing the partial result. --}}
            <div class="dx-card">
                <span class="dx-chip">القراءة جاهزة</span>
                <h2 class="dx-section-title dx-section-title--flush">جهّزنا لك قراءة أولية</h2>
                <ul class="dx-steps">
                    <li>قرأنا الصفحة الرئيسية واستخرجنا إشارات الثقة.</li>
                    <li>راجعنا وضوح العرض وجاهزية التحويل.</li>
                    <li>حسبنا درجة عامة ومقارنة أولية.</li>
                </ul>
                <p class="dx-sub dx-sub--start">أدخل بريدك لعرضها. لن نطلب منك إنشاء حساب الآن.</p>

                @if ($errors->any())
                    <div class="dx-alert dx-mt-3">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('diagnose.email', $case) }}" class="dx-email-row">
                    @csrf
                    <input type="email" name="email" class="dx-input" placeholder="بريدك الإلكتروني" required>
                    <button type="submit" class="dx-submit">اعرض القراءة</button>
                </form>
            </div>

        @elseif ($showResult && $partial)
            @php($score = (int) ($partial['executive_score'] ?? 0))
            @php($integrity = $partial['integrity_status'] ?? 'partial')
            <div class="dx-card">
                <span class="dx-chip">القراءة الأولية</span>
                <div class="dx-result-head dx-mt-4">
                    <div class="dx-dial" style="--val: {{ $score }}">
                        <span class="dx-dial-num">{{ $score }}<small>/100</small></span>
                    </div>
                    <div class="dx-result-meta">
                        <strong>درجة مشروعك العامة</strong>
                        <p>قراءة مبنية على تحليل داخلي مباشر لمصادرك.</p>
                        <span class="dx-badge dx-badge--{{ $integrity }}">
                            مستوى الثقة:
                            @switch($integrity)
                                @case('verified') موثّق @break
                                @case('insufficient') محدود @break
                                @default جزئي
                            @endswitch
                        </span>
                    </div>
                </div>

                @if (! empty($partial['competitor_comparison']))
                    @php($cmp = $partial['competitor_comparison'])
                    <h3 class="dx-section-title">أنت مقابل منافسك</h3>
                    <div class="dx-compare">
                        <div class="dx-bar-row">
                            <span class="dx-bar-label">مشروعك</span>
                            <span class="dx-bar"><span class="dx-bar-fill" style="width: {{ (int) $cmp['you'] }}%"></span></span>
                            <span class="dx-bar-val">{{ (int) $cmp['you'] }}</span>
                        </div>
                        <div class="dx-bar-row">
                            <span class="dx-bar-label">{{ $cmp['competitor_label'] }}</span>
                            <span class="dx-bar"><span class="dx-bar-fill dx-bar-fill--muted" style="width: {{ (int) $cmp['competitor'] }}%"></span></span>
                            <span class="dx-bar-val">{{ (int) $cmp['competitor'] }}</span>
                        </div>
                    </div>
                @endif

                <h3 class="dx-section-title">أهم 3 ملاحظات</h3>
                <ul class="dx-findings">
                    @forelse (($partial['top_problems'] ?? []) as $problem)
                        <li class="dx-finding">
                            <span class="dx-dot dx-dot--{{ $problem['severity'] ?? 'medium' }}"></span>
                            <strong>{{ $problem['title'] ?? '' }}</strong>
                        </li>
                    @empty
                        <li class="dx-finding"><span class="dx-dot dx-dot--low"></span><strong>لم نرصد مشكلات حرجة في القراءة الأولية.</strong></li>
                    @endforelse
                </ul>

                @if (! empty($partial['immediate_opportunity']))
                    <div class="dx-callout">
                        <strong>فرصة فورية</strong>
                        <p>{{ $partial['immediate_opportunity'] }}</p>
                    </div>
                @endif

                <div class="dx-locked">
                    <div class="dx-locked-ghost" aria-hidden="true"><span></span><span></span><span></span></div>
                    <div class="dx-lock-icon">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 1 1 8 0v4"/></svg>
                    </div>
                    <strong>{{ (int) ($partial['locked_problems_count'] ?? 0) }} نقطة إضافية + الخطة التنفيذية مقفلة</strong>
                    <p>أنشئ حساباً مجانياً لرؤية التقرير الكامل والأولويات وخطة التنفيذ.</p>
                    <a href="{{ route('register') }}" class="dx-submit dx-btn-inline">أنشئ حسابك وأكمل من هنا</a>
                </div>
            </div>

        @else
            {{-- Still analyzing — poll the status endpoint. --}}
            <div class="dx-card dx-analyzing" id="diagnose-analyzing"
                 data-status-url="{{ route('diagnose.status', $case) }}"
                 data-reload-url="{{ route('diagnose.show', $case) }}">
                <span class="dx-chip">جارٍ التحليل</span>
                <div class="dx-spinner" aria-hidden="true"></div>
                <h2 class="dx-section-title dx-section-title--tight">نقرأ مشروعك الآن</h2>
                <p class="dx-sub">نجلب الصفحة ونحلّل إشارات الثقة والعرض. لحظات قليلة.</p>
            </div>

            <script>
                (function () {
                    var box = document.getElementById('diagnose-analyzing');
                    if (!box) return;
                    var statusUrl = box.getAttribute('data-status-url');
                    var reloadUrl = box.getAttribute('data-reload-url');
                    var tries = 0;
                    var timer = setInterval(function () {
                        tries++;
                        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (d) {
                                if (d.status === 'ready' || d.status === 'failed' || d.status === 'expired') {
                                    clearInterval(timer);
                                    window.location.href = reloadUrl;
                                }
                            })
                            .catch(function () {});
                        if (tries > 40) { clearInterval(timer); }
                    }, 3000);
                })();
            </script>
        @endif
    </div>
</section>
@endsection

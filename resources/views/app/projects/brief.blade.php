@extends('layouts.app', ['title' => 'ملف المشروع التسويقي', 'pageTitle' => 'ملف المشروع التسويقي', 'pageKicker' => $project->name])

@php
    $score = $briefAssessment['completeness_score'] ?? 0;
    $report = $briefAssessment['reports'] ?? [];
@endphp

@section('content')
<section class="app-grid app-two-col mb-8 brief-top-grid">
    <article class="card brief-score-card">
        <div class="app-section-head">
            <h3 class="heading-sm">جاهزية الملف</h3>
            <span class="app-badge">{{ $score }}%</span>
        </div>
        <div class="brief-score-track">
            <div class="brief-score-fill" style="width: {{ $score }}%"></div>
        </div>
        <p class="text-body mt-4">
            ابنِ هذا الملف مرة واحدة، ثم استخدمه في التحليل والخطة والاستوديو والمخرجات التنفيذية.
        </p>
        <div class="app-list mt-4">
            @foreach (($briefAssessment['next_actions'] ?? []) as $action)
                <div class="app-list-item">
                    <div><strong>{{ $action }}</strong></div>
                </div>
            @endforeach
        </div>
    </article>

    <article class="card brief-live-card">
        <div class="app-section-head">
            <h3 class="heading-sm">المردود الذي تبنيه الآن</h3>
        </div>
        <div class="brief-live-report" data-brief-live-report>
            <div class="brief-live-section">
                <span>Executive Brief</span>
                <ul data-brief-live-exec>
                    @forelse(($report['executive_brief'] ?? []) as $line)
                        <li>{{ $line }}</li>
                    @empty
                        <li>ابدأ بوصف النشاط والهدف حتى يظهر الملخص التنفيذي هنا.</li>
                    @endforelse
                </ul>
            </div>
            <div class="brief-live-section">
                <span>Audience Snapshot</span>
                <ul data-brief-live-audience>
                    @forelse(($report['audience_snapshot'] ?? []) as $line)
                        <li>{{ $line }}</li>
                    @empty
                        <li>أكمل العميل المثالي وألمه حتى تظهر صورة الجمهور.</li>
                    @endforelse
                </ul>
            </div>
            <div class="brief-live-section">
                <span>Offer & Positioning</span>
                <ul data-brief-live-offer>
                    @forelse(($report['offer_positioning'] ?? []) as $line)
                        <li>{{ $line }}</li>
                    @empty
                        <li>وضّح العرض والتميّز والوعد حتى تتشكل زاوية العرض.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </article>
</section>

<form method="POST" action="{{ route('projects.brief.update', $project) }}" class="app-form-grid" id="project-brief-form">
    @csrf
    @method('PUT')

    <section class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">Quick Capture</h3>
            <p class="text-caption">أسرع حقول تعطيك مردوداً في الأدوات والاستوديو.</p>
        </div>
        <div class="app-form-grid cols-2">
            <label class="app-field cols-span-2">
                <span>ما الذي يفعله المشروع باختصار؟</span>
                <textarea class="app-input" name="business[summary]" rows="4" placeholder="صف النشاط بلغة يفهمها العميل من أول قراءة." data-brief-field="business.summary">{{ old('business.summary', data_get($brief, 'business.summary')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>ما العرض الرئيسي الذي تبيعه؟</span>
                <textarea class="app-input" name="business[offer]" rows="3" placeholder="ما الذي سيأخذه العميل عملياً؟ وما النتيجة الأقرب منه؟" data-brief-field="business.offer">{{ old('business.offer', data_get($brief, 'business.offer')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>من هو العميل المثالي؟</span>
                <textarea class="app-input" name="audience[ideal_customer]" rows="3" placeholder="الشريحة الأقرب للدفع الآن، لا الجمهور العام." data-brief-field="audience.ideal_customer">{{ old('audience.ideal_customer', data_get($brief, 'audience.ideal_customer')) }}</textarea>
            </label>
            <label class="app-field">
                <span>ما الهدف الأساسي الآن؟</span>
                <input class="app-input" name="goals[primary_goal]" value="{{ old('goals.primary_goal', data_get($brief, 'goals.primary_goal')) }}" placeholder="مثال: زيادة العملاء المحتملين أو وضوح العرض." data-brief-field="goals.primary_goal">
            </label>
            <label class="app-field">
                <span>كيف ستقيس النجاح؟</span>
                <input class="app-input" name="goals[success_metric]" value="{{ old('goals.success_metric', data_get($brief, 'goals.success_metric')) }}" placeholder="مثال: عدد الاستفسارات، معدل التحويل، ROAS." data-brief-field="goals.success_metric">
            </label>
            <label class="app-field">
                <span>القنوات الحالية</span>
                <input class="app-input" name="current_marketing[channels]" value="{{ old('current_marketing.channels', data_get($brief, 'current_marketing.channels')) }}" placeholder="مثال: إنستغرام، Google Ads، واتساب." data-brief-field="current_marketing.channels">
            </label>
            <label class="app-field">
                <span>الأولوية التنفيذية الآن</span>
                <input class="app-input" name="execution[priority]" value="{{ old('execution.priority', data_get($brief, 'execution.priority')) }}" placeholder="مثال: تشخيص الفجوة أو إعادة بناء العرض." data-brief-field="execution.priority">
            </label>
        </div>
    </section>

    <section class="card">
        <div class="app-section-head">
            <h3 class="heading-sm">Deep Brief</h3>
            <p class="text-caption">هذه الطبقة تعطي التقارير والاستوديو رؤية أعمق ودقة أعلى.</p>
        </div>
        <div class="app-form-grid cols-2">
            <label class="app-field">
                <span>السوق أو الدولة المرجعية</span>
                <input class="app-input" name="business[market]" value="{{ old('business.market', data_get($brief, 'business.market')) }}" placeholder="مثال: السعودية، الخليج، مصر." data-brief-field="business.market">
            </label>
            <label class="app-field">
                <span>الإطار الزمني للهدف</span>
                <input class="app-input" name="goals[timeframe]" value="{{ old('goals.timeframe', data_get($brief, 'goals.timeframe')) }}" placeholder="مثال: 30 يوم، ربع سنوي." data-brief-field="goals.timeframe">
            </label>
            <label class="app-field cols-span-2">
                <span>ما أهم ألم أو حاجة لدى الجمهور؟</span>
                <textarea class="app-input" name="audience[pain_points]" rows="3" placeholder="ما الشيء الذي يدفعه ليبحث عن حل الآن؟" data-brief-field="audience.pain_points">{{ old('audience.pain_points', data_get($brief, 'audience.pain_points')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>ما الذي يدفعه لاتخاذ قرار الشراء؟</span>
                <textarea class="app-input" name="audience[buying_trigger]" rows="3" placeholder="مثال: يريد نتيجة سريعة، يخاف من إهدار الميزانية، يريد دليلًا واضحاً." data-brief-field="audience.buying_trigger">{{ old('audience.buying_trigger', data_get($brief, 'audience.buying_trigger')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>كيف تصف وضع التسويق الحالي؟</span>
                <textarea class="app-input" name="current_marketing[current_state]" rows="3" placeholder="ما الذي يعمل؟ ما الذي لا يعمل؟ وما الشيء غير الواضح الآن؟" data-brief-field="current_marketing.current_state">{{ old('current_marketing.current_state', data_get($brief, 'current_marketing.current_state')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>الأصول المتوفرة حالياً</span>
                <textarea class="app-input" name="current_marketing[assets]" rows="3" placeholder="هوية، موقع، محتوى، حالات، صور، فيديو، عروض..." data-brief-field="current_marketing.assets">{{ old('current_marketing.assets', data_get($brief, 'current_marketing.assets')) }}</textarea>
            </label>
            <label class="app-field">
                <span>صوت العلامة</span>
                <input class="app-input" name="brand[voice]" value="{{ old('brand.voice', data_get($brief, 'brand.voice')) }}" placeholder="مثال: مباشر، احترافي، واثق، قريب." data-brief-field="brand.voice">
            </label>
            <label class="app-field">
                <span>قواعد النبرة أو ما يجب تجنبه</span>
                <input class="app-input" name="brand[tone_rules]" value="{{ old('brand.tone_rules', data_get($brief, 'brand.tone_rules')) }}" placeholder="مثال: بدون مبالغة، بدون لغة فضفاضة." data-brief-field="brand.tone_rules">
            </label>
            <label class="app-field cols-span-2">
                <span>ميزة التمركز أو الزاوية التي تميّزك</span>
                <textarea class="app-input" name="positioning[edge]" rows="3" placeholder="ما الفرق الحقيقي الذي يجب أن يراه العميل فيك؟" data-brief-field="positioning.edge">{{ old('positioning.edge', data_get($brief, 'positioning.edge')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>الوعد أو النتيجة التي تعد بها</span>
                <textarea class="app-input" name="positioning[promise]" rows="3" placeholder="ما النتيجة التي تَعِد بها دون مبالغة؟" data-brief-field="positioning.promise">{{ old('positioning.promise', data_get($brief, 'positioning.promise')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>أهم المنافسين أو البدائل</span>
                <textarea class="app-input" name="competition[competitors]" rows="3" placeholder="أسماء منافسين أو بدائل أو حلول أخرى يقارن بها العميل." data-brief-field="competition.competitors">{{ old('competition.competitors', data_get($brief, 'competition.competitors')) }}</textarea>
            </label>
            <label class="app-field cols-span-2">
                <span>ما الفجوة التي تراها في السوق؟</span>
                <textarea class="app-input" name="competition[gap]" rows="3" placeholder="أين المساحة البيضاء التي يمكن أن تتميز فيها؟" data-brief-field="competition.gap">{{ old('competition.gap', data_get($brief, 'competition.gap')) }}</textarea>
            </label>
            <label class="app-field">
                <span>ما الأصل التالي الذي تحتاجه المنصة أن تبنيه لك؟</span>
                <input class="app-input" name="execution[next_asset]" value="{{ old('execution.next_asset', data_get($brief, 'execution.next_asset')) }}" placeholder="مثال: تشخيص، عرض، خطة محتوى، حملة." data-brief-field="execution.next_asset">
            </label>
            <label class="app-field">
                <span>نطاق الميزانية</span>
                <input class="app-input" name="commercial[budget_range]" value="{{ old('commercial.budget_range', data_get($brief, 'commercial.budget_range')) }}" placeholder="مثال: 5k-10k شهرياً." data-brief-field="commercial.budget_range">
            </label>
            <label class="app-field">
                <span>صاحب القرار</span>
                <input class="app-input" name="commercial[decision_maker]" value="{{ old('commercial.decision_maker', data_get($brief, 'commercial.decision_maker')) }}" placeholder="الاسم أو الصفة." data-brief-field="commercial.decision_maker">
            </label>
            <label class="app-field cols-span-2">
                <span>ملاحظات تسليم أو تنفيذ</span>
                <textarea class="app-input" name="execution[delivery_notes]" rows="3" placeholder="أي شيء يجب أن تعرفه الأدوات أو الاستوديو قبل التوليد والتنفيذ." data-brief-field="execution.delivery_notes">{{ old('execution.delivery_notes', data_get($brief, 'execution.delivery_notes')) }}</textarea>
            </label>
        </div>
    </section>

    <div class="app-form-actions">
        <button type="submit" class="btn btn-primary btn-xl">حفظ ملف المشروع التسويقي</button>
        <a href="{{ route('projects.show', $project) }}" class="btn btn-ghost btn-xl">رجوع</a>
    </div>
</form>
@endsection

@push('head')
<style>
    .brief-top-grid { align-items: stretch; }
    .brief-score-track { height: 12px; background: var(--surface-3); border-radius: 999px; overflow: hidden; }
    .brief-score-fill { height: 100%; background: linear-gradient(90deg, var(--p), var(--p2)); border-radius: 999px; }
    .brief-live-report { display: grid; gap: 14px; }
    .brief-live-section { border: 1px solid var(--border); border-radius: var(--r-lg); padding: 14px 16px; background: var(--surface-2); }
    .brief-live-section span { display: block; font-size: var(--fs-xs); font-weight: 800; color: var(--p); margin-bottom: 8px; letter-spacing: .04em; text-transform: uppercase; }
    .brief-live-section ul { margin: 0; padding-inline-start: 1rem; display: grid; gap: 6px; }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const form = document.getElementById('project-brief-form');
    if (!form) return;

    const renderList = (selector, items, fallback) => {
        const root = document.querySelector(selector);
        if (!root) return;
        const values = items.filter(Boolean);
        root.innerHTML = '';
        (values.length ? values : [fallback]).forEach((item) => {
            const li = document.createElement('li');
            li.textContent = item;
            root.appendChild(li);
        });
    };

    const read = (name) => (form.querySelector(`[data-brief-field="${name}"]`)?.value || '').trim();

    const update = () => {
        renderList('[data-brief-live-exec]', [
            read('business.summary') ? `النشاط: ${read('business.summary')}` : '',
            read('goals.primary_goal') ? `الهدف: ${read('goals.primary_goal')}` : '',
            read('execution.priority') ? `الأولوية: ${read('execution.priority')}` : '',
            read('commercial.budget_range') ? `الميزانية: ${read('commercial.budget_range')}` : '',
        ], 'ابدأ بوصف النشاط والهدف حتى يظهر الملخص التنفيذي هنا.');

        renderList('[data-brief-live-audience]', [
            read('audience.ideal_customer'),
            read('audience.pain_points'),
            read('audience.buying_trigger'),
        ], 'أكمل العميل المثالي وألمه حتى تظهر صورة الجمهور.');

        renderList('[data-brief-live-offer]', [
            read('business.offer'),
            read('positioning.edge'),
            read('positioning.promise'),
        ], 'وضّح العرض والتميّز والوعد حتى تتشكل زاوية العرض.');
    };

    form.addEventListener('input', update);
    update();
})();
</script>
@endpush

@extends('layouts.app', ['title' => 'التهيئة الأولى', 'pageTitle' => 'مرحباً بك', 'pageKicker' => 'Onboarding'])

@section('content')

{{-- ── Onboarding Wizard ────────────────────────────────────────── --}}
<div class="onb-wizard" id="onb-wizard">

    {{-- Progress Steps --}}
    <div class="onb-steps">
        <div class="onb-step onb-step--active" data-step-indicator="1">
            <span class="onb-step-dot"></span>
            <span class="onb-step-label">وضعك الآن</span>
        </div>
        <div class="onb-steps-line"></div>
        <div class="onb-step" data-step-indicator="2">
            <span class="onb-step-dot"></span>
            <span class="onb-step-label">أول مشروع</span>
        </div>
        <div class="onb-steps-line"></div>
        <div class="onb-step" data-step-indicator="3">
            <span class="onb-step-dot"></span>
            <span class="onb-step-label">تفاصيل إضافية</span>
        </div>
    </div>

    <form method="POST" action="{{ route('onboarding.store') }}" id="onb-form">
        @csrf

        @if ($errors->any())
            <div class="alert alert-error mb-6" role="alert">
                <strong class="d-block mb-2">تعذر إكمال التهيئة حالياً.</strong>
                <ul class="list-reset">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── STEP 1: من أنت؟ (٣ أسئلة فقط) ──────────────────── --}}
        <section class="onb-panel card" data-step="1">
            <div class="onb-panel-hero">
                <div class="section-badge mb-4">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">الخطوة الأولى من ٣</span>
                </div>
                <h2 class="heading-lg mb-3">ما أكبر تحدٍ تواجهه الآن <span class="text-gradient">في التسويق؟</span></h2>
                <p class="text-body-lg mb-8">إجابتك تحدد المسار الأنسب لك وتُهيّئ المنصة حسب وضعك الفعلي.</p>
            </div>

            {{-- Challenge Picker Cards --}}
            <div class="onb-challenge-grid">
                <label class="onb-challenge-card {{ old('_challenge_hint') === 'no_message' ? 'is-selected' : '' }}" data-challenge="no_message">
                    <input type="radio" name="_challenge_hint" value="no_message" class="onb-hidden-radio" @checked(old('_challenge_hint') === 'no_message')>
                    <div class="onb-challenge-icon">💬</div>
                    <h3 class="onb-challenge-title">رسالتي غير واضحة</h3>
                    <p class="onb-challenge-body">لا أستطيع شرح ما أقدمه بشكل يُقنع العميل بسرعة</p>
                </label>
                <label class="onb-challenge-card {{ old('_challenge_hint') === 'no_audience' ? 'is-selected' : '' }}" data-challenge="no_audience">
                    <input type="radio" name="_challenge_hint" value="no_audience" class="onb-hidden-radio" @checked(old('_challenge_hint') === 'no_audience')>
                    <div class="onb-challenge-icon">🎯</div>
                    <h3 class="onb-challenge-title">لا أعرف جمهوري بدقة</h3>
                    <p class="onb-challenge-body">أُسوّق للجميع وأحصل على نتائج ضعيفة</p>
                </label>
                <label class="onb-challenge-card {{ old('_challenge_hint') === 'no_plan' ? 'is-selected' : '' }}" data-challenge="no_plan">
                    <input type="radio" name="_challenge_hint" value="no_plan" class="onb-hidden-radio" @checked(old('_challenge_hint') === 'no_plan')>
                    <div class="onb-challenge-icon">🗺️</div>
                    <h3 class="onb-challenge-title">لا أعرف من أين أبدأ</h3>
                    <p class="onb-challenge-body">أقرأ كثيراً لكن لا أتخذ خطوة واضحة</p>
                </label>
                <label class="onb-challenge-card {{ old('_challenge_hint') === 'no_conversion' ? 'is-selected' : '' }}" data-challenge="no_conversion">
                    <input type="radio" name="_challenge_hint" value="no_conversion" class="onb-hidden-radio" @checked(old('_challenge_hint') === 'no_conversion')>
                    <div class="onb-challenge-icon">📈</div>
                    <h3 class="onb-challenge-title">أبيع لكن التحويل ضعيف</h3>
                    <p class="onb-challenge-body">عندي زيارات أو متابعون لكن المبيعات لا تتناسب</p>
                </label>
                <label class="onb-challenge-card {{ old('_challenge_hint') === 'agency_manage' ? 'is-selected' : '' }}" data-challenge="agency_manage">
                    <input type="radio" name="_challenge_hint" value="agency_manage" class="onb-hidden-radio" @checked(old('_challenge_hint') === 'agency_manage')>
                    <div class="onb-challenge-icon">🏢</div>
                    <h3 class="onb-challenge-title">أُدير عملاء متعددين</h3>
                    <p class="onb-challenge-body">أحتاج نظاماً لتنظيم العمل مع عملائي</p>
                </label>
                <label class="onb-challenge-card {{ old('_challenge_hint') === 'other' ? 'is-selected' : '' }}" data-challenge="other">
                    <input type="radio" name="_challenge_hint" value="other" class="onb-hidden-radio" @checked(old('_challenge_hint') === 'other')>
                    <div class="onb-challenge-icon">✨</div>
                    <h3 class="onb-challenge-title">شيء آخر</h3>
                    <p class="onb-challenge-body">لديّ تحدٍ مختلف وسأحدده لاحقاً</p>
                </label>
            </div>

            {{-- ٣ حقول فقط في الخطوة الأولى --}}
            <div class="onb-core-fields mt-8">
                <div class="app-form-grid cols-2">
                    <label class="app-field">
                        <span>اسمك أو اسم حسابك</span>
                        <input class="app-input" name="account_name" value="{{ old('account_name', $workspace->account?->name ?? auth()->user()->name) }}" required placeholder="مثال: خالد سعد">
                    </label>
                    <label class="app-field">
                        <span>دولتك أو سوقك الأساسي</span>
                        <input class="app-input" name="country" value="{{ old('country', $profile['country'] ?? '') }}" required placeholder="مثال: السعودية، مصر، الإمارات">
                    </label>
                    <label class="app-field cols-span-2">
                        <span>أنت تعمل كـ</span>
                        <div class="onb-persona-row">
                            @foreach ($personas as $key => $label)
                                <label class="onb-persona-chip {{ old('persona', $defaults['persona']) === $key ? 'is-selected' : '' }}">
                                    <input type="radio" name="persona" value="{{ $key }}" @checked(old('persona', $defaults['persona']) === $key) required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </label>
                </div>
            </div>

            {{-- Hidden fields with smart defaults --}}
            <input type="hidden" name="workspace_name" value="{{ old('workspace_name', $workspace->name) }}">
            <input type="hidden" name="workspace_type" value="{{ old('workspace_type', $workspace->type ?? 'personal') }}">
            <input type="hidden" name="awareness_level" id="auto-awareness" value="{{ old('awareness_level', $defaults['awareness_level']) }}">
            <input type="hidden" name="primary_goal" id="auto-goal" value="{{ old('primary_goal', $defaults['primary_goal']) }}">
            <input type="hidden" name="recommended_path" id="auto-path" value="{{ old('recommended_path', $defaults['recommended_path']) }}">
            <input type="hidden" name="audience" id="auto-audience" value="{{ old('audience', $defaults['audience']) }}">
            <input type="hidden" name="content_locale" id="auto-locale" value="{{ old('content_locale', $defaults['content_locale']) }}">
            <input type="hidden" name="current_challenge" id="auto-challenge" value="{{ old('current_challenge', $profile['current_challenge'] ?? '') }}">

            <div class="onb-panel-actions">
                <button type="button" class="btn btn-primary btn-xl" data-onb-next="2">
                    متابعة — إنشاء أول مشروع
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </button>
                <p class="onb-skip-note">
                    <a href="#" data-onb-skip class="text-caption">تخطّ هذه الخطوة وابدأ مباشرة ←</a>
                </p>
            </div>
        </section>

        {{-- ── STEP 2: أول مشروع ──────────────────────────────── --}}
        <section class="onb-panel card" data-step="2" hidden>
            <div class="onb-panel-hero">
                <div class="section-badge mb-4">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">الخطوة الثانية من ٣</span>
                </div>
                <h2 class="heading-lg mb-3">أنشئ <span class="text-gradient">أول مشروع</span> داخل المنصة</h2>
                <p class="text-body-lg mb-6">المشروع هو الوعاء الذي تُحفظ فيه كل مخرجات الأدوات. يمكنك تعديله لاحقاً في أي وقت.</p>
            </div>

            <div class="onb-project-preview card card-nested mb-6">
                <div class="onb-preview-icon">🚀</div>
                <div>
                    <p class="text-caption mb-1">بعد إنشاء المشروع ستصل مباشرة للأداة الأولى في مسارك</p>
                    <strong class="text-body">الخطوة الأولى: تحليل وضع مشروعك الحالي</strong>
                </div>
            </div>

            <div class="app-form-grid cols-2">
                <label class="app-field">
                    <span>اسم العميل أو جهة العمل</span>
                    <input class="app-input" name="client_name" value="{{ old('client_name') }}" required placeholder="مثال: مؤسسة المسار الرقمي، أو اسمك إن كنت العميل">
                </label>
                <label class="app-field">
                    <span>اسم المشروع</span>
                    <input class="app-input" name="project_name" value="{{ old('project_name') }}" required placeholder="مثال: إطلاق خدمة الاستشارات">
                </label>
                    <label class="app-field cols-span-2">
                        <span>أين أنت الآن في هذا المشروع؟</span>
                        <div class="onb-stage-row">
                            @foreach ([1 => 'في البداية — أبني الأساس', 2 => 'أطوّر عرضي وقيمتي', 3 => 'أنمّي وأوسّع', 4 => 'أُطلق وأقيس', 5 => 'أُحكم وأتوسع'] as $stageNum => $stageDesc)
                                <label class="onb-stage-chip {{ old('project_stage', 1) == $stageNum ? 'is-selected' : '' }}">
                                    <input type="radio" name="project_stage" value="{{ $stageNum }}" @checked(old('project_stage', 1) == $stageNum) required>
                                    <span class="onb-stage-num">{{ $stageNum }}</span>
                                    <span class="onb-stage-desc">{{ $stageDesc }}</span>
                                </label>
                        @endforeach
                    </div>
                </label>
            </div>

            <div class="onb-panel-actions">
                <button type="button" class="btn btn-ghost btn-lg" data-onb-back="1">← رجوع</button>
                <button type="button" class="btn btn-primary btn-xl" data-onb-next="3">
                    متابعة
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </button>
            </div>
        </section>

        {{-- ── STEP 3: تفاصيل إضافية (اختيارية) ─────────────── --}}
        <section class="onb-panel card" data-step="3" hidden>
            <div class="onb-panel-hero">
                <div class="section-badge mb-4">
                    <span class="section-dot"></span>
                    <span class="section-badge-text">الخطوة الأخيرة — اختيارية</span>
                </div>
                <h2 class="heading-lg mb-3">تفاصيل <span class="text-gradient">تُحسّن دقة الذكاء الاصطناعي</span></h2>
                <p class="text-body-lg mb-2">هذه المعلومات تُستخدم عند توليد النصوص والمسودات. يمكنك تخطيها والبدء الآن.</p>
                <p class="text-caption mb-6">تستطيع تعديل كل هذا لاحقاً من إعدادات الحساب.</p>
            </div>

            <div class="onb-brief-preview card card-nested mb-6">
                <div class="onb-preview-icon">📄</div>
                <div>
                    <p class="text-caption mb-1">ما الذي تبنيه الآن؟</p>
                    <strong class="text-body">ملف مشروع تسويقي حيّ يُستخدم في التحليل والاستوديو والخطة.</strong>
                </div>
            </div>

            {{-- ٣ حقول أساسية فقط — كل ما عداها اختياري ومطوي --}}
            <div class="app-form-grid cols-2 mb-6">
                <label class="app-field cols-span-2">
                    <span>اشرح النشاط أو المشروع باختصار</span>
                    <textarea class="app-input" name="brief_business_summary" rows="2" placeholder="ما الذي يفعله المشروع؟ ولماذا يهم العميل؟">{{ old('brief_business_summary') }}</textarea>
                </label>
                <label class="app-field">
                    <span>الدومين الأساسي</span>
                    <input class="app-input" name="primary_domain" value="{{ old('primary_domain') }}" placeholder="example.com أو https://example.com">
                </label>
                <label class="app-field">
                    <span>القطاع</span>
                    <select class="app-input" name="sector">
                        @foreach ($sectorOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('sector', 'general_business') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <details class="onb-advanced-details">
                <summary class="onb-advanced-summary">
                    <span>تفاصيل إضافية تُحسّن دقة النتائج (اختياري)</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="app-form-grid cols-2 mt-6">
                    <label class="app-field cols-span-2">
                        <span>ما العرض الرئيسي الذي تريد بيعه؟</span>
                        <textarea class="app-input" name="brief_offer" rows="2" placeholder="مثال: خدمة أو عرض أو نتيجة واضحة تريد بيعها.">{{ old('brief_offer') }}</textarea>
                    </label>
                    <label class="app-field cols-span-2">
                        <span>من هو العميل الأقرب لك الآن؟</span>
                        <textarea class="app-input" name="brief_ideal_customer" rows="2" placeholder="الشريحة الأقرب للدفع الآن.">{{ old('brief_ideal_customer', $profile['audience'] ?? '') }}</textarea>
                    </label>
                    <label class="app-field">
                        <span>ما الهدف الأقرب خلال الفترة القادمة؟</span>
                        <input class="app-input" name="brief_primary_goal" value="{{ old('brief_primary_goal') }}" placeholder="مثال: زيادة الاستفسارات أو وضوح العرض">
                    </label>
                    <label class="app-field">
                        <span>كيف ستقيس النجاح؟</span>
                        <input class="app-input" name="brief_success_metric" value="{{ old('brief_success_metric') }}" placeholder="مثال: عدد العملاء أو التحويل">
                    </label>
                    <label class="app-field">
                        <span>القنوات الحالية</span>
                        <input class="app-input" name="brief_current_channels" value="{{ old('brief_current_channels') }}" placeholder="مثال: إنستغرام، واتساب، موقع">
                    </label>
                    <label class="app-field">
                        <span>ما الأولوية التنفيذية الآن؟</span>
                        <input class="app-input" name="brief_priority" value="{{ old('brief_priority', $profile['current_challenge'] ?? '') }}" placeholder="مثال: تشخيص الفجوة أو صياغة العرض">
                    </label>
                    <label class="app-field cols-span-2">
                        <span>الروابط الرسمية للسوشيال</span>
                        <textarea class="app-input" name="official_social_links" rows="3" placeholder="ضع رابطاً واحداً في كل سطر">{{ old('official_social_links') }}</textarea>
                    </label>
                    <label class="app-field cols-span-2">
                        <span>المنافسون</span>
                        <textarea class="app-input" name="competitors" rows="3" placeholder="اسم أو دومين كل منافس في سطر">{{ old('competitors') }}</textarea>
                    </label>
                    <label class="app-field cols-span-2">
                        <span>أهداف التحليل</span>
                        <textarea class="app-input" name="analysis_goals" rows="3" placeholder="مثال: تقييم الموقع&#10;تحليل السوشيال&#10;مقارنة المنافسين">{{ old('analysis_goals') }}</textarea>
                    </label>
                    <label class="app-field cols-span-2">
                        <span class="onb-persona-row">
                            <label class="onb-persona-chip is-selected">
                                <input type="checkbox" name="monitoring_enabled" value="1" @checked(old('monitoring_enabled'))>
                                <span>فعّل المراقبة الدورية لهذا المشروع من البداية</span>
                            </label>
                        </span>
                    </label>
                    <label class="app-field">
                        <span>اسم مساحة العمل</span>
                        <input class="app-input" name="_workspace_name_display" value="{{ old('_workspace_name_display', $workspace->name) }}"
                            placeholder="مثال: مساحة الاستشارات"
                            oninput="document.querySelector('[name=workspace_name]').value=this.value">
                    </label>
                    <label class="app-field">
                        <span>نوع مساحة العمل</span>
                        <select class="app-input" onchange="document.querySelector('[name=workspace_type]').value=this.value">
                            @foreach (['personal' => 'شخصية', 'team' => 'فريق', 'agency' => 'وكالة'] as $type => $label)
                                <option value="{{ $type }}" @selected(old('workspace_type', $workspace->type) === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="app-field">
                        <span>مستوى معرفتك بالتسويق</span>
                        <select class="app-input" onchange="document.getElementById('auto-awareness').value=this.value">
                            @foreach ($awarenessLevels as $key => $label)
                                <option value="{{ $key }}" @selected(old('awareness_level', $profile['awareness_level'] ?? 'aware') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="app-field">
                        <span>هدفك الرئيسي الآن</span>
                        <select class="app-input" onchange="document.getElementById('auto-goal').value=this.value">
                            @foreach ($goals as $key => $label)
                                <option value="{{ $key }}" @selected(old('primary_goal', $profile['primary_goal'] ?? 'clarify_message') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="app-field">
                        <span>المسار المفضل</span>
                        <select class="app-input" onchange="document.getElementById('auto-path').value=this.value">
                            <option value="">تحديد تلقائي (موصى به)</option>
                            @foreach ($paths as $key => $label)
                                <option value="{{ $key }}" @selected(old('recommended_path', $profile['recommended_path'] ?? null) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="app-field">
                        <span>لهجة المحتوى</span>
                        <select class="app-input" onchange="document.getElementById('auto-locale').value=this.value">
                            @foreach ($contentLocales as $key => $label)
                                <option value="{{ $key }}" @selected(old('content_locale', $profile['content_locale'] ?? 'ar_modern_fusha') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="app-field cols-span-2">
                        <span>جمهورك المستهدف</span>
                        <input class="app-input" placeholder="مثال: رواد أعمال في السعودية، أصحاب المشاريع الصغيرة"
                            oninput="document.getElementById('auto-audience').value=this.value"
                            value="{{ old('audience', $profile['audience'] ?? '') }}">
                    </label>
                    <label class="app-field cols-span-2">
                        <span>أكبر تحدٍ تواجهه الآن (بكلماتك)</span>
                        <input class="app-input" placeholder="مثال: الرسالة غير واضحة، أو الزبائن لا يفهمون قيمة خدمتي"
                            oninput="document.getElementById('auto-challenge').value=this.value"
                            value="{{ old('current_challenge', $profile['current_challenge'] ?? '') }}">
                    </label>
                </div>
            </details>

            <div class="onb-panel-actions mt-8">
                <button type="button" class="btn btn-ghost btn-lg" data-onb-back="2">← رجوع</button>
                <button type="submit" class="btn btn-primary btn-xl">
                    ابدأ رحلتك الآن
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </button>
            </div>
        </section>

    </form>
</div>

{{-- Value strip below wizard --}}
<div class="onb-value-strip mt-6">
    <div class="onb-value-item">
        <span class="onb-value-icon">🎯</span>
        <span>٢٦ أداة مرتّبة في ٥ مراحل</span>
    </div>
    <div class="onb-value-item">
        <span class="onb-value-icon">🧠</span>
        <span>ذكاء اصطناعي يعرف سياق مشروعك</span>
    </div>
    <div class="onb-value-item">
        <span class="onb-value-icon">⚡</span>
        <span>كل أداة تُغذي التالية — لا تكرار</span>
    </div>
    <div class="onb-value-item">
        <span class="onb-value-icon">🔒</span>
        <span>بياناتك محفوظة ومرتّبة دائماً</span>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // Challenge → auto-set hidden fields
    const challengeMap = {
        no_message:    { goal: 'build_offer',        challenge: 'الرسالة التسويقية غير واضحة' },
        no_audience:   { goal: 'improve_marketing',  challenge: 'الجمهور المستهدف غير محدد بدقة' },
        no_plan:       { goal: 'build_90_day_plan',  challenge: 'لا توجد خطة أو مسار واضح' },
        no_conversion: { goal: 'get_first_customers', challenge: 'معدل التحويل ضعيف رغم وجود زيارات' },
        agency_manage: { goal: 'build_90_day_plan',  challenge: 'إدارة عملاء متعددين وتنظيم العمل' },
        other:         { goal: '{{ $defaults['primary_goal'] }}', challenge: '' },
    };

    document.querySelectorAll('.onb-challenge-card').forEach(function(card) {
        card.addEventListener('click', function() {
            document.querySelectorAll('.onb-challenge-card').forEach(c => c.classList.remove('is-selected'));
            card.classList.add('is-selected');
            const radio = card.querySelector('input[type=radio]');
            if (radio) radio.checked = true;
            const map = challengeMap[card.dataset.challenge] || {};
            if (map.goal) document.getElementById('auto-goal').value = map.goal;
            if (map.challenge) document.getElementById('auto-challenge').value = map.challenge;
        });
    });

    // Persona chips
    document.querySelectorAll('.onb-persona-chip input').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.onb-persona-chip').forEach(c => c.classList.remove('is-selected'));
            radio.closest('.onb-persona-chip').classList.add('is-selected');
            // Auto workspace type
            if (radio.value === 'agency') document.querySelector('[name=workspace_type]').value = 'agency';
            else if (radio.value === 'freelancer') document.querySelector('[name=workspace_type]').value = 'personal';
        });
    });

    // Stage chips
    document.querySelectorAll('.onb-stage-chip input').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.onb-stage-chip').forEach(c => c.classList.remove('is-selected'));
            radio.closest('.onb-stage-chip').classList.add('is-selected');
        });
    });

    // Step navigation
    function validateStep(step) {
        const panel = document.querySelector('[data-step="' + step + '"]');
        if (!panel) return true;

        const fields = Array.from(panel.querySelectorAll('input, select, textarea'))
            .filter(function(field) {
                return field.type !== 'hidden' && !field.disabled;
            });

        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }

        return true;
    }

    function showStep(n) {
        document.querySelectorAll('.onb-panel').forEach(p => p.hidden = true);
        const panel = document.querySelector('[data-step="' + n + '"]');
        if (panel) panel.hidden = false;
        document.querySelectorAll('[data-step-indicator]').forEach(function(el) {
            el.classList.toggle('onb-step--active', parseInt(el.dataset.stepIndicator) === n);
            el.classList.toggle('onb-step--done', parseInt(el.dataset.stepIndicator) < n);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.querySelectorAll('[data-onb-next]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const currentStep = parseInt(btn.closest('[data-step]').dataset.step, 10);
            if (!validateStep(currentStep)) return;
            showStep(parseInt(btn.dataset.onbNext, 10));
        });
    });
    document.querySelectorAll('[data-onb-back]').forEach(function(btn) {
        btn.addEventListener('click', function() { showStep(parseInt(btn.dataset.onbBack)); });
    });

    // Skip — fill minimal defaults and submit
    document.querySelectorAll('[data-onb-skip]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            const form = document.getElementById('onb-form');
            // Fill required fields with defaults if empty
            if (!form.account_name.value) form.account_name.value = '{{ auth()->user()->name }}';
            if (!form.country.value) form.country.value = 'غير محدد';
            // Ensure persona selected
            const personaChecked = form.querySelector('input[name=persona]:checked');
            if (!personaChecked) form.querySelector('input[name=persona]').checked = true;
            // Set defaults for step 2 required fields
            if (!form.client_name.value) form.client_name.value = '{{ auth()->user()->name }}';
            if (!form.project_name.value) form.project_name.value = 'مشروعي الأول';
            const stageChecked = form.querySelector('input[name=project_stage]:checked');
            if (!stageChecked) form.querySelector('input[name=project_stage]').checked = true;
            form.submit();
        });
    });

    // Mark first persona as selected visually
    const firstPersona = document.querySelector('.onb-persona-chip input:checked');
    if (firstPersona) firstPersona.closest('.onb-persona-chip').classList.add('is-selected');
    const firstStage = document.querySelector('.onb-stage-chip input:checked');
    if (firstStage) firstStage.closest('.onb-stage-chip').classList.add('is-selected');
    const selectedChallenge = document.querySelector('.onb-challenge-card input:checked');
    if (selectedChallenge) selectedChallenge.closest('.onb-challenge-card').classList.add('is-selected');
})();
</script>
@endpush

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
                <label class="onb-challenge-card" data-challenge="no_message">
                    <input type="radio" name="_challenge_hint" value="no_message" class="onb-hidden-radio">
                    <div class="onb-challenge-icon">💬</div>
                    <h3 class="onb-challenge-title">رسالتي غير واضحة</h3>
                    <p class="onb-challenge-body">لا أستطيع شرح ما أقدمه بشكل يُقنع العميل بسرعة</p>
                </label>
                <label class="onb-challenge-card" data-challenge="no_audience">
                    <input type="radio" name="_challenge_hint" value="no_audience" class="onb-hidden-radio">
                    <div class="onb-challenge-icon">🎯</div>
                    <h3 class="onb-challenge-title">لا أعرف جمهوري بدقة</h3>
                    <p class="onb-challenge-body">أُسوّق للجميع وأحصل على نتائج ضعيفة</p>
                </label>
                <label class="onb-challenge-card" data-challenge="no_plan">
                    <input type="radio" name="_challenge_hint" value="no_plan" class="onb-hidden-radio">
                    <div class="onb-challenge-icon">🗺️</div>
                    <h3 class="onb-challenge-title">لا أعرف من أين أبدأ</h3>
                    <p class="onb-challenge-body">أقرأ كثيراً لكن لا أتخذ خطوة واضحة</p>
                </label>
                <label class="onb-challenge-card" data-challenge="no_conversion">
                    <input type="radio" name="_challenge_hint" value="no_conversion" class="onb-hidden-radio">
                    <div class="onb-challenge-icon">📈</div>
                    <h3 class="onb-challenge-title">أبيع لكن التحويل ضعيف</h3>
                    <p class="onb-challenge-body">عندي زيارات أو متابعون لكن المبيعات لا تتناسب</p>
                </label>
                <label class="onb-challenge-card" data-challenge="agency_manage">
                    <input type="radio" name="_challenge_hint" value="agency_manage" class="onb-hidden-radio">
                    <div class="onb-challenge-icon">🏢</div>
                    <h3 class="onb-challenge-title">أُدير عملاء متعددين</h3>
                    <p class="onb-challenge-body">أحتاج نظاماً لتنظيم العمل مع عملائي</p>
                </label>
                <label class="onb-challenge-card" data-challenge="other">
                    <input type="radio" name="_challenge_hint" value="other" class="onb-hidden-radio">
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
                                <label class="onb-persona-chip">
                                    <input type="radio" name="persona" value="{{ $key }}" @checked(old('persona', $profile['persona'] ?? null) === $key) required>
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
            <input type="hidden" name="awareness_level" id="auto-awareness" value="{{ old('awareness_level', $profile['awareness_level'] ?? 'aware') }}">
            <input type="hidden" name="primary_goal" id="auto-goal" value="{{ old('primary_goal', $profile['primary_goal'] ?? 'clarify_message') }}">
            <input type="hidden" name="recommended_path" id="auto-path" value="{{ old('recommended_path', $profile['recommended_path'] ?? '') }}">
            <input type="hidden" name="audience" id="auto-audience" value="{{ old('audience', $profile['audience'] ?? 'عملاء مناسبون لخدمتي') }}">
            <input type="hidden" name="content_locale" id="auto-locale" value="{{ old('content_locale', $profile['content_locale'] ?? 'ar_modern_fusha') }}">
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
                    <strong class="text-body">الخطوة الأولى: تشخيص وضع مشروعك الحالي</strong>
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
                            <label class="onb-stage-chip">
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

            <details class="onb-advanced-details">
                <summary class="onb-advanced-summary">
                    <span>عرض التفاصيل الإضافية (اختياري)</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="app-form-grid cols-2 mt-6">
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
        no_message:    { goal: 'clarify_message',    challenge: 'الرسالة التسويقية غير واضحة' },
        no_audience:   { goal: 'define_audience',    challenge: 'الجمهور المستهدف غير محدد بدقة' },
        no_plan:       { goal: 'build_strategy',     challenge: 'لا توجد خطة أو مسار واضح' },
        no_conversion: { goal: 'increase_sales',     challenge: 'معدل التحويل ضعيف رغم وجود زيارات' },
        agency_manage: { goal: 'manage_clients',     challenge: 'إدارة عملاء متعددين وتنظيم العمل' },
        other:         { goal: 'clarify_message',    challenge: '' },
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
        btn.addEventListener('click', function() { showStep(parseInt(btn.dataset.onbNext)); });
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
})();
</script>
@endpush

<?php
/**
 * diagnostics.php
 * الواجهة الأمامية - الإصدار 4.0 (AI-Ready)
 * - إزالة الأسئلة الحساسة (VAT, نطاقات, العائلة).
 * - دعم المصدر الجديد.
 */

require_once dirname(__DIR__) . '/includes/init.php';

 $pageTitle = 'التشخيص الاستراتيجي الذكي - ' . SITE_NAME;
 $pageDescription = 'أداة تشخيص مدعومة بذكاء اصطناعي لتحليل أعمالك.';

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- CSS (كما في السابق مع إضافات بسيطة) -->
<style>
.diagnostic-wizard-section { padding: var(--space-20) 0; background-color: var(--bg-secondary); min-height: 80vh; }
.step-container { max-width: 900px; margin: 0 auto; }
.steps-nav { display: flex; justify-content: center; gap: var(--space-4); margin-bottom: var(--space-12); }
.step-nav-item { padding: var(--space-3) var(--space-6); background: var(--bg-tertiary); border-radius: var(--radius-full); font-weight: 700; font-size: var(--font-size-sm); color: var(--text-muted); transition: var(--transition); cursor: default; }
.step-nav-item.active { background: var(--primary); color: white; box-shadow: var(--shadow-glow); }
.diag-option { background: var(--bg-card); border: 2px solid var(--border-color); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4); cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: var(--space-4); position: relative; }
.diag-option:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-md); background: var(--primary-ultra-light); }
.diag-option.selected { border-color: var(--primary); background: var(--primary-ultra-light); box-shadow: inset 4px 0 0 var(--primary); }
.diag-option-text { font-weight: 600; color: var(--text-primary); flex:1; }

.diag-grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
.diag-checkbox-option { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 15px; cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: 10px; font-size: 0.95rem; }
.diag-checkbox-option:hover { border-color: var(--primary); background: var(--primary-ultra-light); }
.diag-checkbox-option.selected { border-color: var(--primary); background: var(--primary-ultra-light); color: var(--primary); font-weight: bold; border:1px solid var(--primary); }

.pending-screen-wrapper { text-align: center; padding: 60px 20px; animation: fadeInUp 0.8s ease-out; }
.pending-icon-circle { width: 100px; height: 100px; background: var(--primary-ultra-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 30px; border: 2px solid var(--primary); }

@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content" data-aos="fade-up">
            <div class="hero-intro">
                <span class="greeting">أداة القياس الاستراتيجي المتقدم</span>
                <h1 class="hero-name">المحرّك الاستراتيجي 2026</h1>
                <p class="hero-title">تحليل نضج الأعمال المتكامل</p>
            </div>
            <p class="hero-description" style="max-width: 650px;">
                اكتشف الفجوات الاستراتيجية في منشأتك من خلال <strong>15 ركيزة أساسية</strong>. يتم تحليل بياناتك بواسطة خوارزميات مخصصة لإنتاج تقرير دقيق.
            </p>
        </div>
    </div>
    <div class="hero-gradient"></div>
</section>

<!-- Main Diagnostic Section -->
<section class="diagnostic-wizard-section">
    <div class="container">
        
        <!-- Steps Navigation -->
        <div class="steps-nav no-print" data-aos="fade-up">
            <div class="step-nav-item active" data-step="1">1. البيانات</div>
            <div class="step-nav-item" data-step="2">2. التقييم</div>
            <div class="step-nav-item" data-step="3">3. التواصل</div>
            <div class="step-nav-item" data-step="4">4. الإنجاز</div>
        </div>

        <div class="step-container" id="diagnosticCanvas">
            
            <!-- Step 1: Context -->
            <div id="step-id-1" class="diag-step-content" data-aos="fade-up">
                <div class="section-intro text-center mb-10">
                    <h2>لنبدأ بتحديد سياق أعمالك</h2>
                    <p>هذه البيانات تساعدنا في ضبط معايير "المقارنة المرجعية" (Benchmarking)</p>
                </div>
                
                <div class="service-card p-10" style="max-width: 700px; margin: 0 auto; background: var(--bg-card); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-lg);">
                    <div class="form-group mb-6">
                        <label class="form-label font-bold mb-3 d-block">اسم الجهة / الشركة <span class="text-danger">*</span></label>
                        <input type="text" id="company_name" class="form-control" placeholder="مثلاً: شركة آفاق الرقمية" style="height: 60px; font-size: 1.1rem; border-radius: var(--radius-md);">
                    </div>
                    
                    <div class="form-group mb-6">
                        <label class="form-label font-bold mb-3 d-block">كيف عرفت علينا؟ (لتحسين التقرير)</label>
                        <select id="lead_source" class="form-control" style="height: 60px; border-radius: var(--radius-md);">
                            <option value="google">بحث جوجل</option>
                            <option value="social">سوشيال ميديا (انستجرام/تيك توك)</option>
                            <option value="referral">توصية من شخص</option>
                            <option value="ads">إعلان ممول</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    
                    <div class="row" style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                        <div class="form-group">
                            <label class="form-label font-bold mb-3 d-block">القطاع</label>
                            <select id="industry" class="form-control" style="height: 60px; border-radius: var(--radius-md);">
                                <option value="ecommerce">التجارة الإلكترونية (Ecommerce)</option>
                                <option value="services">الخدمات المهنية والاستشارية</option>
                                <option value="tech">التقنية والشركات الناشئة (SaaS)</option>
                                <option value="realestate">العقارات والمقاولات</option>
                                <option value="fnb">الأغذية والمشروبات (مطاعم/كافيهات)</option>
                                <option value="retail">تجار التجزئة والمتاجر التقليدية</option>
                                <option value="industry">التصنيع والإنتاج</option>
                                <option value="education">التعليم والتدريب</option>
                                <option value="healthcare">الرعاية الصحية والجمال</option>
                                <option value="other">قطاعات أخرى</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label font-bold mb-3 d-block">حجم الفريق</label>
                            <select id="companySize" class="form-control" style="height: 60px; border-radius: var(--radius-md);">
                                <option value="solo">مستقل / مؤسس فردي</option>
                                <option value="small">صغير (2-10 موظفين)</option>
                                <option value="medium">متوسط (11-50 موظف)</option>
                                <option value="large">كبير (50+ موظف)</option>
                            </select>
                        </div>
                    </div>

                    <div class="text-center">
                        <button onclick="changeStep(2)" class="btn btn-primary btn-lg w-100 p-5">ابدأ التقييم الاستراتيجي الآن <i class="fas fa-arrow-left mr-2"></i></button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Assessment Session -->
            <div id="step-id-2" class="diag-step-content" style="display:none;">
                <div class="progress-bar-wrapper mb-12">
                    <div class="d-flex justify-between items-center mb-4">
                        <h4 id="categoryName" class="text-primary mb-0">الرؤية والقيادة</h4>
                        <span id="progressIndicator" class="font-bold">1 / 15</span>
                    </div>
                    <div style="height: 8px; background: var(--bg-tertiary); border-radius: 10px; overflow: hidden;">
                        <div id="progressBarFill" style="height: 100%; background: var(--primary); width: 0%; transition: width 0.4s ease;"></div>
                    </div>
                </div>

                <div id="questionContainer" data-aos="fade-in"></div>
            </div>

            <!-- Step 3: Contact Form -->
            <div id="step-id-3" class="diag-step-content" style="display:none;">
                <div class="section-intro text-center mb-10">
                    <div class="service-icon" style="margin-bottom: 1rem;"><i class="fas fa-check-circle"></i></div>
                    <h2>بيانات التسليم</h2>
                    <p class="text-muted">جاري معالجة بياناتك... سيتم إرسال التقرير عبر البريد.</p>
                </div>
                
                <div class="service-card p-10 mx-auto" style="max-width: 550px;">
                    <div class="form-group mb-6">
                        <label class="form-label font-bold mb-2 d-block">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" id="full_name" class="form-control" placeholder="الاسم الذي نعرفك به">
                    </div>
                    <div class="form-group mb-6">
                        <label class="form-label font-bold mb-2 d-block">البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" id="email" class="form-control" placeholder="example@mail.com">
                    </div>
                    <div class="form-group mb-8">
                        <label class="form-label font-bold mb-2 d-block">رقم التواصل (واتساب)</label>
                        <input type="tel" id="address_phone" class="form-control" placeholder="05xxxxxxxx">
                    </div>
                    <button id="submitReportBtn" onclick="saveAndGenerate()" class="btn btn-primary btn-lg w-100 py-5">إرسال البيانات للتحليل الاستراتيجي <i class="fas fa-check-circle mr-2"></i></button>
                    <div id="aiLoading" style="display:none; text-align:center; margin-top:20px;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">جاري تحليل البيانات ومقارنتها بالنماذج الاستشارية المعتمدة...</p>
                    </div>
                </div>
            </div>

            <!-- Step 4: Pending Review Screen -->
            <div id="step-id-4" class="diag-step-content" style="display:none;">
                <div class="pending-screen-wrapper">
                    <div class="pending-icon-circle">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h2 class="mb-4" style="font-size: 2rem;">تمت معالجة بياناتك</h2>
                    <p class="text-muted mb-6 h5" style="line-height: 1.8; max-width: 700px; margin-left: auto; margin-right: auto;">
                        لقد تم استلام بياناتك بنجاح وبدأ فريقنا الفني والمؤشرات الاستشارية في تحليل وضعك الحالي.<br>
                        تمت مطابقة ردودك مع أفضل الممارسات في قطاعك لبناء خارطة طريق مخصصة.<br>
                        سيصلك التقرير التفصيلي والتوصيات التنفيذية على بريدك الإلكتروني قريباً.
                    </p>
                    
                    <div style="background: #F3F4F6; padding: 20px; border-radius: 12px; max-width: 600px; margin: 30px auto; text-align: right;">
                        <p class="small text-muted mb-2" style="margin-bottom:0;"><i class="fas fa-info-circle"></i> نصيحة: تأكد من فحص مجلد Spam.</p>
                    </div>

                    <div class="mt-8">
                        <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary">العودة للرئيسية</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
// بنك الأسئلة (محدث ومختزل - بدون الأسئلة الحساسة)
// بنك الأسئلة الشامل (Universal Matrix v5.0)
// تم تنظيمها لتكون متفرعة بناءً على القطاع (Industry) وحجم العمل (Size)
const QUESTS_DATABASE = {
    // ركائز عامة لكل النشاطات (General Pillars)
    'common': [
        { type: 'single', p: 'strategy', q: 'ما هو الهدف الاستراتيجي الأكبر لمنشأتك في الـ 12 شهراً القادمة؟', options: [
            { t: 'البقاء والاستقرار المالي (Survival)', s: 20, r: 'ركز على تقليل الهدر وتحسين التدفق النقدي.' },
            { t: 'التوسع وزيادة الحصة السوقية (Scaling)', s: 80, r: 'استثمر في أتمتة الأنظمة لاستيعاب النمو.' },
            { t: 'بناء علامة تجارية وسلطة فكرية (Authority)', s: 70, r: 'ركز على صناعة المحتوى النوعي وبناء المجتمع.' },
            { t: 'التحول من العمل اليدوي إلى الرقمي (Digitalization)', s: 60, r: 'اربط العمليات بـ CRM ونظم الأتمتة فوراً.' }
        ]},
        { type: 'single', p: 'marketing', q: 'ما هي استراتيجيتك الرئيسية لجذب العملاء الجدد؟', options: [
            { t: 'الاعتماد على التوصيات والعلاقات الشخصية فقط', s: 30, r: 'هذا غير قابل للتوسع، ابدأ ببناء قنوات رقمية ممتلكة.' },
            { t: 'الإعلانات المدفوعة على منصات التواصل الاجتماعي', s: 60, r: 'تأكد من قياس تكلفة اكتساب العميل (CAC) مقابل القيمة الدائمة (LTV).' },
            { t: 'صناعة محتوى قيم وبناء جمهور عضوي', s: 90, r: 'استمر وحوّل الجمهور لقائمة بريدية وقاعدة بيانات ممتلكة.' },
            { t: 'نموذج هجين (إعلانات + محتوى + شراكات)', s: 85, r: 'تأكد من تتبع أداء كل قناة وتحسينها بشكل مستمر.' }
        ]},
        { type: 'single', p: 'tech', q: 'كيف تدير بيانات عملائك ومعلومات الأعمال الحساسة؟', options: [
            { t: 'جداول Excel ومستندات مبعثرة بين الأجهزة', s: 10, r: 'أنت في خطر فقدان بيانات حرج، انتقل فوراً لنظام إدارة مركزي.' },
            { t: 'تطبيقات سحابية منفصلة (Google Drive, Trello, WhatsApp)', s: 40, r: 'تحتاج لتوحيد البيانات في نظام CRM واحد لتجنب التشتت.' },
            { t: 'نظام CRM أو ERP متكامل مع صلاحيات وحماية', s: 90, r: 'ممتاز، ركز الآن على أتمتة التقارير والتحليلات الذكية.' },
            { t: 'حلول مخصصة مبنية برمجياً لاحتياجاتنا الخاصة', s: 95, r: 'تأكد من توثيق الأنظمة وتدريب الفريق عليها باستمرار.' }
        ]},
        { type: 'single', p: 'operations', q: 'ما مدى وضوح العمليات والإجراءات الداخلية في منشأتك؟', options: [
            { t: 'العمل يعتمد على خبرة الأفراد دون توثيق واضح', s: 20, r: 'هذا يجعلك رهينة للموظفين، ابدأ بكتابة أدلة التشغيل (SOPs).' },
            { t: 'لدينا توثيق جزئي لبعض العمليات الأساسية', s: 50, r: 'اعمل على توحيد كل العمليات الحرجة وتحديثها دورياً.' },
            { t: 'كل عملية موثقة ومراقبة بمؤشرات أداء واضحة', s: 85, r: 'ركز على تحسين الكفاءة وتقليل الهدر (Lean Thinking).' },
            { t: 'عمليات مؤتمتة بالكامل مع تحسين مستمر', s: 100, r: 'أنت في مستوى عالمي، شارك نموذجك كمرجع في قطاعك.' }
        ]}
    ],
    
    // مسار التجارة الإلكترونية (Ecommerce)
    'industry_ecommerce': [
        { type: 'single', p: 'marketing', q: 'كيف تتعامل حالياً مع رحلة العميل في متجرك؟', options: [
            { t: 'نعتمد على الإعلانات المباشرة فقط للبيع', s: 30, r: 'تحتاج لبناء نظام "إعادة استستهداف" وقمع مبيعات (Funnel).' },
            { t: 'لدينا استراتيجية لتحسين معدل التحويل (CRO)', s: 70, r: 'ركز على تجربة المستخدم (UX) في صفحات الدفع.' },
            { t: 'نستخدم التشغيل الآلي لاستعادة "السلال المتروكة"', s: 90, r: 'ممتاز، ابدأ الآن في تخصيص العروض بناءً على سلوك الشراء.' }
        ]},
        { type: 'single', p: 'tech', q: 'ما هو التحدي الأكبر في العمليات اللوجستية لديك؟', options: [
            { t: 'تأخير شركات الشحن وصعوبة التتبع', s: 40, r: 'ابحث عن حلول تجميع (Aggregators) لتحسين الأداء.' },
            { t: 'ارتفاع نسبة المرتجعات وعدم استلام الطلبات', s: 20, r: 'فعل تأكيد الطلب عبر واتساب آلياً قبل الشحن.' },
            { t: 'صعوبة ربط المخزون بالمتجر والمنصات الأخرى', s: 50, r: 'تحتاج لنظام ERP مصغر لتوحيد قنوات البيع.' }
        ]},
        { type: 'single', p: 'strategy', q: 'ما هي استراتيجيتك في التسعير والهوامش الربحية؟', options: [
            { t: 'نسعّر بناءً على التكلفة + هامش ثابت', s: 40, r: 'جرّب التسعير الديناميكي بناءً على الطلب والمنافسة.' },
            { t: 'نراقب أسعار المنافسين ونعدّل باستمرار', s: 70, r: 'استخدم أدوات الـ Price Intelligence لأتمتة المراقبة.' },
            { t: 'لدينا نموذج تسعير ديناميكي مؤتمت', s: 95, r: 'ركز على تحسين قيمة السلة (AOV) عبر البيع المتقاطع.' }
        ]},
        { type: 'single', p: 'operations', q: 'كيف تقيس وتحسن تجربة ما بعد البيع؟', options: [
            { t: 'لا نتواصل مع العميل بعد استلام الطلب', s: 15, r: 'أنت تخسر فرصة الولاء، فعّل رسائل الشكر والمتابعة.' },
            { t: 'نرسل رسالة شكر واحدة بعد التوصيل', s: 50, r: 'اب نِ برنامج ولاء وحفز العميل على الطلب المتكرر.' },
            { t: 'لدينا برنامج ولاء ونظام تقييم وإحالات', s: 90, r: 'استثمر في Community Building لتحويل العملاء لسفراء.' }
        ]},
        { type: 'single', p: 'marketing', q: 'هل تطبق استراتيجية Omnichannel (تعدد القنوات)؟', options: [
            { t: 'نبيع فقط عبر منصة واحدة (موقعنا أو سوشيال)', s: 30, r: 'وسّع وجودك لمنصات متعددة لزيادة نقاط التلامس.' },
            { t: 'موجودون على عدة منصات لكن بيانات منفصلة', s: 55, r: 'وحّد البيانات في CRM واحد لرؤية شاملة للعميل.' },
            { t: 'تكامل كامل بين كل القنوات (متجر، تطبيق، سوشيال)', s: 95, r: 'اعمل على تجربة سلسة للعميل عبر كل نقاط التلامس.' }
        ]},
        { type: 'single', p: 'tech', q: 'كيف تستخدم البيانات لتخصيص تجربة العميل؟', options: [
            { t: 'لا نستخدم أي تخصيص، كل العملاء يرون نفس المحتوى', s: 20, r: 'ابدأ بتقسيم العملاء (Segmentation) حسب السلوك.' },
            { t: 'نرسل عروضاً مخصصة بناءً على التاريخ الشرائي', s: 65, r: 'استخدم محرك التوصيات (Recommmendation Engine) لزيادة AOV.' },
            { t: 'تخصيص كامل مدعوم بالبيانات والتعلم الآلي', s: 100, r: 'أنت تطبق أفضل الممارسات العالمية، استمر.' }
        ]}
    ],

    // مسار الخدمات المهنية والاستشارية (Services/Consulting)
    'industry_services': [
        { type: 'single', p: 'marketing', q: 'كيف تحصل على عملاء جدد لخدماتك؟', options: [
            { t: 'توصيات العملاء الحاليين فقط (Word of Mouth)', s: 40, r: 'هذا نموذج غير قابل للتوسع، ابدأ بالتسويق بالمحتوى.' },
            { t: 'إعلانات ممولة ووصول بارد (Cold Outbound)', s: 50, r: 'احرص على بناء "مغناطيس عملاء" (Lead Magnet) لفلترة المهتمين.' },
            { t: 'بناء سلطة معرفية عبر LinkedIn وصناعة المحتوى', s: 90, r: 'استمر في ذلك، وحوّل جمهورك إلى قائمة بريدية ممتلكة.' }
        ]},
        { type: 'single', p: 'strategy', q: 'ما هو مستوى التخصص (Niche) في خدماتك؟', options: [
            { t: 'نقدم خدمات عامة لأي عميل مهتم', s: 20, r: 'التعميم يقلل قيمتك السعرية، اختر تخصصاً دقيقاً نافساً.' },
            { t: 'لدينا تخصص في قطاع معين لكننا نقبل الجميع', s: 60, r: 'ابدأ بالاعتذار عن المشاريع التي لا تخدم صورتك الذهنية.' },
            { t: 'نحن الخبراء الأول في فئة دقيقة جداً', s: 100, r: 'أنت في منطقة (Authority Pricing)، ارفع أسعارك.' }
        ]},
        { type: 'single', p: 'strategy', q: 'ما هو نموذج التسعير الذي تطبقه على خدماتك؟', options: [
            { t: 'تسعير بالساعة أو اليوم', s: 30, r: 'هذا يربط دخلك بوقتك، انتقل للتسعير بالقيمة أو الباقات.' },
            { t: 'تسعير بالمشروع أو الباقة', s: 70, r: 'ركز على توضيح ROI للعميل لتبرير السعر.' },
            { t: 'تسعير بالقيمة (Value-Based Pricing)', s: 95, r: 'ممتاز، احرص على قياس النتائج وإظهار الأثر للعميل.' },
            { t: 'نموذج استمرارية (Retainer) أو اشتراكات شهرية', s: 90, r: 'ضمنت استقرار الدخل، ركز الآن على رفع LTV للعميل.' }
        ]},
        { type: 'single', p: 'tech', q: 'كيف تدير علاقاتك مع العملاء والمشاريع؟', options: [
            { t: 'عبر البريد الإلكتروني والواتساب فقط', s: 25, r: 'هذا فوضوي وغير احترافي، استخدم CRM فوراً.' },
            { t: 'نستخدم أدوات إدارة مشاريع (Trello, Asana)', s: 55, r: 'حسّن بربطها مع CRM لرؤية متكاملة لرحلة العميل.' },
            { t: 'نظام CRM متكامل مع بوابة عملاء', s: 90, r: 'استثمر في أتمتة التقارير والفواتير لتوفير الوقت.' }
        ]},
        { type: 'single', p: 'operations', q: 'كيف تقيس رضا العملاء ونجاح المشاريع؟', options: [
            { t: 'نعتمد على الشعور العام ونسبة التجديد', s: 30, r: 'ابدأ بقياس NPS وCSAT بشكل منهجي بعد كل مشروع.' },
            { t: 'نرسل استبيانات رضا بعد انتهاء كل مشروع', s: 70, r: 'حلل البيانات لتحديد نقاط التحسين المتكررة.' },
            { t: 'نقيس NPS/CSAT ونربطه بتحسين مستمر', s: 95, r: 'استثمر في Case Studies لإظهار النتائج الملموسة.' }
        ]},
        { type: 'single', p: 'operations', q: 'هل لديكم توثيق وعمليات موحدة (SOPs) للخدمات؟', options: [
            { t: 'كل مشروع يُنفذ بطريقة مختلفة حسب الفريق', s: 20, r: 'هذا يقلل الكفاءة والجودة، وثّق أفضل الممارسات فوراً.' },
            { t: 'لدينا قوالب وأدلة لبعض الخدمات الأساسية', s: 60, r: 'وحّد كل العمليات واجعلها متاحة لكل الفريق.' },
            { t: 'كل خدمة موثقة بـ SOP وتُحدث دورياً', s: 95, r: 'فكر في تحويل خبرتك لمنتج رقمي (دورات، استشارات مسجلة).' }
        ]}
    ],

    // مسار الشركات التقنية والناشئة (Tech/Startups)
    'industry_tech': [
        { type: 'single', p: 'strategy', q: 'في أي مرحلة تجد منتجك التقني الآن؟', options: [
            { t: 'مجرد فكرة أو نموذج أولي (MVP)', s: 30, r: 'ركز على التحقق من السوق (Validation) قبل البرمجة العميقة.' },
            { t: 'لدينا مستخدمون ونبحث عن ملائمة السوق (PMF)', s: 60, r: 'استثمر في جمع البيانات النوعية من المستخدمين الأوائل.' },
            { t: 'مرحلة النمو السريع (Scaling) والتوسع الجغرافي', s: 90, r: 'ركز على كفاءة البنية التحتية وأتمتة خدمة العملاء.' }
        ]}
    ],

    // مسار العقارات والمقاولات (Real Estate)
    'industry_realestate': [
        { type: 'single', p: 'marketing', q: 'كيف تدير عملية جلب المواعيد والإغلاق العقاري؟', options: [
            { t: 'نعتمد على الاتصالات الباردة واللوحات الميدانية', s: 20, r: 'هذه الطرق التقليدية مكلفة، انتقل للتسويق الرقمي المستهدف.' },
            { t: 'نستخدم بوابات ومواقع عقارية عامة', s: 50, r: 'أنت تنافس في محيط أحمر، ابدأ ببناء علامتك التجارية الخاصة.' },
            { t: 'لدينا قمع تسويقي مؤتمت وفريق مبيعات مدرب', s: 90, r: 'ركز على تحسين معدل الاحتفاظ ببيانات العملاء (CRMs).' }
        ]}
    ],
    
    // مسار الأغذية والمشروبات (F&B)
    'industry_fnb': [
        { type: 'single', p: 'strategy', q: 'كيف تضمن ثبات جودة الخدمة وتكرار الطلب في فرعك؟', options: [
            { t: 'نعتمد بالكامل على مهارة الشيف أو الطاقم الحالي', s: 30, r: 'هذا خطر كبير، يجب مأسسة (Standardize) الوصفات والعمليات.' },
            { t: 'نطبق أدلة تشغيل (SOPs) ونراقب الأداء دورياً', s: 70, r: 'فكر في أتمتة نظام الولاء الرقمي لزيادة تكرار الزيارات.' },
            { t: 'لدينا نظام مراقبة حي لكل العمليات وتقارير هدر يومية', s: 100, r: 'ممتاز، فكر الآن في التوسع عبر الفرنشايز أو الفروع الجديدة.' }
        ]}
    ],

    // مسار المؤسسين الأفراد (Solo Entrepreneurs)
    'size_solo': [
        { type: 'single', p: 'tech', q: 'كيف تدير وقتك والمهام المتكررة وحدك؟', options: [
            { t: 'أفعل كل شيء يدوياً وبنفسي', s: 10, r: 'أنت في خطر "الاحتراق الرقمي"، استخدم أدوات الأتمتة فوراً.' },
            { t: 'أستخدم بعض الأدوات المشتتة (Trello, WhatsApp)', s: 40, r: 'تحتاج لتوحيد "نظام التشغيل الشخصي" (Personal OS).' },
            { t: 'بنيت منظومة أتمتة تعوض غياب الموظفين', s: 90, r: 'رائع، أنت تطبق نموذج "الشركة الفردية" الحديثة.' }
        ]}
    ],

    // مسار الشركات المتوسطة والكبيرة (Medium/Large)
    'size_large': [
        { type: 'single', p: 'strategy', q: 'كيف يتم اتخاذ القرار الاستراتيجي في المنشأة؟', options: [
            { t: 'بناءً على حدس وتوجيهات الإدارة العليا', s: 30, r: 'انتقل لنموذج (Data-Driven Decision Making).' },
            { t: 'بناءً على تقارير دورية من رؤساء الأقسام', s: 60, r: 'تأكد من دقة البيانات وربطها بلوحة تحكم حية (Live Dashboard).' },
            { t: 'بناءً على تحليل البيانات الضخمة والنماذج المتقدمة', s: 100, r: 'ركز الآن على "التحليلات التنبؤية" لاستشراف المستقبل.' }
        ]}
    ],

    // مسار تجارة التجزئة (Retail)
    'industry_retail': [
        { type: 'single', p: 'operations', q: 'كيف تدير مخزونك وتربطه بتجربة العميل في الفروع؟', options: [
            { t: 'نعتمد على الجرد اليدوي والملاحظة الشخصية', s: 20, r: 'هذا يؤدي لهدر كبير، استخدم نظام (Inventory Management) رقمي.' },
            { t: 'لدينا نظام POS بسيط لكل فرع بشكل منفصل', s: 50, r: 'تحتاج لربط الفروع سحابياً لمراقبة حركة المخزون لحظياً.' },
            { t: 'منظومة مخزون ذكية مرتبطة آلياً بإعادة الطلب', s: 95, r: 'ممتاز، ركز الآن على تجربة العميل (Omnichannel).' }
        ]}
    ],

    // مسار التصنيع والإنتاج (Manufacturing)
    'industry_industry': [
        { type: 'single', p: 'operations', q: 'ما هو مستوى مراقبة تكلفة الإنتاج والفاقد لديك؟', options: [
            { t: 'نقيس التكاليف بشكل إجمالي في نهاية الشهر', s: 30, r: 'تحتاج لمراقبة لحظية لنقاط الهدر في خط الإنتاج.' },
            { t: 'لدينا تقارير دورية عن كفاءة الماكينات (OEE)', s: 70, r: 'استثمر في أنظمة الـ (IoT) لتقليل وقت التوقف المفاجئ.' },
            { t: 'أتمتة كاملة للتقارير وربطها بسلسلة الإمداد', s: 100, r: 'أنت في منطقة (Industry 4.0)، ركز على الصيانة التنبؤية.' }
        ]},
        { type: 'single', p: 'strategy', q: 'كيف تدير سلسلة الإمداد والعلاقة مع الموردين؟', options: [
            { t: 'نعتمد على مورد واحد أو عدد محدود جداً', s: 20, r: 'أنت في خطر انقطاع الإمداد، نوّع قاعدة الموردين فوراً.' },
            { t: 'لدينا عدة موردين لكن التعامل يدوي وغير منظم', s: 50, r: 'تحتاج لنظام SRM لإدارة الموردين وتقييم أدائهم.' },
            { t: 'شراكات استراتيجية طويلة الأمد مع تقييم دوري', s: 75, r: 'ركز على التكامل الرقمي مع الموردين (EDI/API).' },
            { t: 'سلسلة إمداد رقمية متكاملة مع رؤية شاملة', s: 95, r: 'استثمر في التحليلات التنبؤية لتج نب نقص المواد الخام.' }
        ]},
        { type: 'single', p: 'operations', q: 'ما مدى تطبيقكم لمبادئ الإنتاج المرن (Lean Manufacturing)؟', options: [
            { t: 'لا نطبق أي منهجية محددة في الإنتاج', s: 15, r: 'ابدأ بتطبيق 5S لتنظيم بيئة العمل وتقليل الهدر المرئي.' },
            { t: 'نطبق بعض الأدوات مثل 5S أو Kaizen بشكل جزئي', s: 50, r: 'وسّع التطبيق ليشمل VSM (رسم خريطة القيمة) لتحديد نقاط الهدر.' },
            { t: 'نطبق Lean بشكل منهجي مع قياس مستمر', s: 85, r: 'ادمج Six Sigma مع Lean لتحقيق دقة صفرية في العيوب.' },
            { t: 'ثقافة التحسين المستمر (Kaizen) متجذرة في كل الفريق', s: 100, r: 'أنت قدوة في القطاع، شارك تجربتك كمرجع دولي.' }
        ]},
        { type: 'single', p: 'operations', q: 'كيف تضمن الجودة والامتثال للمعايير (ISO/Quality Control)؟', options: [
            { t: 'الفحص النهائي للمنتج قبل الشحن فقط', s: 25, r: 'هذا يؤدي لهدر كبير، طبّق الفحص في كل مرحلة إنتاج.' },
            { t: 'لدينا نقاط فحص متعددة لكن دون توثيق رسمي', s: 50, r: 'ابدأ بتطبيق ISO 9001 لتوثيق العمليات وزيادة الثقة.' },
            { t: 'حاصلون على شهادة ISO مع تدقيق دوري', s: 80, r: 'ركز على الجودة الوقائية بدلاً من التصحيحية (Poka-Yoke).' },
            { t: 'أنظمة جودة ذكية مع تحليل إحصائي (SPC)', s: 100, r: 'استثمر في التحليلات التنبؤية للعيوب قبل حدوثها.' }
        ]},
        { type: 'single', p: 'tech', q: 'ما هو نهجكم في الصيانة ومنع التوقفات غير المخططة؟', options: [
            { t: 'صيانة تصحيحية فقط (إصلاح بعد العطل)', s: 20, r: 'هذا مكلف جداً، انتقل فوراً للصيانة الوقائية المجدولة.' },
            { t: 'صيانة وقائية مجدولة بناءً على الزمن', s: 60, r: 'حسّن الجدولة بناءً على ساعات تشغيل فعلية وليس زمنية فقط.' },
            { t: 'صيانة تنبؤية باستخدام أجهزة استشعار (IoT)', s: 90, r: 'استثمر في تحليل البيانات (Machine Learning) للتنبؤ الأدق.' },
            { t: 'صيانة ذاتية (Autonomous) مع تشخيص ذكي', s: 100, r: 'أنت رائد في Industry 4.0، وثّق نموذجك للآخرين.' }
        ]},
        { type: 'single', p: 'tech', q: 'ما مستوى التحول الرقمي الصناعي (Industry 4.0) لديكم؟', options: [
            { t: 'عمليات يدوية أو آلية بسيطة غير متصلة', s: 15, r: 'ابدأ بتوصيل الآلات بالإنترنت (IIoT) لجمع البيانات الحية.' },
            { t: 'بعض الآلات متصلة لكن دون تكامل شامل', s: 45, r: 'وحّد البيانات في منصة MES واحدة لرؤية شاملة.' },
            { t: 'مصنع ذكي مع ربط ERP-MES-IoT', s: 80, r: 'استثمر في التوائم الرقمية (Digital Twins) لمحاكاة الإنتاج.' },
            {  t: 'مصنع ذكي متكامل مع قرارات ذاتية (AI-driven)', s: 100, r: 'أنت في الطليعة العالمية، ركز على الاستدامة الرقمية.' }
        ]},
        { type: 'single', p: 'operations', q: 'كيف تدير مخزون المواد الخام والمنتجات الجاهزة؟', options: [
            { t: 'جرد يدوي شهري أو أسبوعي', s: 25, r: 'أنت تخسر من الهدر والتلف، تحتاج لنظام تتبع فوري (RFID/Barcode).' },
            { t: 'نظام محوسب لكن دون ربط مع الإنتاج والمبيعات', s: 50, r: 'اربط المخزون مع نظام ERP لرؤية متكاملة وإعادة طلب آلية.' },
            { t: 'نظام Just-In-Time مع موردين موثوقين', s: 85, r: 'تأكد من وجود مخزون أمان للمواد الحرجة لتجنب التوقف.' },
            { t: 'مخزون ذكي مع تنبؤ بالطلب وتجديد آلي', s: 100, r: 'استثمر في blockchain لتتبع شفاف لسلسلة الإمداد الكاملة.' }
        ]}
    ],

    // مسار التعليم والتدريب (Education)
    'industry_education': [
        { type: 'single', p: 'strategy', q: 'كيف تقيس نجاح وجودة المحتوى التعليمي أو التدريبي؟', options: [
            { t: 'بناءً على عدد المسجلين والمبيعات فقط', s: 30, r: 'المبيعات لا تعني النجاح، قس معدل إكمال الدورة (Completion Rate).' },
            { t: 'بناءً على استبيانات الرضا بعد التخرج', s: 60, r: 'تحتاج لأدوات تتبع (Engagement) داخل البيئة التعليمية.' },
            { t: 'بناءً على نتائج المتعلمين الحقيقية وسوق العمل', s: 90, r: 'هذا هو المعيار الأقوى للتطور المستدام.' }
        ]}
    ],

    // مسار الرعاية الصحية والجمال (Healthcare/Beauty)
    'industry_healthcare': [
        { type: 'single', p: 'operations', q: 'كيف تدير تدفق المراجعين وضمان جودة الخدمة؟', options: [
            { t: 'نعتمد على الحجز الهاتفي أو الحضور المباشر', s: 20, r: 'استخدم نظام حجز رقمي (Booking System) لتقليل وقت الانتظار.' },
            { t: 'لدينا نظام مواعيد ولكن التواصل مع المريض يدوي', s: 50, r: 'فعل التذكير الآلي ورسائل المتابعة (Follow-up) بعد الخدمة.' },
            { t: 'تجربة مريض متكاملة ومؤتمتة من الحجز حتى الشفافية', s: 95, r: 'ركز الآن على بناء (Patient Community) لزيادة الولاء.' }
        ]}
    ]
};

// سيتم تعبئة هذه المصفوفة ديناميكياً بناءً على الـ Context
let STRATEGIC_QUESTS = [];

let currentIdx = 0;
let answersLog = [];

function changeStep(n) {
    if(n === 2) {
        const companyName = document.getElementById('company_name').value;
        const industry = document.getElementById('industry').value;
        const size = document.getElementById('companySize').value;
        
        if(!companyName) { alert('يرجى كتابة اسم المنشأة'); return; }
        
        // بناء بنك الأسئلة الديناميكي (Dynamic Branching)
        STRATEGIC_QUESTS = [...QUESTS_DATABASE['common']];
        
        // إضافة أسئلة القطاع
        if(QUESTS_DATABASE['industry_' + industry]) {
            STRATEGIC_QUESTS = [...STRATEGIC_QUESTS, ...QUESTS_DATABASE['industry_' + industry]];
        }
        
        // إضافة أسئلة الحجم
        if(size === 'solo' && QUESTS_DATABASE['size_solo']) {
            STRATEGIC_QUESTS = [...STRATEGIC_QUESTS, ...QUESTS_DATABASE['size_solo']];
        } else if((size === 'medium' || size === 'large') && QUESTS_DATABASE['size_large']) {
            STRATEGIC_QUESTS = [...STRATEGIC_QUESTS, ...QUESTS_DATABASE['size_large']];
        }
        
        // إعادة تهيئة الفهرس
        currentIdx = 0;
        answersLog = [];

        renderStrategicQuestion();
    }
    
    document.querySelectorAll('.diag-step-content').forEach(el => el.style.display = 'none');
    document.getElementById('step-id-' + n).style.display = 'block';
    
    document.querySelectorAll('.step-nav-item').forEach(it => {
        it.classList.remove('active');
        if(it.dataset.step == n) it.classList.add('active');
    });
    
    if(n === 4) window.scrollTo(0,0);
}

function renderStrategicQuestion() {
    const q = STRATEGIC_QUESTS[currentIdx];
    const catNames = { 
        'strategy': 'الرؤية والقيادة الاستراتيجية', 
        'marketing': 'النمو والتسويق الرقمي', 
        'tech': 'البنية التحتية والتقنية',
        'operations': 'الكفاءة والعمليات التشغيلية'
    };
    document.getElementById('categoryName').textContent = catNames[q.p];
    document.getElementById('progressIndicator').textContent = `${currentIdx + 1} / ${STRATEGIC_QUESTS.length}`;
    document.getElementById('progressBarFill').style.width = ((currentIdx + 1) / STRATEGIC_QUESTS.length * 100) + '%';
    const container = document.getElementById('questionContainer');
    
    let html = `<div class="fade-in"><h3 class="mb-10 font-bold" style="line-height:1.5; font-size: 1.8rem;">${q.q}</h3>`;

    if (q.type === 'multiple') {
        html += `<div class="diag-grid-container">`;
        q.options.forEach((opt, i) => {
            html += `
                <div class="diag-checkbox-option" onclick="toggleMultipleChoice(this, ${opt.s}, '${opt.t.replace(/'/g, "\\'")}')">
                    <i class="far fa-square"></i>
                    <span>${opt.t}</span>
                </div>
            `;
        });
        html += `</div><div class="text-center mt-8"><button onclick="saveMultipleChoice()" class="btn btn-primary">التالي <i class="fas fa-arrow-left"></i></button></div>`;
    } else {
        html += `<div class="diag-options-grid">`;
        q.options.forEach((opt, i) => {
            html += `
                <div class="diag-option" onclick="captureChoice(${opt.s}, '${opt.r.replace(/'/g, "\\'")}', '${opt.t.replace(/'/g, "\\'")}')">
                    <div class="diag-option-text">${opt.t}</div>
                </div>
            `;
        });
        html += `</div>`;
    }
    
    html += `</div>`;
    container.innerHTML = html;
}

function captureChoice(s, r, a) {
    answersLog[currentIdx] = { 
        pillar: STRATEGIC_QUESTS[currentIdx].p, 
        score: s, 
        rec: r, 
        q: STRATEGIC_QUESTS[currentIdx].q,
        a: a
    };
    nextQuestion();
}

let currentMultipleScores = [];
let currentMultipleAnswers = [];
function toggleMultipleChoice(el, score, text) {
    el.classList.toggle('selected');
    const icon = el.querySelector('i');
    if (el.classList.contains('selected')) {
        icon.classList.remove('fa-square'); icon.classList.add('fa-check-square');
        currentMultipleScores.push(score);
        currentMultipleAnswers.push(text);
    } else {
        icon.classList.remove('fa-check-square'); icon.classList.add('fa-square');
        currentMultipleScores = currentMultipleScores.filter(s => s !== score);
        currentMultipleAnswers = currentMultipleAnswers.filter(t => t !== text);
    }
}

function saveMultipleChoice() {
    let finalScore = 0;
    let finalAnswer = "";
    if (currentMultipleScores.length > 0) {
        const sum = currentMultipleScores.reduce((a, b) => a + b, 0);
        finalScore = Math.round(sum / currentMultipleScores.length);
        finalAnswer = currentMultipleAnswers.join(' + ');
    } else { 
        finalScore = 5; 
        finalAnswer = "لم يتم اختيار أي عنصر";
    }

    const q = STRATEGIC_QUESTS[currentIdx];
    answersLog[currentIdx] = { pillar: q.p, score: finalScore, rec: q.r || 'يفضل تفعيل هذه النقاط.', q: q.q, a: finalAnswer };
    currentMultipleScores = [];
    currentMultipleAnswers = [];
    nextQuestion();
}

function nextQuestion() {
    if(currentIdx < STRATEGIC_QUESTS.length - 1) {
        currentIdx++; renderStrategicQuestion();
    } else { changeStep(3); }
}

async function saveAndGenerate() {
    const name = document.getElementById('full_name').value;
    const email = document.getElementById('email').value;
    const phone = document.getElementById('address_phone').value;
    const leadSource = document.getElementById('lead_source').value;
    
    if(!name || !email) { alert('الاسم والبريد ضروريان'); return; }
    
    const btn = document.getElementById('submitReportBtn');
    const loader = document.getElementById('aiLoading');
    btn.style.display = 'none'; 
    loader.style.display = 'block';
    
    // إرسال البيانات الخام للمعالجة الاستراتيجية في الـ Backend
    const payload = {
        full_name: name, email: email, phone: phone, lead_source: leadSource,
        company_name: document.getElementById('company_name').value,
        industry: document.getElementById('industry').value,
        company_size: document.getElementById('companySize').value,
        answers: answersLog, // إرسال الإجابات التفصيلية
        recommendations: answersLog.map(x => ({ q: x.q, r: x.rec }))
    };

    try {
        const res = await fetch('<?= url('api/diagnostic.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        
        document.getElementById('pending_name').textContent = name;
        changeStep(4);

    } catch(e) {
        console.log(e);
        document.getElementById('pending_name').textContent = name;
        changeStep(4);
    }
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
<?php
/**
 * صفحة التواصل - نموذج العملاء المحتملين متعدد الخطوات
 * موقع خالد سعد للاستشارات
 */

require_once dirname(__DIR__) . '/includes/init.php';

// إعدادات SEO
$pageTitle = 'تواصل معنا - ' . SITE_NAME;
$pageDescription = 'تواصل معنا للحصول على استشارة مجانية. نساعدك في تحقيق أهدافك التسويقية والرقمية.';

// الحصول على معلومات الخدمة إذا تم تمريرها
$selectedService = isset($_GET['service']) ? clean($_GET['service']) : '';
$selectedPlan = isset($_GET['plan']) ? clean($_GET['plan']) : '';

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero" style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); padding: var(--space-12) 0;">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <span class="hero-badge">
                <i class="fas fa-envelope"></i>
                تواصل معنا
            </span>
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-4);">نسعد بتواصلك</h1>
            <p style="font-size: var(--font-size-lg); color: var(--text-secondary); max-width: 600px; margin: 0 auto;">
                أخبرنا عن مشروعك وسنتواصل معك خلال 24 ساعة
            </p>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section style="padding: var(--space-16) 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 400px; gap: var(--space-12); align-items: start;">

            <!-- Multi-Step Form -->
            <div class="contact-form-wrapper" data-aos="fade-up">
                <div class="card" style="padding: var(--space-8);">

                    <!-- Progress Bar -->
                    <div class="form-progress" style="margin-bottom: var(--space-8);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-3);">
                            <span class="step-label active" data-step="1">معلومات أساسية</span>
                            <span class="step-label" data-step="2">عن شركتك</span>
                            <span class="step-label" data-step="3">تفاصيل المشروع</span>
                        </div>
                        <div style="height: 4px; background: var(--bg-tertiary); border-radius: var(--radius-full); overflow: hidden;">
                            <div id="progressBar" style="height: 100%; width: 33.33%; background: var(--primary); transition: width var(--transition);"></div>
                        </div>
                    </div>

                    <form id="leadForm" action="<?= url('api/submit-lead.php') ?>" method="POST" data-validate>
                        <?= Security::csrfField() ?>
                        <?= honeypotField() ?>

                        <!-- Step 1: Basic Info -->
                        <div class="form-step active" data-step="1">
                            <h3 style="margin-bottom: var(--space-6);">
                                <i class="fas fa-user" style="color: var(--primary); margin-left: var(--space-2);"></i>
                                معلوماتك الأساسية
                            </h3>

                            <div class="form-group">
                                <label class="form-label" for="full_name">الاسم الكامل <span class="required">*</span></label>
                                <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="أدخل اسمك الكامل" aria-describedby="nameHelp">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">البريد الإلكتروني <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" required placeholder="example@company.com" aria-describedby="emailHelp">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="phone">رقم الهاتف</label>
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder="+966 5x xxx xxxx" dir="ltr" style="text-align: left;">
                            </div>

                            <button type="button" class="btn btn-primary w-100" onclick="nextStep(2)">
                                التالي
                                <i class="fas fa-arrow-left"></i>
                            </button>
                        </div>

                        <!-- Step 2: Company Info -->
                        <div class="form-step" data-step="2" style="display: none;">
                            <h3 style="margin-bottom: var(--space-6);">
                                <i class="fas fa-building" style="color: var(--primary); margin-left: var(--space-2);"></i>
                                معلومات شركتك
                            </h3>

                            <div class="form-group">
                                <label class="form-label" for="company">اسم الشركة</label>
                                <input type="text" id="company" name="company" class="form-control" placeholder="اسم شركتك أو مشروعك">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="company_size">حجم الشركة</label>
                                <select id="company_size" name="company_size" class="form-control">
                                    <option value="">اختر حجم الشركة</option>
                                    <option value="1-10">1-10 موظفين</option>
                                    <option value="11-50">11-50 موظف</option>
                                    <option value="51-200">51-200 موظف</option>
                                    <option value="201-500">201-500 موظف</option>
                                    <option value="500+">أكثر من 500 موظف</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="industry">القطاع / الصناعة</label>
                                <select id="industry" name="industry" class="form-control">
                                    <option value="">اختر القطاع</option>
                                    <option value="technology">التقنية</option>
                                    <option value="ecommerce">التجارة الإلكترونية</option>
                                    <option value="retail">التجزئة</option>
                                    <option value="healthcare">الرعاية الصحية</option>
                                    <option value="education">التعليم</option>
                                    <option value="finance">المالية والمصارف</option>
                                    <option value="real-estate">العقارات</option>
                                    <option value="hospitality">الضيافة والسياحة</option>
                                    <option value="manufacturing">التصنيع</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>

                            <div style="display: flex; gap: var(--space-4);">
                                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="prevStep(1)">
                                    <i class="fas fa-arrow-right"></i>
                                    السابق
                                </button>
                                <button type="button" class="btn btn-primary" style="flex: 2;" onclick="nextStep(3)">
                                    التالي
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Project Details -->
                        <div class="form-step" data-step="3" style="display: none;">
                            <h3 style="margin-bottom: var(--space-6);">
                                <i class="fas fa-clipboard-list" style="color: var(--primary); margin-left: var(--space-2);"></i>
                                تفاصيل المشروع
                            </h3>

                            <div class="form-group">
                                <label class="form-label" for="service_interested">الخدمة المطلوبة</label>
                                <select id="service_interested" name="service_interested" class="form-control">
                                    <option value="">اختر الخدمة</option>
                                    <option value="consulting" <?= $selectedService === 'consulting' ? 'selected' : '' ?>>الاستشارات التسويقية</option>
                                    <option value="digital" <?= $selectedService === 'digital' ? 'selected' : '' ?>>التحول الرقمي</option>
                                    <option value="branding" <?= $selectedService === 'branding' ? 'selected' : '' ?>>بناء الهوية التجارية</option>
                                    <option value="training" <?= $selectedService === 'training' ? 'selected' : '' ?>>التدريب والتطوير</option>
                                    <option value="multiple">عدة خدمات</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="budget">الميزانية المتوقعة</label>
                                <select id="budget" name="budget" class="form-control">
                                    <option value="">اختر نطاق الميزانية</option>
                                    <option value="less_10k">أقل من 10,000 ر.س</option>
                                    <option value="10k_25k">10,000 - 25,000 ر.س</option>
                                    <option value="25k_50k">25,000 - 50,000 ر.س</option>
                                    <option value="50k_100k">50,000 - 100,000 ر.س</option>
                                    <option value="more_100k">أكثر من 100,000 ر.س</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="message">رسالتك <span class="required">*</span></label>
                                <textarea id="message" name="message" class="form-control" rows="4" required placeholder="أخبرنا المزيد عن مشروعك، أهدافك، والتحديات التي تواجهها..." aria-describedby="messageHelp"></textarea>
                                <span class="form-hint" id="messageHelp">الحد الأدنى 20 حرفاً</span>
                            </div>

                            <?php if ($selectedPlan): ?>
                            <input type="hidden" name="selected_plan" value="<?= e($selectedPlan) ?>">
                            <?php endif; ?>

                            <div style="display: flex; gap: var(--space-4);">
                                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="prevStep(2)">
                                    <i class="fas fa-arrow-right"></i>
                                    السابق
                                </button>
                                <button type="submit" class="btn btn-primary" style="flex: 2;" id="submitBtn">
                                    <span class="btn-text">
                                        <i class="fas fa-paper-plane"></i>
                                        إرسال الطلب
                                    </span>
                                    <span class="btn-loading">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        جاري الإرسال...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Success Message -->
                    <div id="formSuccess" style="display: none; text-align: center; padding: var(--space-8);">
                        <div style="width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-6);">
                            <i class="fas fa-check" style="font-size: 2rem; color: var(--success);"></i>
                        </div>
                        <h3 style="margin-bottom: var(--space-3);">تم إرسال طلبك بنجاح!</h3>
                        <p style="color: var(--text-secondary); margin-bottom: var(--space-6);">
                            شكراً لتواصلك معنا. سيقوم أحد خبرائنا بالتواصل معك خلال 24 ساعة.
                        </p>
                        <a href="<?= url('') ?>" class="btn btn-primary">
                            العودة للرئيسية
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contact Info Sidebar -->
            <div class="contact-sidebar" data-aos="fade-up" data-aos-delay="200">
                <!-- Contact Card -->
                <div class="card" style="padding: var(--space-6); margin-bottom: var(--space-6);">
                    <h4 style="margin-bottom: var(--space-5);">
                        <i class="fas fa-headset" style="color: var(--primary); margin-left: var(--space-2);"></i>
                        معلومات التواصل
                    </h4>

                    <div style="margin-bottom: var(--space-5);">
                        <div style="display: flex; align-items: flex-start; gap: var(--space-3); margin-bottom: var(--space-4);">
                            <div style="width: 40px; height: 40px; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-phone-alt" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <strong style="display: block; margin-bottom: var(--space-1);">الهاتف</strong>
                                <a href="tel:+966500000000" style="color: var(--text-secondary);"><?= e(SITE_PHONE) ?></a>
                            </div>
                        </div>

                        <div style="display: flex; align-items: flex-start; gap: var(--space-3); margin-bottom: var(--space-4);">
                            <div style="width: 40px; height: 40px; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-envelope" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <strong style="display: block; margin-bottom: var(--space-1);">البريد الإلكتروني</strong>
                                <a href="mailto:<?= SITE_EMAIL ?>" style="color: var(--text-secondary);"><?= e(SITE_EMAIL) ?></a>
                            </div>
                        </div>

                        <div style="display: flex; align-items: flex-start; gap: var(--space-3);">
                            <div style="width: 40px; height: 40px; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas fa-map-marker-alt" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <strong style="display: block; margin-bottom: var(--space-1);">العنوان</strong>
                                <span style="color: var(--text-secondary);"><?= e(SITE_ADDRESS) ?></span>
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border-color); padding-top: var(--space-5);">
                        <strong style="display: block; margin-bottom: var(--space-3);">ساعات العمل</strong>
                        <p style="color: var(--text-secondary); margin: 0;">
                            <i class="far fa-clock" style="margin-left: var(--space-2);"></i>
                            الأحد - الخميس: 9 ص - 6 م
                        </p>
                    </div>
                </div>

                <!-- Quick Contact -->
                <div class="card" style="padding: var(--space-6); background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white;">
                    <h4 style="margin-bottom: var(--space-3); color: white;">
                        <i class="fab fa-whatsapp" style="margin-left: var(--space-2);"></i>
                        تواصل سريع عبر واتساب
                    </h4>
                    <p style="color: rgba(255,255,255,0.9); margin-bottom: var(--space-4); font-size: var(--font-size-sm);">
                        للاستفسارات السريعة، يمكنك التواصل معنا مباشرة عبر واتساب
                    </p>
                    <a href="https://wa.me/966500000000?text=مرحباً، أريد الاستفسار عن خدماتكم" target="_blank" class="btn" style="background: white; color: var(--primary); width: 100%;">
                        <i class="fab fa-whatsapp"></i>
                        ابدأ المحادثة
                    </a>
                </div>

                <!-- Trust Badges -->
                <div style="margin-top: var(--space-6); text-align: center;">
                    <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: var(--space-3);">
                        <i class="fas fa-shield-alt" style="margin-left: var(--space-2);"></i>
                        معلوماتك آمنة ومحمية
                    </p>
                    <div style="display: flex; justify-content: center; gap: var(--space-4); color: var(--text-muted);">
                        <span><i class="fas fa-lock"></i> SSL</span>
                        <span><i class="fas fa-user-shield"></i> خصوصية</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.step-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    transition: color var(--transition);
}
.step-label.active {
    color: var(--primary);
    font-weight: 600;
}
.form-step {
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 992px) {
    section > .container > div {
        grid-template-columns: 1fr !important;
    }
    .contact-sidebar {
        order: -1;
    }
}
</style>

<script>
let currentStep = 1;
const totalSteps = 3;

function nextStep(step) {
    // Validate current step
    const currentStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`);
    const requiredFields = currentStepEl.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    if (!isValid) return;

    // Move to next step
    currentStepEl.style.display = 'none';
    document.querySelector(`.form-step[data-step="${step}"]`).style.display = 'block';

    // Update progress
    currentStep = step;
    updateProgress();
}

function prevStep(step) {
    document.querySelector(`.form-step[data-step="${currentStep}"]`).style.display = 'none';
    document.querySelector(`.form-step[data-step="${step}"]`).style.display = 'block';
    currentStep = step;
    updateProgress();
}

function updateProgress() {
    const progress = (currentStep / totalSteps) * 100;
    document.getElementById('progressBar').style.width = progress + '%';

    document.querySelectorAll('.step-label').forEach(label => {
        const labelStep = parseInt(label.dataset.step);
        if (labelStep <= currentStep) {
            label.classList.add('active');
        } else {
            label.classList.remove('active');
        }
    });
}

// Form submission
document.getElementById('leadForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.disabled = true;

    try {
        const formData = new FormData(this);
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            document.querySelector('.card').innerHTML = document.getElementById('formSuccess').innerHTML;
            document.getElementById('formSuccess').style.display = 'block';
            // Show success message
            document.querySelectorAll('.form-step, .form-progress').forEach(el => el.style.display = 'none');
            document.getElementById('formSuccess').style.display = 'block';
        } else {
            showNotification('error', result.message || 'حدث خطأ. يرجى المحاولة مرة أخرى.');
            btn.classList.remove('loading');
            btn.disabled = false;
        }
    } catch (error) {
        showNotification('error', 'حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.');
        btn.classList.remove('loading');
        btn.disabled = false;
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

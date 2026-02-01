<?php
/**
 * صفحة التواصل - احجز استشارة مع خالد سعد
 * خالد سعد - خبير التسويق والتحول الرقمي
 */

require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'احجز استشارة - ' . SITE_NAME;
$pageDescription = 'احجز استشارة مجانية مع خالد سعد. سأساعدك في تحقيق أهدافك التسويقية والرقمية.';

$selectedService = isset($_GET['service']) ? clean($_GET['service']) : '';
$selectedPlan = isset($_GET['plan']) ? clean($_GET['plan']) : '';

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Contact Hero -->
<section class="contact-hero">
    <div class="container">
        <div class="contact-hero-content" data-aos="fade-up">
            <span class="section-badge"><i class="fas fa-calendar-check"></i> احجز استشارة</span>
            <h1>دعنا نتحدث عن مشروعك</h1>
            <p>أخبرني عن أهدافك وتحدياتك، وسأتواصل معك خلال 24 ساعة لمناقشة كيف يمكنني مساعدتك</p>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="contact-section">
    <div class="container">
        <div class="contact-grid">

            <!-- Multi-Step Form -->
            <div class="contact-form-wrapper" data-aos="fade-up">
                <div class="form-card">

                    <!-- Progress Bar -->
                    <div class="form-progress">
                        <div class="progress-labels">
                            <span class="step-label active" data-step="1">معلوماتك</span>
                            <span class="step-label" data-step="2">عن مشروعك</span>
                            <span class="step-label" data-step="3">التفاصيل</span>
                        </div>
                        <div class="progress-bar-track">
                            <div id="progressBar" class="progress-bar-fill"></div>
                        </div>
                    </div>

                    <form id="leadForm" action="<?= url('api/submit-lead.php') ?>" method="POST" data-validate>
                        <?= Security::csrfField() ?>
                        <?= honeypotField() ?>

                        <!-- Step 1: Basic Info -->
                        <div class="form-step active" data-step="1">
                            <div class="step-header">
                                <span class="step-icon"><i class="fas fa-user"></i></span>
                                <div>
                                    <h3>معلوماتك الأساسية</h3>
                                    <p>حتى أتمكن من التواصل معك</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="full_name">الاسم <span class="required">*</span></label>
                                <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="اسمك الكريم">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">البريد الإلكتروني <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" required placeholder="example@email.com">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="phone">رقم الهاتف (اختياري)</label>
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder="للتواصل الأسرع" dir="ltr" style="text-align: left;">
                            </div>

                            <button type="button" class="btn btn-primary w-100" onclick="nextStep(2)">
                                التالي <i class="fas fa-arrow-left"></i>
                            </button>
                        </div>

                        <!-- Step 2: Project Info -->
                        <div class="form-step" data-step="2" style="display: none;">
                            <div class="step-header">
                                <span class="step-icon"><i class="fas fa-briefcase"></i></span>
                                <div>
                                    <h3>عن مشروعك</h3>
                                    <p>ساعدني في فهم طبيعة عملك</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="company">اسم المشروع / الشركة</label>
                                <input type="text" id="company" name="company" class="form-control" placeholder="اسم مشروعك أو شركتك">
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="company_size">حجم الفريق</label>
                                <select id="company_size" name="company_size" class="form-control">
                                    <option value="">اختر</option>
                                    <option value="solo">أعمل بمفردي</option>
                                    <option value="1-10">1-10 أشخاص</option>
                                    <option value="11-50">11-50 شخص</option>
                                    <option value="51+">أكثر من 50</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="industry">المجال</label>
                                <select id="industry" name="industry" class="form-control">
                                    <option value="">اختر مجالك</option>
                                    <option value="technology">التقنية</option>
                                    <option value="ecommerce">التجارة الإلكترونية</option>
                                    <option value="retail">التجزئة</option>
                                    <option value="services">الخدمات</option>
                                    <option value="healthcare">الرعاية الصحية</option>
                                    <option value="education">التعليم</option>
                                    <option value="finance">المالية</option>
                                    <option value="real-estate">العقارات</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>

                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                                    <i class="fas fa-arrow-right"></i> السابق
                                </button>
                                <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                                    التالي <i class="fas fa-arrow-left"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Details -->
                        <div class="form-step" data-step="3" style="display: none;">
                            <div class="step-header">
                                <span class="step-icon"><i class="fas fa-comment-dots"></i></span>
                                <div>
                                    <h3>كيف أساعدك؟</h3>
                                    <p>أخبرني المزيد عن احتياجاتك</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="service_interested">الخدمة المطلوبة</label>
                                <select id="service_interested" name="service_interested" class="form-control">
                                    <option value="">اختر الخدمة</option>
                                    <option value="consulting" <?= $selectedService === 'consulting' ? 'selected' : '' ?>>الاستشارات التسويقية</option>
                                    <option value="digital" <?= $selectedService === 'digital' ? 'selected' : '' ?>>التحول الرقمي</option>
                                    <option value="branding" <?= $selectedService === 'branding' ? 'selected' : '' ?>>بناء الهوية التجارية</option>
                                    <option value="multiple">عدة خدمات</option>
                                    <option value="not_sure">غير متأكد بعد</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="message">رسالتك <span class="required">*</span></label>
                                <textarea id="message" name="message" class="form-control" rows="4" required placeholder="أخبرني عن أهدافك، التحديات التي تواجهها، أو أي أسئلة لديك..."></textarea>
                            </div>

                            <?php if ($selectedPlan): ?>
                            <input type="hidden" name="selected_plan" value="<?= e($selectedPlan) ?>">
                            <?php endif; ?>

                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                                    <i class="fas fa-arrow-right"></i> السابق
                                </button>
                                <button type="submit" class="btn btn-primary flex-2" id="submitBtn">
                                    <span class="btn-text"><i class="fas fa-paper-plane"></i> إرسال الطلب</span>
                                    <span class="btn-loading"><i class="fas fa-spinner fa-spin"></i> جاري الإرسال...</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Success Message -->
                    <div id="formSuccess" class="success-message" style="display: none;">
                        <div class="success-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3>تم إرسال طلبك بنجاح!</h3>
                        <p>شكراً لتواصلك معي. سأراجع طلبك وأتواصل معك خلال 24 ساعة.</p>
                        <a href="<?= url('') ?>" class="btn btn-primary">العودة للرئيسية</a>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="contact-sidebar" data-aos="fade-up" data-aos-delay="100">

                <!-- About Card -->
                <div class="sidebar-card about-card">
                    <div class="about-avatar">خ</div>
                    <h4>خالد سعد</h4>
                    <p>خبير التسويق والتحول الرقمي</p>
                    <div class="about-stats">
                        <div class="stat">
                            <strong>+10</strong>
                            <span>سنوات خبرة</span>
                        </div>
                        <div class="stat">
                            <strong>+150</strong>
                            <span>عميل</span>
                        </div>
                    </div>
                </div>

                <!-- What to Expect -->
                <div class="sidebar-card">
                    <h4><i class="fas fa-lightbulb"></i> ماذا تتوقع؟</h4>
                    <ul class="expect-list">
                        <li><i class="fas fa-check"></i> استشارة أولية مجانية</li>
                        <li><i class="fas fa-check"></i> تحليل أولي لوضعك الحالي</li>
                        <li><i class="fas fa-check"></i> توصيات مخصصة</li>
                        <li><i class="fas fa-check"></i> خطة عمل واضحة</li>
                    </ul>
                </div>

                <!-- Quick Contact -->
                <div class="sidebar-card whatsapp-card">
                    <h4><i class="fab fa-whatsapp"></i> تفضل المحادثة المباشرة؟</h4>
                    <p>يمكنك التواصل معي مباشرة عبر واتساب للاستفسارات السريعة</p>
                    <a href="https://wa.me/?text=<?= urlencode('مرحباً خالد، أريد الاستفسار عن خدماتك') ?>" target="_blank" class="btn btn-whatsapp">
                        <i class="fab fa-whatsapp"></i> ابدأ المحادثة
                    </a>
                </div>

                <!-- Trust -->
                <div class="trust-badges">
                    <p><i class="fas fa-shield-alt"></i> معلوماتك آمنة ومحمية</p>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Contact Page Styles */
.contact-hero {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: var(--space-12) 0;
}

.contact-hero-content {
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
}

.contact-hero-content h1 {
    font-size: var(--font-size-3xl);
    margin: var(--space-4) 0;
}

.contact-hero-content p {
    color: var(--text-secondary);
    font-size: var(--font-size-lg);
}

.section-badge {
    display: inline-block;
    padding: var(--space-2) var(--space-4);
    background: var(--primary-light);
    color: var(--primary);
    border-radius: var(--radius-full);
    font-size: var(--font-size-sm);
    font-weight: 600;
}

.section-badge i {
    margin-left: var(--space-2);
}

.contact-section {
    padding: var(--space-12) 0 var(--space-16);
}

.contact-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: var(--space-8);
    align-items: start;
}

/* Form Card */
.form-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-xl);
    padding: var(--space-8);
}

.form-progress {
    margin-bottom: var(--space-8);
}

.progress-labels {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--space-3);
}

.step-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    transition: var(--transition);
}

.step-label.active {
    color: var(--primary);
    font-weight: 600;
}

.progress-bar-track {
    height: 4px;
    background: var(--bg-tertiary);
    border-radius: var(--radius-full);
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    width: 33.33%;
    background: var(--primary);
    transition: width var(--transition);
}

.step-header {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}

.step-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: var(--font-size-xl);
}

.step-header h3 {
    margin: 0;
    font-size: var(--font-size-lg);
}

.step-header p {
    margin: 0;
    color: var(--text-muted);
    font-size: var(--font-size-sm);
}

.form-step {
    animation: fadeIn 0.3s ease;
}

.btn-group {
    display: flex;
    gap: var(--space-4);
}

.btn-group .btn:first-child {
    flex: 1;
}

.btn-group .btn:last-child {
    flex: 2;
}

.flex-2 {
    flex: 2 !important;
}

/* Success Message */
.success-message {
    text-align: center;
    padding: var(--space-8);
}

.success-icon {
    width: 80px;
    height: 80px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--space-6);
}

.success-icon i {
    font-size: 2rem;
    color: var(--success);
}

.success-message h3 {
    margin-bottom: var(--space-3);
}

.success-message p {
    color: var(--text-secondary);
    margin-bottom: var(--space-6);
}

/* Sidebar */
.sidebar-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-xl);
    padding: var(--space-6);
    margin-bottom: var(--space-4);
}

.sidebar-card h4 {
    margin-bottom: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.sidebar-card h4 i {
    color: var(--primary);
}

/* About Card */
.about-card {
    text-align: center;
}

.about-avatar {
    width: 70px;
    height: 70px;
    background: var(--primary);
    color: white;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-2xl);
    font-weight: 700;
    margin: 0 auto var(--space-3);
}

.about-card h4 {
    justify-content: center;
    margin-bottom: var(--space-1);
}

.about-card > p {
    color: var(--text-muted);
    font-size: var(--font-size-sm);
    margin-bottom: var(--space-4);
}

.about-stats {
    display: flex;
    justify-content: center;
    gap: var(--space-6);
    padding-top: var(--space-4);
    border-top: 1px solid var(--border-color);
}

.about-stats .stat {
    text-align: center;
}

.about-stats strong {
    display: block;
    font-size: var(--font-size-xl);
    color: var(--primary);
}

.about-stats span {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

/* Expect List */
.expect-list {
    list-style: none;
}

.expect-list li {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-2) 0;
    color: var(--text-secondary);
}

.expect-list li i {
    color: var(--success);
    font-size: var(--font-size-sm);
}

/* WhatsApp Card */
.whatsapp-card {
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
}

.whatsapp-card h4 {
    color: white;
}

.whatsapp-card h4 i {
    color: white;
}

.whatsapp-card p {
    color: rgba(255,255,255,0.9);
    font-size: var(--font-size-sm);
    margin-bottom: var(--space-4);
}

.btn-whatsapp {
    background: white;
    color: #25D366;
    width: 100%;
}

.btn-whatsapp:hover {
    background: rgba(255,255,255,0.9);
}

/* Trust */
.trust-badges {
    text-align: center;
    padding: var(--space-4);
}

.trust-badges p {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.trust-badges i {
    margin-left: var(--space-2);
}

/* Responsive */
@media (max-width: 992px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }

    .contact-sidebar {
        order: -1;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: var(--space-4);
    }

    .sidebar-card {
        margin-bottom: 0;
    }
}

@media (max-width: 576px) {
    .form-card {
        padding: var(--space-6);
    }

    .contact-sidebar {
        grid-template-columns: 1fr;
    }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
let currentStep = 1;
const totalSteps = 3;

function nextStep(step) {
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

    currentStepEl.style.display = 'none';
    document.querySelector(`.form-step[data-step="${step}"]`).style.display = 'block';
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

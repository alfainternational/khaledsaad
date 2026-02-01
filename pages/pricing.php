<?php
/**
 * صفحة الأسعار
 * موقع خالد سعد للاستشارات
 */

require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'باقات الأسعار - ' . SITE_NAME;
$pageDescription = 'اكتشف باقات الأسعار المرنة التي تناسب احتياجاتك وميزانيتك. خطط مخصصة للشركات الناشئة والمتوسطة والكبيرة.';

// جلب الباقات
try {
    $plans = db()->fetchAll("SELECT * FROM pricing_plans WHERE status = 'active' ORDER BY sort_order ASC");
} catch (Exception $e) {
    $plans = [];
}

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero" style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); padding: var(--space-16) 0;">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <span class="hero-badge">
                <i class="fas fa-tags"></i>
                الأسعار
            </span>
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-4);">خطط مرنة تناسب احتياجاتك</h1>
            <p style="font-size: var(--font-size-lg); color: var(--text-secondary); max-width: 600px; margin: 0 auto;">
                اختر الباقة المناسبة لحجم عملك وميزانيتك. جميع الباقات قابلة للتخصيص حسب متطلباتك
            </p>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section style="padding: var(--space-16) 0;">
    <div class="container">
        <div class="pricing-grid">
            <!-- Starter Plan -->
            <div class="pricing-card" data-aos="fade-up" data-aos-delay="100">
                <div class="pricing-header">
                    <h3>باقة الانطلاق</h3>
                    <p>مثالية للشركات الناشئة والمشاريع الصغيرة</p>
                    <div class="price">
                        <span class="price-amount">5,000</span>
                        <span class="price-currency">ر.س</span>
                        <span class="price-period">/ شهرياً</span>
                    </div>
                </div>
                <ul class="pricing-features">
                    <li><i class="fas fa-check"></i><span>تحليل السوق الأولي</span></li>
                    <li><i class="fas fa-check"></i><span>استراتيجية تسويق أساسية</span></li>
                    <li><i class="fas fa-check"></i><span>تقرير أداء شهري</span></li>
                    <li><i class="fas fa-check"></i><span>دعم عبر البريد الإلكتروني</span></li>
                    <li><i class="fas fa-check"></i><span>جلسة استشارية شهرية (1 ساعة)</span></li>
                    <li><i class="fas fa-check"></i><span>إدارة حساب واحد للتواصل الاجتماعي</span></li>
                    <li class="disabled"><i class="fas fa-times"></i><span>إدارة الحملات الإعلانية</span></li>
                    <li class="disabled"><i class="fas fa-times"></i><span>تدريب الفريق</span></li>
                </ul>
                <a href="<?= url('pages/contact.php?plan=starter') ?>" class="btn btn-outline w-100">ابدأ الآن</a>
            </div>

            <!-- Growth Plan (Popular) -->
            <div class="pricing-card popular" data-aos="fade-up" data-aos-delay="200">
                <div class="pricing-header">
                    <h3>باقة النمو</h3>
                    <p>للشركات في مرحلة التوسع والنمو</p>
                    <div class="price">
                        <span class="price-amount">10,000</span>
                        <span class="price-currency">ر.س</span>
                        <span class="price-period">/ شهرياً</span>
                    </div>
                </div>
                <ul class="pricing-features">
                    <li><i class="fas fa-check"></i><span>تحليل شامل للسوق والمنافسين</span></li>
                    <li><i class="fas fa-check"></i><span>استراتيجية تسويق متكاملة</span></li>
                    <li><i class="fas fa-check"></i><span>تقارير أداء أسبوعية</span></li>
                    <li><i class="fas fa-check"></i><span>دعم على مدار الساعة</span></li>
                    <li><i class="fas fa-check"></i><span>4 جلسات استشارية شهرياً</span></li>
                    <li><i class="fas fa-check"></i><span>إدارة 3 حسابات للتواصل الاجتماعي</span></li>
                    <li><i class="fas fa-check"></i><span>إدارة الحملات الإعلانية</span></li>
                    <li><i class="fas fa-check"></i><span>تحسين محركات البحث (SEO)</span></li>
                </ul>
                <a href="<?= url('pages/contact.php?plan=growth') ?>" class="btn btn-primary w-100">ابدأ الآن</a>
            </div>

            <!-- Enterprise Plan -->
            <div class="pricing-card" data-aos="fade-up" data-aos-delay="300">
                <div class="pricing-header">
                    <h3>باقة المؤسسات</h3>
                    <p>حلول مخصصة للمؤسسات الكبيرة</p>
                    <div class="price">
                        <span class="price-amount">25,000</span>
                        <span class="price-currency">ر.س</span>
                        <span class="price-period">/ شهرياً</span>
                    </div>
                </div>
                <ul class="pricing-features">
                    <li><i class="fas fa-check"></i><span>تحليل معمق للسوق والمنافسين</span></li>
                    <li><i class="fas fa-check"></i><span>استراتيجية شاملة ومخصصة</span></li>
                    <li><i class="fas fa-check"></i><span>تقارير يومية ولوحة تحكم خاصة</span></li>
                    <li><i class="fas fa-check"></i><span>فريق دعم مخصص</span></li>
                    <li><i class="fas fa-check"></i><span>جلسات استشارية غير محدودة</span></li>
                    <li><i class="fas fa-check"></i><span>إدارة جميع قنوات التواصل</span></li>
                    <li><i class="fas fa-check"></i><span>إدارة كاملة للحملات الإعلانية</span></li>
                    <li><i class="fas fa-check"></i><span>تدريب الفريق (8 ساعات/شهر)</span></li>
                </ul>
                <a href="<?= url('pages/contact.php?plan=enterprise') ?>" class="btn btn-outline w-100">تواصل معنا</a>
            </div>
        </div>
    </div>
</section>

<!-- Additional Services -->
<section style="padding: var(--space-16) 0; background: var(--bg-secondary);">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">خدمات إضافية</span>
            <h2>خدمات يمكن إضافتها</h2>
            <p>عزز باقتك بخدمات إضافية حسب احتياجاتك</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
            <div class="card" style="padding: var(--space-6);" data-aos="fade-up" data-aos-delay="100">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4);">
                    <div style="width: 50px; height: 50px; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-video" style="color: var(--primary); font-size: 1.25rem;"></i>
                    </div>
                    <span style="font-weight: 700; color: var(--primary);">2,000 ر.س</span>
                </div>
                <h4 style="margin-bottom: var(--space-2);">إنتاج فيديو تسويقي</h4>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin: 0;">فيديو احترافي (30-60 ثانية) مع السيناريو والمونتاج</p>
            </div>

            <div class="card" style="padding: var(--space-6);" data-aos="fade-up" data-aos-delay="200">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4);">
                    <div style="width: 50px; height: 50px; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-palette" style="color: var(--primary); font-size: 1.25rem;"></i>
                    </div>
                    <span style="font-weight: 700; color: var(--primary);">5,000 ر.س</span>
                </div>
                <h4 style="margin-bottom: var(--space-2);">تصميم الهوية البصرية</h4>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin: 0;">شعار + ألوان + خطوط + دليل الهوية</p>
            </div>

            <div class="card" style="padding: var(--space-6);" data-aos="fade-up" data-aos-delay="300">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4);">
                    <div style="width: 50px; height: 50px; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-globe" style="color: var(--primary); font-size: 1.25rem;"></i>
                    </div>
                    <span style="font-weight: 700; color: var(--primary);">8,000 ر.س</span>
                </div>
                <h4 style="margin-bottom: var(--space-2);">تصميم موقع إلكتروني</h4>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin: 0;">موقع احترافي متجاوب (5-10 صفحات)</p>
            </div>

            <div class="card" style="padding: var(--space-6);" data-aos="fade-up" data-aos-delay="400">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4);">
                    <div style="width: 50px; height: 50px; background: rgba(37, 99, 235, 0.1); border-radius: var(--radius); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chalkboard-teacher" style="color: var(--primary); font-size: 1.25rem;"></i>
                    </div>
                    <span style="font-weight: 700; color: var(--primary);">3,000 ر.س</span>
                </div>
                <h4 style="margin-bottom: var(--space-2);">ورشة عمل تدريبية</h4>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm); margin: 0;">يوم تدريبي كامل (6 ساعات) لفريقك</p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section style="padding: var(--space-16) 0;">
    <div class="container" style="max-width: 800px;">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">أسئلة شائعة</span>
            <h2>الأسئلة المتكررة</h2>
        </div>

        <div class="faq-list" data-aos="fade-up">
            <details class="faq-item" style="border: 1px solid var(--border-color); border-radius: var(--radius-lg); margin-bottom: var(--space-4); overflow: hidden;">
                <summary style="padding: var(--space-5); cursor: pointer; font-weight: 600; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary);">
                    هل يمكنني تغيير الباقة لاحقاً؟
                    <i class="fas fa-chevron-down" style="transition: transform var(--transition);"></i>
                </summary>
                <div style="padding: var(--space-5); border-top: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary);">نعم، يمكنك الترقية أو تخفيض باقتك في أي وقت. سيتم احتساب الفرق بالتناسب مع الفترة المتبقية.</p>
                </div>
            </details>

            <details class="faq-item" style="border: 1px solid var(--border-color); border-radius: var(--radius-lg); margin-bottom: var(--space-4); overflow: hidden;">
                <summary style="padding: var(--space-5); cursor: pointer; font-weight: 600; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary);">
                    ما هي مدة العقد؟
                    <i class="fas fa-chevron-down" style="transition: transform var(--transition);"></i>
                </summary>
                <div style="padding: var(--space-5); border-top: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary);">الحد الأدنى للعقد هو 3 أشهر لضمان تحقيق نتائج ملموسة. بعدها يمكنك التجديد شهرياً.</p>
                </div>
            </details>

            <details class="faq-item" style="border: 1px solid var(--border-color); border-radius: var(--radius-lg); margin-bottom: var(--space-4); overflow: hidden;">
                <summary style="padding: var(--space-5); cursor: pointer; font-weight: 600; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary);">
                    هل هناك ضمان للنتائج؟
                    <i class="fas fa-chevron-down" style="transition: transform var(--transition);"></i>
                </summary>
                <div style="padding: var(--space-5); border-top: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary);">نحن ملتزمون بتحقيق الأهداف المتفق عليها. نقدم تقارير شفافة ونعمل معك على تحسين الأداء باستمرار. إذا لم تكن راضياً، نقدم ضمان استرداد خلال أول 30 يوماً.</p>
                </div>
            </details>

            <details class="faq-item" style="border: 1px solid var(--border-color); border-radius: var(--radius-lg); margin-bottom: var(--space-4); overflow: hidden;">
                <summary style="padding: var(--space-5); cursor: pointer; font-weight: 600; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary);">
                    هل تشمل الأسعار ميزانية الإعلانات؟
                    <i class="fas fa-chevron-down" style="transition: transform var(--transition);"></i>
                </summary>
                <div style="padding: var(--space-5); border-top: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary);">الأسعار المذكورة هي رسوم الإدارة فقط. ميزانية الإعلانات يتم تحديدها حسب أهدافك ويتم دفعها مباشرة للمنصات الإعلانية.</p>
                </div>
            </details>

            <details class="faq-item" style="border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden;">
                <summary style="padding: var(--space-5); cursor: pointer; font-weight: 600; display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary);">
                    كيف يتم التواصل والمتابعة؟
                    <i class="fas fa-chevron-down" style="transition: transform var(--transition);"></i>
                </summary>
                <div style="padding: var(--space-5); border-top: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary);">سيتم تعيين مدير حساب مخصص لك. التواصل يتم عبر الواتساب، البريد، واجتماعات دورية عبر Zoom حسب الباقة.</p>
                </div>
            </details>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section" data-aos="fade-up">
    <div class="container">
        <h2>لست متأكداً من الباقة المناسبة؟</h2>
        <p>تواصل معنا للحصول على استشارة مجانية وسنساعدك في اختيار الأنسب لاحتياجاتك</p>
        <div class="d-flex justify-center gap-4 flex-wrap">
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-calendar-check"></i>
                احجز استشارة مجانية
            </a>
            <a href="tel:+966500000000" class="btn btn-outline btn-lg">
                <i class="fas fa-phone-alt"></i>
                اتصل بنا
            </a>
        </div>
    </div>
</section>

<style>
details[open] summary i {
    transform: rotate(180deg);
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

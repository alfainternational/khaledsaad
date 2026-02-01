<?php
/**
 * صفحة من نحن
 * موقع خالد سعد للاستشارات
 */

require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'من نحن - ' . SITE_NAME;
$pageDescription = 'تعرف على خالد سعد للاستشارات - شركة استشارات تسويقية رائدة في السعودية. خبرة تتجاوز 10 سنوات في التحول الرقمي وبناء العلامات التجارية.';

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero" style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); padding: var(--space-16) 0;">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <span class="hero-badge">
                <i class="fas fa-info-circle"></i>
                من نحن
            </span>
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-4);">شريكك في رحلة النجاح</h1>
            <p style="font-size: var(--font-size-lg); color: var(--text-secondary); max-width: 700px; margin: 0 auto;">
                نحن فريق من الخبراء المتخصصين في التسويق والتحول الرقمي، نساعد الشركات في تحقيق نمو مستدام
            </p>
        </div>
    </div>
</section>

<!-- Story Section -->
<section style="padding: var(--space-16) 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-12); align-items: center;">
            <div data-aos="fade-up">
                <span class="badge" style="margin-bottom: var(--space-4);">قصتنا</span>
                <h2 style="margin-bottom: var(--space-4);">من الفكرة إلى الريادة</h2>
                <p style="color: var(--text-secondary); margin-bottom: var(--space-4); font-size: var(--font-size-lg);">
                    بدأت رحلتنا في عام 2014 برؤية واضحة: مساعدة الشركات العربية في التحول الرقمي والنمو في عصر التكنولوجيا. على مدار أكثر من 10 سنوات، عملنا مع أكثر من 150 شركة في مختلف القطاعات.
                </p>
                <p style="color: var(--text-secondary); margin-bottom: var(--space-6);">
                    نؤمن بأن كل شركة تستحق استراتيجية تسويقية مخصصة تناسب احتياجاتها وأهدافها. لهذا نقدم حلولاً مبتكرة تجمع بين الخبرة المحلية والمعايير العالمية.
                </p>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-6);">
                    <div>
                        <span style="display: block; font-size: var(--font-size-3xl); font-weight: 800; color: var(--primary);" data-counter="10" data-suffix="+">0+</span>
                        <span style="color: var(--text-muted); font-size: var(--font-size-sm);">سنوات خبرة</span>
                    </div>
                    <div>
                        <span style="display: block; font-size: var(--font-size-3xl); font-weight: 800; color: var(--primary);" data-counter="150" data-suffix="+">0+</span>
                        <span style="color: var(--text-muted); font-size: var(--font-size-sm);">عميل راضٍ</span>
                    </div>
                    <div>
                        <span style="display: block; font-size: var(--font-size-3xl); font-weight: 800; color: var(--primary);" data-counter="95" data-suffix="%">0%</span>
                        <span style="color: var(--text-muted); font-size: var(--font-size-sm);">نسبة النجاح</span>
                    </div>
                </div>
            </div>
            <div data-aos="fade-up" data-aos-delay="200" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: var(--radius-xl); padding: var(--space-12); display: flex; align-items: center; justify-content: center; min-height: 450px;">
                <i class="fas fa-rocket" style="font-size: 8rem; color: white; opacity: 0.9;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section style="padding: var(--space-16) 0; background: var(--bg-secondary);">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">قيمنا</span>
            <h2>المبادئ التي توجهنا</h2>
            <p>نلتزم بمجموعة من القيم الأساسية التي تحكم عملنا وعلاقاتنا مع عملائنا</p>
        </div>

        <div class="services-grid">
            <div class="service-card" data-aos="fade-up" data-aos-delay="100">
                <div class="service-icon" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3>الشراكة الحقيقية</h3>
                <p>نعتبر أنفسنا شركاء لعملائنا، نجاحهم هو نجاحنا. نعمل معهم جنباً إلى جنب لتحقيق أهدافهم.</p>
            </div>
            <div class="service-card" data-aos="fade-up" data-aos-delay="200">
                <div class="service-icon" style="background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3>الابتكار المستمر</h3>
                <p>نبحث دائماً عن طرق جديدة ومبتكرة لحل التحديات وتحقيق نتائج استثنائية لعملائنا.</p>
            </div>
            <div class="service-card" data-aos="fade-up" data-aos-delay="300">
                <div class="service-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>التركيز على النتائج</h3>
                <p>نقيس نجاحنا بنتائج عملائنا. كل استراتيجية نضعها مبنية على أهداف واضحة وقابلة للقياس.</p>
            </div>
            <div class="service-card" data-aos="fade-up" data-aos-delay="400">
                <div class="service-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>النزاهة والشفافية</h3>
                <p>نلتزم بالصدق والشفافية في كل تعاملاتنا. نقدم تقارير واضحة ونشارك النجاحات والتحديات بصراحة.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section style="padding: var(--space-16) 0;">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">فريقنا</span>
            <h2>الخبراء خلف نجاحك</h2>
            <p>فريق من المتخصصين ذوي الخبرة العميقة في مجالاتهم</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6);">
            <div class="card" style="text-align: center; padding: var(--space-6);" data-aos="fade-up" data-aos-delay="100">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border-radius: var(--radius-full); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white; font-weight: 700;">
                    خ
                </div>
                <h3 style="margin-bottom: var(--space-1);">خالد سعد</h3>
                <p style="color: var(--primary); font-size: var(--font-size-sm); margin-bottom: var(--space-3);">المؤسس والرئيس التنفيذي</p>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                    خبرة +15 سنة في التسويق الاستراتيجي والتحول الرقمي. عمل مع أكبر الشركات في المنطقة.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: var(--space-6);" data-aos="fade-up" data-aos-delay="200">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%); border-radius: var(--radius-full); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white; font-weight: 700;">
                    ن
                </div>
                <h3 style="margin-bottom: var(--space-1);">نورة الأحمد</h3>
                <p style="color: var(--primary); font-size: var(--font-size-sm); margin-bottom: var(--space-3);">مديرة التسويق الرقمي</p>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                    متخصصة في إدارة الحملات الإعلانية وتحسين معدلات التحويل. شهادات معتمدة من Google وMeta.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: var(--space-6);" data-aos="fade-up" data-aos-delay="300">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--success) 0%, #059669 100%); border-radius: var(--radius-full); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white; font-weight: 700;">
                    ع
                </div>
                <h3 style="margin-bottom: var(--space-1);">عبدالله الشمري</h3>
                <p style="color: var(--primary); font-size: var(--font-size-sm); margin-bottom: var(--space-3);">مدير التحول الرقمي</p>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                    خبير في رقمنة العمليات وتكامل الأنظمة. قاد مشاريع تحول رقمي لأكثر من 50 شركة.
                </p>
            </div>

            <div class="card" style="text-align: center; padding: var(--space-6);" data-aos="fade-up" data-aos-delay="400">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); border-radius: var(--radius-full); margin: 0 auto var(--space-4); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white; font-weight: 700;">
                    س
                </div>
                <h3 style="margin-bottom: var(--space-1);">سارة العتيبي</h3>
                <p style="color: var(--primary); font-size: var(--font-size-sm); margin-bottom: var(--space-3);">مديرة تطوير الأعمال</p>
                <p style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                    متخصصة في بناء العلاقات مع العملاء وتطوير الشراكات الاستراتيجية.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Certifications -->
<section style="padding: var(--space-12) 0; background: var(--bg-secondary);">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <h3 style="margin-bottom: var(--space-6); color: var(--text-muted);">شركاؤنا ومعتمدون من</h3>
            <div style="display: flex; justify-content: center; align-items: center; gap: var(--space-12); flex-wrap: wrap; opacity: 0.7;">
                <div style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--font-size-xl); font-weight: 700; color: var(--text-secondary);">
                    <i class="fab fa-google" style="font-size: 2rem;"></i> Google Partner
                </div>
                <div style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--font-size-xl); font-weight: 700; color: var(--text-secondary);">
                    <i class="fab fa-meta" style="font-size: 2rem;"></i> Meta Partner
                </div>
                <div style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--font-size-xl); font-weight: 700; color: var(--text-secondary);">
                    <i class="fab fa-hubspot" style="font-size: 2rem;"></i> HubSpot
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section" data-aos="fade-up">
    <div class="container">
        <h2>هل أنت مستعد للانطلاق؟</h2>
        <p>تواصل معنا اليوم واكتشف كيف يمكننا مساعدتك في تحقيق أهدافك</p>
        <div class="d-flex justify-center gap-4 flex-wrap">
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-calendar-check"></i>
                احجز استشارة مجانية
            </a>
        </div>
    </div>
</section>

<style>
@media (max-width: 992px) {
    section > .container > div:first-child {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

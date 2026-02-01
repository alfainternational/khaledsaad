<?php
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'خدماتنا - ' . SITE_NAME;
$pageDescription = 'نقدم خدمات استشارية متكاملة في التسويق الرقمي، التحول الرقمي، بناء الهوية التجارية، والتدريب.';

include dirname(__DIR__) . '/includes/header.php';
?>

<section style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); padding: var(--space-16) 0;">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <span class="hero-badge"><i class="fas fa-cogs"></i> خدماتنا</span>
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-4);">حلول متكاملة لنمو أعمالك</h1>
            <p style="font-size: var(--font-size-lg); color: var(--text-secondary); max-width: 700px; margin: 0 auto;">نقدم مجموعة شاملة من الخدمات الاستشارية المصممة خصيصاً لتلبية احتياجات عملك وتحقيق أهدافك</p>
        </div>
    </div>
</section>

<!-- Service 1: Marketing Consulting -->
<section id="consulting" style="padding: var(--space-16) 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-12); align-items: center;">
            <div data-aos="fade-up">
                <span class="badge" style="margin-bottom: var(--space-4);">الخدمة الأولى</span>
                <h2 style="font-size: var(--font-size-3xl); margin-bottom: var(--space-4);">الاستشارات التسويقية</h2>
                <p style="font-size: var(--font-size-lg); color: var(--text-secondary); margin-bottom: var(--space-6);">نصمم استراتيجيات تسويقية مخصصة مبنية على تحليل معمق للسوق والمنافسين لتحقيق نمو مستدام وزيادة العائد على الاستثمار.</p>
                <ul style="list-style: none; margin-bottom: var(--space-6);">
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> تحليل السوق والمنافسين</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> وضع استراتيجية التسويق</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> تحسين معدلات التحويل</li>
                    <li style="display: flex; gap: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> إدارة الحملات الإعلانية</li>
                </ul>
                <a href="<?= url('pages/contact.php?service=consulting') ?>" class="btn btn-primary">طلب الخدمة</a>
            </div>
            <div data-aos="fade-up" data-aos-delay="200" style="background: var(--bg-secondary); border-radius: var(--radius-xl); padding: var(--space-8); text-align: center;">
                <i class="fas fa-chart-line" style="font-size: 6rem; color: var(--primary);"></i>
            </div>
        </div>
    </div>
</section>

<!-- Service 2: Digital Transformation -->
<section id="digital" style="padding: var(--space-16) 0; background: var(--bg-secondary);">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-12); align-items: center;">
            <div data-aos="fade-up" style="order: 2;">
                <span class="badge" style="margin-bottom: var(--space-4);">الخدمة الثانية</span>
                <h2 style="font-size: var(--font-size-3xl); margin-bottom: var(--space-4);">التحول الرقمي</h2>
                <p style="font-size: var(--font-size-lg); color: var(--text-secondary); margin-bottom: var(--space-6);">نساعدك في رقمنة عملياتك وتحسين الكفاءة التشغيلية باستخدام أحدث التقنيات والأدوات الرقمية.</p>
                <ul style="list-style: none; margin-bottom: var(--space-6);">
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> تقييم الجاهزية الرقمية</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> أتمتة العمليات</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> تكامل الأنظمة</li>
                    <li style="display: flex; gap: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> تحليل البيانات</li>
                </ul>
                <a href="<?= url('pages/contact.php?service=digital') ?>" class="btn btn-primary">طلب الخدمة</a>
            </div>
            <div data-aos="fade-up" data-aos-delay="200" style="background: var(--bg-primary); border-radius: var(--radius-xl); padding: var(--space-8); text-align: center; order: 1;">
                <i class="fas fa-laptop-code" style="font-size: 6rem; color: var(--primary);"></i>
            </div>
        </div>
    </div>
</section>

<!-- Service 3: Branding -->
<section id="branding" style="padding: var(--space-16) 0;">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-12); align-items: center;">
            <div data-aos="fade-up">
                <span class="badge" style="margin-bottom: var(--space-4);">الخدمة الثالثة</span>
                <h2 style="font-size: var(--font-size-3xl); margin-bottom: var(--space-4);">بناء الهوية التجارية</h2>
                <p style="font-size: var(--font-size-lg); color: var(--text-secondary); margin-bottom: var(--space-6);">نصمم هوية تجارية مميزة ومؤثرة تعكس قيم شركتك وتترك انطباعاً لا يُنسى لدى عملائك.</p>
                <ul style="list-style: none; margin-bottom: var(--space-6);">
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> تصميم الشعار والهوية البصرية</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> دليل الهوية التجارية</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> المواد التسويقية</li>
                    <li style="display: flex; gap: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> استراتيجية العلامة التجارية</li>
                </ul>
                <a href="<?= url('pages/contact.php?service=branding') ?>" class="btn btn-primary">طلب الخدمة</a>
            </div>
            <div data-aos="fade-up" data-aos-delay="200" style="background: var(--bg-secondary); border-radius: var(--radius-xl); padding: var(--space-8); text-align: center;">
                <i class="fas fa-palette" style="font-size: 6rem; color: var(--primary);"></i>
            </div>
        </div>
    </div>
</section>

<!-- Service 4: Training -->
<section id="training" style="padding: var(--space-16) 0; background: var(--bg-secondary);">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-12); align-items: center;">
            <div data-aos="fade-up" style="order: 2;">
                <span class="badge" style="margin-bottom: var(--space-4);">الخدمة الرابعة</span>
                <h2 style="font-size: var(--font-size-3xl); margin-bottom: var(--space-4);">التدريب والتطوير</h2>
                <p style="font-size: var(--font-size-lg); color: var(--text-secondary); margin-bottom: var(--space-6);">برامج تدريبية متخصصة لتطوير مهارات فريقك وتعزيز قدراتهم التسويقية والرقمية.</p>
                <ul style="list-style: none; margin-bottom: var(--space-6);">
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> ورش عمل تفاعلية</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> تدريب على الأدوات الرقمية</li>
                    <li style="display: flex; gap: var(--space-3); margin-bottom: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> برامج مخصصة للفرق</li>
                    <li style="display: flex; gap: var(--space-3);"><i class="fas fa-check-circle" style="color: var(--success);"></i> متابعة وتقييم الأداء</li>
                </ul>
                <a href="<?= url('pages/contact.php?service=training') ?>" class="btn btn-primary">طلب الخدمة</a>
            </div>
            <div data-aos="fade-up" data-aos-delay="200" style="background: var(--bg-primary); border-radius: var(--radius-xl); padding: var(--space-8); text-align: center; order: 1;">
                <i class="fas fa-users" style="font-size: 6rem; color: var(--primary);"></i>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section" data-aos="fade-up">
    <div class="container">
        <h2>هل أنت مستعد للبدء؟</h2>
        <p>احجز استشارة مجانية وناقش احتياجاتك مع خبرائنا</p>
        <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg"><i class="fas fa-calendar-check"></i> احجز الآن</a>
    </div>
</section>

<style>
@media (max-width: 768px) {
    section > .container > div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    section > .container > div > div[style*="order: 2"] {
        order: 0 !important;
    }
    section > .container > div > div[style*="order: 1"] {
        order: 0 !important;
    }
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

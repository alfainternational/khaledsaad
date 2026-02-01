<?php
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'قصص النجاح - ' . SITE_NAME;
$pageDescription = 'اكتشف كيف ساعدنا عملاءنا في تحقيق نتائج استثنائية وتحويل تحدياتهم إلى فرص نمو.';

include dirname(__DIR__) . '/includes/header.php';
?>

<section style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%); padding: var(--space-16) 0;">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <span class="hero-badge"><i class="fas fa-trophy"></i> قصص النجاح</span>
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-4);">نتائج حقيقية، عملاء راضون</h1>
            <p style="font-size: var(--font-size-lg); color: var(--text-secondary); max-width: 600px; margin: 0 auto;">اكتشف كيف ساعدنا الشركات في تحقيق أهدافها وتجاوز توقعاتها</p>
        </div>
    </div>
</section>

<section style="padding: var(--space-16) 0;">
    <div class="container">
        <div class="testimonials-grid">
            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100">
                <div class="testimonial-content">
                    <p>"بفضل خبرة فريق خالد سعد، تمكنا من زيادة مبيعاتنا بنسبة 340% خلال 6 أشهر فقط. الاستراتيجية التي وضعوها كانت واضحة وفعالة ومبنية على فهم عميق لسوقنا."</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar" style="background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">م</div>
                    <div class="author-info">
                        <h4>محمد العمري</h4>
                        <p>المدير التنفيذي - شركة تقنية الابتكار</p>
                    </div>
                </div>
                <div class="testimonial-metrics">
                    <div class="metric"><span class="metric-value">+340%</span><span class="metric-label">زيادة المبيعات</span></div>
                    <div class="metric"><span class="metric-value">6 أشهر</span><span class="metric-label">الفترة الزمنية</span></div>
                    <div class="metric"><span class="metric-value">5X</span><span class="metric-label">العائد على الاستثمار</span></div>
                </div>
            </div>

            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="200">
                <div class="testimonial-content">
                    <p>"التحول الرقمي الذي ساعدنا فريق خالد سعد في تنفيذه غيّر طريقة عملنا بالكامل. أصبحنا أكثر كفاءة وقدرة على المنافسة في السوق."</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar" style="background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">س</div>
                    <div class="author-info">
                        <h4>سارة الخالدي</h4>
                        <p>مديرة التسويق - مجموعة الرياض التجارية</p>
                    </div>
                </div>
                <div class="testimonial-metrics">
                    <div class="metric"><span class="metric-value">-60%</span><span class="metric-label">تخفيض التكاليف</span></div>
                    <div class="metric"><span class="metric-value">+200%</span><span class="metric-label">زيادة الإنتاجية</span></div>
                    <div class="metric"><span class="metric-value">100%</span><span class="metric-label">رقمنة العمليات</span></div>
                </div>
            </div>

            <div class="testimonial-card" data-aos="fade-up" data-aos-delay="300">
                <div class="testimonial-content">
                    <p>"الهوية التجارية الجديدة التي صممها الفريق منحتنا مظهراً احترافياً ومتميزاً في السوق. أوصي بشدة بالتعامل معهم لكل من يريد التميز."</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar" style="background: var(--success); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">أ</div>
                    <div class="author-info">
                        <h4>أحمد الغامدي</h4>
                        <p>مؤسس - مطاعم الذواقة</p>
                    </div>
                </div>
                <div class="testimonial-metrics">
                    <div class="metric"><span class="metric-value">+150%</span><span class="metric-label">زيادة الوعي</span></div>
                    <div class="metric"><span class="metric-value">3</span><span class="metric-label">فروع جديدة</span></div>
                    <div class="metric"><span class="metric-value">+80%</span><span class="metric-label">زيادة العملاء</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section" data-aos="fade-up">
    <div class="container">
        <h2>هل تريد أن تكون قصة النجاح التالية؟</h2>
        <p>تواصل معنا اليوم ولنبدأ رحلة نجاحك</p>
        <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg"><i class="fas fa-calendar-check"></i> احجز استشارة مجانية</a>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

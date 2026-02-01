<?php
/**
 * صفحة من أنا
 * خالد سعد - خبير التسويق والتحول الرقمي
 */

require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'من أنا - ' . SITE_NAME;
$pageDescription = 'تعرف على خالد سعد، خبير التسويق والتحول الرقمي. خبرة تتجاوز 10 سنوات في مساعدة الشركات على النمو.';

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- About Hero -->
<section class="about-hero">
    <div class="container">
        <div class="about-hero-grid">
            <div class="about-hero-content" data-aos="fade-up">
                <span class="section-badge">من أنا</span>
                <h1>خالد سعد</h1>
                <p class="hero-subtitle">خبير التسويق والتحول الرقمي</p>
                <p class="hero-desc">
                    أساعد رواد الأعمال والشركات في بناء استراتيجيات تسويقية فعّالة وتحقيق التحول الرقمي الذي يحقق نتائج ملموسة.
                </p>
                <div class="hero-stats">
                    <div class="stat">
                        <span class="stat-number">+10</span>
                        <span class="stat-label">سنوات خبرة</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">+150</span>
                        <span class="stat-label">عميل</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">95%</span>
                        <span class="stat-label">نسبة رضا</span>
                    </div>
                </div>
            </div>
            <div class="about-hero-visual" data-aos="fade-up" data-aos-delay="100">
                <div class="avatar-large">خ</div>
            </div>
        </div>
    </div>
</section>

<!-- My Story -->
<section class="story-section">
    <div class="container">
        <div class="story-content" data-aos="fade-up">
            <h2>قصتي</h2>
            <div class="story-text">
                <p>
                    بدأت رحلتي في عالم التسويق منذ أكثر من عشر سنوات، حين أدركت أن كثيراً من المشاريع الرائعة تفشل ليس لضعف منتجاتها، بل لعدم قدرتها على إيصال قيمتها للعملاء المناسبين.
                </p>
                <p>
                    عملت مع شركات في مختلف المراحل والأحجام - من مشاريع ناشئة بدأت من الصفر، إلى شركات راسخة تسعى للتحول الرقمي. هذا التنوع أعطاني فهماً عميقاً للتحديات المختلفة التي تواجه كل مرحلة.
                </p>
                <p>
                    أؤمن بأن التسويق الفعّال ليس مجرد إعلانات وحملات، بل هو فهم عميق للعميل وبناء جسور تواصل حقيقية. لذلك أركز دائماً على الاستراتيجيات المبنية على البيانات والتي تحقق نتائج قابلة للقياس.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- My Approach -->
<section class="approach-section">
    <div class="container">
        <div class="section-intro" data-aos="fade-up">
            <h2>منهجيتي في العمل</h2>
            <p>أؤمن بمبادئ واضحة توجه كل مشروع أعمل عليه</p>
        </div>

        <div class="approach-grid">
            <div class="approach-card" data-aos="fade-up">
                <div class="approach-icon"><i class="fas fa-ear-listen"></i></div>
                <h3>الاستماع أولاً</h3>
                <p>أبدأ كل مشروع بفهم عملك بعمق. أسئلتي أكثر من إجاباتي في البداية، لأن الفهم الصحيح هو أساس الاستراتيجية الناجحة.</p>
            </div>
            <div class="approach-card" data-aos="fade-up" data-aos-delay="100">
                <div class="approach-icon"><i class="fas fa-chart-pie"></i></div>
                <h3>قرارات مبنية على البيانات</h3>
                <p>لا أعتمد على الحدس فقط. كل توصية أقدمها مدعومة بتحليل وبيانات حقيقية تضمن فعالية القرارات.</p>
            </div>
            <div class="approach-card" data-aos="fade-up" data-aos-delay="200">
                <div class="approach-icon"><i class="fas fa-bullseye"></i></div>
                <h3>التركيز على النتائج</h3>
                <p>النجاح يُقاس بالنتائج لا بالجهد. أضع أهدافاً واضحة وقابلة للقياس، وأعمل بجد لتحقيقها.</p>
            </div>
            <div class="approach-card" data-aos="fade-up" data-aos-delay="300">
                <div class="approach-icon"><i class="fas fa-handshake"></i></div>
                <h3>شراكة حقيقية</h3>
                <p>لست مجرد مستشار تتعامل معه ثم تنساه. أصبح جزءاً من فريقك، مهتماً بنجاحك كأنه نجاحي الشخصي.</p>
            </div>
        </div>
    </div>
</section>

<!-- Expertise -->
<section class="expertise-section">
    <div class="container">
        <div class="expertise-grid">
            <div class="expertise-content" data-aos="fade-up">
                <h2>مجالات خبرتي</h2>
                <p>على مدار سنوات عملي، طورت خبرة متعمقة في عدة مجالات أساسية:</p>

                <div class="expertise-list">
                    <div class="expertise-item">
                        <div class="expertise-header">
                            <i class="fas fa-bullhorn"></i>
                            <h4>الاستراتيجيات التسويقية</h4>
                        </div>
                        <p>بناء استراتيجيات شاملة تربط بين أهداف العمل والتنفيذ التسويقي</p>
                    </div>
                    <div class="expertise-item">
                        <div class="expertise-header">
                            <i class="fas fa-rocket"></i>
                            <h4>التحول الرقمي</h4>
                        </div>
                        <p>مساعدة الشركات في رقمنة عملياتها وتبني التقنيات الحديثة</p>
                    </div>
                    <div class="expertise-item">
                        <div class="expertise-header">
                            <i class="fas fa-gem"></i>
                            <h4>بناء العلامات التجارية</h4>
                        </div>
                        <p>تطوير هويات تجارية قوية تميز عملائي في السوق</p>
                    </div>
                    <div class="expertise-item">
                        <div class="expertise-header">
                            <i class="fas fa-chart-line"></i>
                            <h4>تحسين معدلات التحويل</h4>
                        </div>
                        <p>تحويل الزوار إلى عملاء من خلال تحسين تجربة المستخدم</p>
                    </div>
                </div>
            </div>
            <div class="expertise-visual" data-aos="fade-up" data-aos-delay="100">
                <div class="quote-card">
                    <i class="fas fa-quote-right"></i>
                    <p>"النجاح في التسويق لا يتعلق بالميزانية الأكبر، بل بالفهم الأعمق لعملائك واحتياجاتهم."</p>
                    <span>- خالد سعد</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content" data-aos="fade-up">
            <h2>دعنا نعمل معاً</h2>
            <p>أتطلع دائماً للتعرف على مشاريع جديدة ورواد أعمال طموحين</p>
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-white btn-lg">
                احجز استشارة مجانية
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</section>

<style>
/* About Page Styles */
.about-hero {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: var(--space-16) 0;
}

.about-hero-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-12);
    align-items: center;
}

.section-badge {
    display: inline-block;
    padding: var(--space-2) var(--space-4);
    background: var(--primary-light);
    color: var(--primary);
    border-radius: var(--radius-full);
    font-size: var(--font-size-sm);
    font-weight: 600;
    margin-bottom: var(--space-4);
}

.about-hero-content h1 {
    font-size: var(--font-size-4xl);
    margin-bottom: var(--space-2);
}

.hero-subtitle {
    font-size: var(--font-size-xl);
    color: var(--primary);
    margin-bottom: var(--space-4);
}

.hero-desc {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
    line-height: 1.8;
    margin-bottom: var(--space-8);
}

.hero-stats {
    display: flex;
    gap: var(--space-8);
}

.hero-stats .stat {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: var(--font-size-2xl);
    font-weight: 700;
    color: var(--primary);
}

.stat-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.about-hero-visual {
    display: flex;
    justify-content: center;
}

.avatar-large {
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 6rem;
    font-weight: 700;
    color: white;
    box-shadow: 0 20px 60px rgba(37, 99, 235, 0.3);
}

/* Story Section */
.story-section {
    padding: var(--space-16) 0;
}

.story-content {
    max-width: 800px;
    margin: 0 auto;
}

.story-content h2 {
    text-align: center;
    margin-bottom: var(--space-8);
}

.story-text p {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
    line-height: 2;
    margin-bottom: var(--space-6);
}

.story-text p:last-child {
    margin-bottom: 0;
}

/* Approach Section */
.approach-section {
    padding: var(--space-16) 0;
    background: var(--bg-secondary);
}

.section-intro {
    text-align: center;
    max-width: 600px;
    margin: 0 auto var(--space-12);
}

.section-intro h2 {
    margin-bottom: var(--space-4);
}

.section-intro p {
    color: var(--text-secondary);
}

.approach-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-6);
}

.approach-card {
    background: var(--bg-primary);
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    border: 1px solid var(--border-color);
}

.approach-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-light);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: var(--space-4);
}

.approach-icon i {
    font-size: 1.25rem;
    color: var(--primary);
}

.approach-card h3 {
    margin-bottom: var(--space-3);
    font-size: var(--font-size-lg);
}

.approach-card p {
    color: var(--text-secondary);
    line-height: 1.7;
    margin: 0;
}

/* Expertise Section */
.expertise-section {
    padding: var(--space-16) 0;
}

.expertise-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-12);
    align-items: start;
}

.expertise-content h2 {
    margin-bottom: var(--space-4);
}

.expertise-content > p {
    color: var(--text-secondary);
    margin-bottom: var(--space-8);
}

.expertise-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}

.expertise-item {
    padding: var(--space-4);
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
}

.expertise-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-2);
}

.expertise-header i {
    color: var(--primary);
}

.expertise-header h4 {
    margin: 0;
    font-size: var(--font-size-base);
}

.expertise-item p {
    margin: 0;
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
    padding-right: calc(var(--space-3) + 1rem);
}

/* Quote Card */
.quote-card {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    padding: var(--space-8);
    border-radius: var(--radius-xl);
    color: white;
    position: sticky;
    top: var(--space-8);
}

.quote-card i {
    font-size: 2rem;
    opacity: 0.5;
    margin-bottom: var(--space-4);
}

.quote-card p {
    font-size: var(--font-size-lg);
    line-height: 1.8;
    margin-bottom: var(--space-4);
}

.quote-card span {
    opacity: 0.8;
}

/* Responsive */
@media (max-width: 992px) {
    .about-hero-grid,
    .expertise-grid {
        grid-template-columns: 1fr;
    }

    .about-hero-visual {
        order: -1;
    }

    .avatar-large {
        width: 180px;
        height: 180px;
        font-size: 4rem;
    }
}

@media (max-width: 768px) {
    .approach-grid {
        grid-template-columns: 1fr;
    }

    .hero-stats {
        justify-content: center;
    }
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * صفحة الخدمات
 * خالد سعد - خبير التسويق والتحول الرقمي
 */

require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'خدماتي - ' . SITE_NAME;
$pageDescription = 'أقدم خدمات استشارية متخصصة في التسويق الرقمي، التحول الرقمي، وبناء الهوية التجارية لمساعدتك في تحقيق النمو.';

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Services Hero -->
<section class="services-hero">
    <div class="container">
        <div class="services-hero-content" data-aos="fade-up">
            <span class="section-badge">خدماتي</span>
            <h1>كيف أستطيع مساعدتك؟</h1>
            <p>أقدم مجموعة من الخدمات المتخصصة المبنية على خبرة تمتد لأكثر من 10 سنوات في مجال التسويق والتحول الرقمي</p>
        </div>
    </div>
</section>

<!-- Main Services -->
<section class="main-services">
    <div class="container">

        <!-- Service 1 -->
        <div class="service-detail" id="consulting" data-aos="fade-up">
            <div class="service-detail-content">
                <div class="service-number-badge">01</div>
                <h2>الاستشارات التسويقية</h2>
                <p class="service-desc">أصمم لك استراتيجية تسويقية مخصصة مبنية على تحليل معمق لعملك وسوقك ومنافسيك، مع خطة تنفيذية واضحة تحقق نتائج ملموسة.</p>

                <div class="service-includes">
                    <h4>ماذا تتضمن الخدمة؟</h4>
                    <ul>
                        <li><i class="fas fa-check"></i> تحليل شامل للوضع الحالي</li>
                        <li><i class="fas fa-check"></i> دراسة السوق والمنافسين</li>
                        <li><i class="fas fa-check"></i> تحديد الجمهور المستهدف</li>
                        <li><i class="fas fa-check"></i> استراتيجية تسويقية متكاملة</li>
                        <li><i class="fas fa-check"></i> خطة تنفيذية مفصلة</li>
                        <li><i class="fas fa-check"></i> مؤشرات قياس الأداء</li>
                    </ul>
                </div>

                <div class="service-result">
                    <span class="result-label">النتيجة المتوقعة</span>
                    <p>استراتيجية واضحة مع خارطة طريق للتنفيذ تساعدك في تحقيق أهدافك التسويقية</p>
                </div>

                <a href="<?= url('pages/contact.php?service=consulting') ?>" class="btn btn-primary">
                    احجز استشارة <i class="fas fa-arrow-left"></i>
                </a>
            </div>
            <div class="service-detail-visual">
                <div class="service-icon-large">
                    <i class="fas fa-bullseye"></i>
                </div>
            </div>
        </div>

        <!-- Service 2 -->
        <div class="service-detail reverse" id="digital" data-aos="fade-up">
            <div class="service-detail-content">
                <div class="service-number-badge featured">02</div>
                <span class="popular-badge">الأكثر طلباً</span>
                <h2>التحول الرقمي</h2>
                <p class="service-desc">أساعدك في رقمنة عملياتك وتبني التقنيات الحديثة لزيادة الكفاءة وتحسين تجربة عملائك وتقليل التكاليف التشغيلية.</p>

                <div class="service-includes">
                    <h4>ماذا تتضمن الخدمة؟</h4>
                    <ul>
                        <li><i class="fas fa-check"></i> تقييم الجاهزية الرقمية</li>
                        <li><i class="fas fa-check"></i> خارطة طريق التحول</li>
                        <li><i class="fas fa-check"></i> اختيار الأدوات المناسبة</li>
                        <li><i class="fas fa-check"></i> أتمتة العمليات</li>
                        <li><i class="fas fa-check"></i> تدريب الفريق</li>
                        <li><i class="fas fa-check"></i> متابعة التنفيذ</li>
                    </ul>
                </div>

                <div class="service-result">
                    <span class="result-label">النتيجة المتوقعة</span>
                    <p>عمليات أكثر كفاءة، تكاليف أقل، وتجربة عملاء محسّنة</p>
                </div>

                <a href="<?= url('pages/contact.php?service=digital') ?>" class="btn btn-primary">
                    احجز استشارة <i class="fas fa-arrow-left"></i>
                </a>
            </div>
            <div class="service-detail-visual">
                <div class="service-icon-large">
                    <i class="fas fa-rocket"></i>
                </div>
            </div>
        </div>

        <!-- Service 3 -->
        <div class="service-detail" id="branding" data-aos="fade-up">
            <div class="service-detail-content">
                <div class="service-number-badge">03</div>
                <h2>بناء الهوية التجارية</h2>
                <p class="service-desc">أساعدك في بناء هوية تجارية قوية ومميزة تعكس قيمك وتترك انطباعاً لا يُنسى لدى عملائك وتميزك عن المنافسين.</p>

                <div class="service-includes">
                    <h4>ماذا تتضمن الخدمة؟</h4>
                    <ul>
                        <li><i class="fas fa-check"></i> تحديد شخصية العلامة</li>
                        <li><i class="fas fa-check"></i> استراتيجية الرسائل</li>
                        <li><i class="fas fa-check"></i> توجيهات الهوية البصرية</li>
                        <li><i class="fas fa-check"></i> صوت العلامة التجارية</li>
                        <li><i class="fas fa-check"></i> دليل الاستخدام</li>
                    </ul>
                </div>

                <div class="service-result">
                    <span class="result-label">النتيجة المتوقعة</span>
                    <p>هوية تجارية متكاملة تميزك في السوق وتبني الثقة مع عملائك</p>
                </div>

                <a href="<?= url('pages/contact.php?service=branding') ?>" class="btn btn-primary">
                    احجز استشارة <i class="fas fa-arrow-left"></i>
                </a>
            </div>
            <div class="service-detail-visual">
                <div class="service-icon-large">
                    <i class="fas fa-gem"></i>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Why Work With Me -->
<section class="why-me-section">
    <div class="container">
        <div class="section-intro" data-aos="fade-up">
            <h2>لماذا تختار العمل معي؟</h2>
        </div>

        <div class="why-me-grid">
            <div class="why-me-item" data-aos="fade-up">
                <div class="why-icon"><i class="fas fa-user-tie"></i></div>
                <h3>خبرة شخصية</h3>
                <p>تتعامل معي مباشرة، وليس مع فريق متغير. أفهم مشروعك بعمق وأعطيه اهتمامي الكامل.</p>
            </div>
            <div class="why-me-item" data-aos="fade-up" data-aos-delay="100">
                <div class="why-icon"><i class="fas fa-chart-line"></i></div>
                <h3>نتائج مُثبتة</h3>
                <p>سجل حافل من النجاحات مع عملاء في مختلف المجالات. النتائج تتحدث عن نفسها.</p>
            </div>
            <div class="why-me-item" data-aos="fade-up" data-aos-delay="200">
                <div class="why-icon"><i class="fas fa-handshake"></i></div>
                <h3>شراكة حقيقية</h3>
                <p>لست مجرد مستشار، بل شريك مهتم بنجاحك. نجاحك هو مقياس نجاحي.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content" data-aos="fade-up">
            <h2>مستعد للبدء؟</h2>
            <p>احجز استشارة مجانية وناقش معي كيف يمكنني مساعدتك</p>
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-white btn-lg">
                احجز استشارتك المجانية
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</section>

<style>
/* Services Page Styles */
.services-hero {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: var(--space-16) 0 var(--space-12);
}

.services-hero-content {
    text-align: center;
    max-width: 700px;
    margin: 0 auto;
}

.services-hero-content h1 {
    font-size: var(--font-size-4xl);
    margin: var(--space-4) 0;
}

.services-hero-content p {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
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

.main-services {
    padding: var(--space-16) 0;
}

.service-detail {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-12);
    align-items: center;
    padding: var(--space-12) 0;
    border-bottom: 1px solid var(--border-color);
}

.service-detail:last-child {
    border-bottom: none;
}

.service-detail.reverse {
    direction: ltr;
}

.service-detail.reverse .service-detail-content {
    direction: rtl;
}

.service-number-badge {
    display: inline-block;
    width: 50px;
    height: 50px;
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: var(--space-4);
}

.service-number-badge.featured {
    background: var(--primary);
    color: white;
}

.popular-badge {
    display: inline-block;
    padding: var(--space-1) var(--space-3);
    background: var(--warning);
    color: white;
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 600;
    margin-bottom: var(--space-3);
}

.service-detail h2 {
    font-size: var(--font-size-2xl);
    margin-bottom: var(--space-4);
}

.service-desc {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
    line-height: 1.8;
    margin-bottom: var(--space-6);
}

.service-includes {
    margin-bottom: var(--space-6);
}

.service-includes h4 {
    font-size: var(--font-size-base);
    margin-bottom: var(--space-4);
    color: var(--text-secondary);
}

.service-includes ul {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-3);
    list-style: none;
}

.service-includes li {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.service-includes li i {
    color: var(--success);
    font-size: var(--font-size-sm);
}

.service-result {
    background: var(--bg-secondary);
    padding: var(--space-4);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-6);
}

.service-result .result-label {
    display: block;
    font-size: var(--font-size-xs);
    color: var(--primary);
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: var(--space-2);
}

.service-result p {
    margin: 0;
    color: var(--text-secondary);
}

.service-detail-visual {
    display: flex;
    align-items: center;
    justify-content: center;
}

.service-icon-large {
    width: 200px;
    height: 200px;
    background: linear-gradient(135deg, var(--primary-light) 0%, var(--bg-secondary) 100%);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
}

.service-icon-large i {
    font-size: 5rem;
    color: var(--primary);
}

/* Why Me Section */
.why-me-section {
    background: var(--bg-secondary);
    padding: var(--space-16) 0;
}

.why-me-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-8);
    margin-top: var(--space-10);
}

.why-me-item {
    text-align: center;
    padding: var(--space-6);
}

.why-icon {
    width: 70px;
    height: 70px;
    background: var(--primary);
    border-radius: var(--radius-xl);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto var(--space-4);
}

.why-icon i {
    font-size: 1.75rem;
    color: white;
}

.why-me-item h3 {
    margin-bottom: var(--space-3);
}

.why-me-item p {
    color: var(--text-secondary);
    line-height: 1.7;
}

/* Responsive */
@media (max-width: 768px) {
    .service-detail {
        grid-template-columns: 1fr;
        gap: var(--space-6);
    }

    .service-detail.reverse {
        direction: rtl;
    }

    .service-detail-visual {
        order: -1;
    }

    .service-icon-large {
        width: 150px;
        height: 150px;
    }

    .service-icon-large i {
        font-size: 3.5rem;
    }

    .service-includes ul {
        grid-template-columns: 1fr;
    }

    .why-me-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

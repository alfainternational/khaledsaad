<?php
/**
 * الصفحة الرئيسية
 * خالد سعد - خبير التسويق والتحول الرقمي
 */

require_once __DIR__ . '/includes/init.php';

$pageTitle = SITE_NAME . ' - ' . SITE_TAGLINE;
$pageDescription = 'خالد سعد، خبير التسويق والتحول الرقمي. أساعدك في بناء استراتيجيات تسويقية فعّالة وتحقيق نمو مستدام لأعمالك.';

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section - Clean & Personal -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content" data-aos="fade-up">
            <div class="hero-intro">
                <span class="greeting">مرحباً، أنا</span>
                <h1 class="hero-name">خالد سعد</h1>
                <p class="hero-title">خبير التسويق والتحول الرقمي</p>
            </div>

            <p class="hero-description">
                أساعد رواد الأعمال والشركات في <strong>بناء استراتيجيات تسويقية فعّالة</strong>
                وتحقيق <strong>التحول الرقمي</strong> الذي يحقق نتائج ملموسة.
            </p>

            <div class="hero-cta">
                <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg">
                    احجز استشارة مجانية
                    <i class="fas fa-arrow-left"></i>
                </a>
                <a href="#services" class="btn btn-ghost btn-lg">
                    تعرف على خدماتي
                </a>
            </div>

            <div class="hero-social-proof">
                <div class="proof-avatars">
                    <span class="avatar">م</span>
                    <span class="avatar">س</span>
                    <span class="avatar">أ</span>
                    <span class="avatar">+</span>
                </div>
                <p>أكثر من <strong>150+ عميل</strong> حققوا نتائج استثنائية</p>
            </div>
        </div>
    </div>

    <div class="hero-gradient"></div>
</section>

<!-- Value Proposition -->
<section class="value-section" id="value">
    <div class="container">
        <div class="value-grid">
            <div class="value-item" data-aos="fade-up">
                <div class="value-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3>استراتيجيات مُجرّبة</h3>
                <p>خبرة +10 سنوات في بناء استراتيجيات تسويقية حققت نتائج ملموسة</p>
            </div>
            <div class="value-item" data-aos="fade-up" data-aos-delay="100">
                <div class="value-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>نتائج قابلة للقياس</h3>
                <p>تركيز على ROI ومؤشرات أداء واضحة لكل مشروع</p>
            </div>
            <div class="value-item" data-aos="fade-up" data-aos-delay="200">
                <div class="value-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3>شراكة حقيقية</h3>
                <p>أعمل معك كشريك وليس مجرد مستشار، نجاحك هو نجاحي</p>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="services-section" id="services">
    <div class="container">
        <div class="section-intro" data-aos="fade-up">
            <h2>كيف أستطيع مساعدتك؟</h2>
            <p>خدمات متخصصة مصممة لتحقيق أهدافك</p>
        </div>

        <div class="services-grid">
            <div class="service-card" data-aos="fade-up">
                <span class="service-number">01</span>
                <h3>الاستشارات التسويقية</h3>
                <p>تحليل شامل لوضعك الحالي ووضع استراتيجية تسويقية مخصصة تناسب أهدافك وميزانيتك.</p>
                <ul class="service-features">
                    <li>تحليل السوق والمنافسين</li>
                    <li>استراتيجية التسويق الرقمي</li>
                    <li>خطة تنفيذية واضحة</li>
                </ul>
                <a href="<?= url('pages/contact.php?service=consulting') ?>" class="service-link">
                    ابدأ الآن <i class="fas fa-arrow-left"></i>
                </a>
            </div>

            <div class="service-card featured" data-aos="fade-up" data-aos-delay="100">
                <span class="service-badge">الأكثر طلباً</span>
                <span class="service-number">02</span>
                <h3>التحول الرقمي</h3>
                <p>أساعدك في رقمنة عملياتك وتبني التقنيات الحديثة لزيادة الكفاءة والإنتاجية.</p>
                <ul class="service-features">
                    <li>تقييم الجاهزية الرقمية</li>
                    <li>أتمتة العمليات</li>
                    <li>تدريب الفريق</li>
                </ul>
                <a href="<?= url('pages/contact.php?service=digital') ?>" class="service-link">
                    ابدأ الآن <i class="fas fa-arrow-left"></i>
                </a>
            </div>

            <div class="service-card" data-aos="fade-up" data-aos-delay="200">
                <span class="service-number">03</span>
                <h3>بناء الهوية التجارية</h3>
                <p>تصميم هوية تجارية مميزة تعكس قيمك وتترك انطباعاً قوياً لدى عملائك.</p>
                <ul class="service-features">
                    <li>استراتيجية العلامة التجارية</li>
                    <li>الهوية البصرية</li>
                    <li>دليل العلامة التجارية</li>
                </ul>
                <a href="<?= url('pages/contact.php?service=branding') ?>" class="service-link">
                    ابدأ الآن <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Results Section -->
<section class="results-section">
    <div class="container">
        <div class="results-content" data-aos="fade-up">
            <h2>نتائج حقيقية، قصص نجاح ملهمة</h2>
            <p class="results-subtitle">بعض النتائج التي حققتها مع عملائي</p>

            <div class="results-grid">
                <div class="result-card">
                    <span class="result-number">340%</span>
                    <span class="result-label">زيادة في المبيعات</span>
                    <p>شركة تقنية ناشئة خلال 6 أشهر</p>
                </div>
                <div class="result-card">
                    <span class="result-number">60%</span>
                    <span class="result-label">تخفيض التكاليف</span>
                    <p>مؤسسة تجارية بعد التحول الرقمي</p>
                </div>
                <div class="result-card">
                    <span class="result-number">5X</span>
                    <span class="result-label">عائد على الاستثمار</span>
                    <p>متوسط ROI لعملائي</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonial Section -->
<section class="testimonial-section">
    <div class="container">
        <div class="testimonial-card" data-aos="fade-up">
            <div class="quote-icon">
                <i class="fas fa-quote-right"></i>
            </div>
            <blockquote>
                "العمل مع خالد كان نقطة تحول حقيقية لمشروعي. لم يكن مجرد مستشار، بل شريك يهتم بنجاحي. الاستراتيجية التي وضعها ساعدتنا في مضاعفة مبيعاتنا خلال فترة قصيرة."
            </blockquote>
            <div class="testimonial-author">
                <div class="author-avatar">م</div>
                <div class="author-info">
                    <strong>محمد العمري</strong>
                    <span>مؤسس شركة تقنية</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Process Section -->
<section class="process-section" id="process">
    <div class="container">
        <div class="section-intro" data-aos="fade-up">
            <h2>كيف نعمل معاً؟</h2>
            <p>عملية بسيطة وواضحة من البداية للنتائج</p>
        </div>

        <div class="process-steps">
            <div class="process-step" data-aos="fade-up">
                <div class="step-number">1</div>
                <h3>استشارة مجانية</h3>
                <p>نتحدث عن أهدافك وتحدياتك لفهم وضعك الحالي</p>
            </div>
            <div class="process-connector"></div>
            <div class="process-step" data-aos="fade-up" data-aos-delay="100">
                <div class="step-number">2</div>
                <h3>خطة مخصصة</h3>
                <p>أضع لك استراتيجية واضحة مع خطوات تنفيذية</p>
            </div>
            <div class="process-connector"></div>
            <div class="process-step" data-aos="fade-up" data-aos-delay="200">
                <div class="step-number">3</div>
                <h3>تنفيذ ومتابعة</h3>
                <p>نعمل معاً على التنفيذ مع متابعة مستمرة للنتائج</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content" data-aos="fade-up">
            <h2>مستعد لتحقيق النمو؟</h2>
            <p>احجز استشارة مجانية وناقش معي كيف يمكنني مساعدتك</p>
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-white btn-lg">
                احجز استشارتك المجانية
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</section>

<!-- Diagnostic Tool CTA -->
<section class="diagnostic-cta">
    <div class="container">
        <div class="diagnostic-card" data-aos="fade-up">
            <div class="diagnostic-content">
                <span class="diagnostic-badge"><i class="fas fa-gift"></i> مجاناً</span>
                <h3>اكتشف مستوى جاهزيتك الرقمية</h3>
                <p>أداة تشخيص مجانية تساعدك في معرفة نقاط القوة والضعف في استراتيجيتك الرقمية</p>
                <a href="<?= url('pages/diagnostic.php') ?>" class="btn btn-primary">
                    ابدأ التشخيص المجاني
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
            <div class="diagnostic-visual">
                <div class="score-preview">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="8"/>
                        <circle cx="50" cy="50" r="45" fill="none" stroke="var(--primary)" stroke-width="8" stroke-dasharray="200 283" stroke-linecap="round" style="transform: rotate(-90deg); transform-origin: center;"/>
                    </svg>
                    <span class="score-text">؟</span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

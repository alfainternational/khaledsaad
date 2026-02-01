<?php
/**
 * الصفحة الرئيسية
 * موقع خالد سعد للاستشارات
 */

require_once __DIR__ . '/includes/init.php';

// إعدادات SEO للصفحة
$pageTitle = SITE_NAME . ' - ' . SITE_TAGLINE;
$pageDescription = 'خالد سعد للاستشارات - شريكك في التحول الرقمي. نقدم استشارات تسويقية متخصصة في السعودية، خدمات التحول الرقمي، وبناء الهوية التجارية لتحقيق نمو مستدام لأعمالك.';
$pageKeywords = 'استشارات تسويقية, التحول الرقمي, استشارات في السعودية, تسويق رقمي, خالد سعد, استشارات أعمال, بناء الهوية التجارية';

// تتبع زيارة الصفحة
trackPageView($_SERVER['REQUEST_URI'], $pageTitle);

// جلب الخدمات
try {
    $services = db()->fetchAll("SELECT * FROM services WHERE status = 'active' ORDER BY sort_order ASC LIMIT 4");
} catch (Exception $e) {
    $services = [];
}

// جلب قصص النجاح
try {
    $testimonials = db()->fetchAll("SELECT * FROM success_stories WHERE status = 'published' AND is_featured = 1 ORDER BY sort_order ASC LIMIT 3");
} catch (Exception $e) {
    $testimonials = [];
}

// جلب المقالات الأخيرة
try {
    $recentPosts = db()->fetchAll("
        SELECT p.*, c.name as category_name, u.full_name as author_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.status = 'published'
        ORDER BY p.published_at DESC
        LIMIT 3
    ");
} catch (Exception $e) {
    $recentPosts = [];
}

// جلب باقات الأسعار
try {
    $pricingPlans = db()->fetchAll("SELECT * FROM pricing_plans WHERE status = 'active' ORDER BY sort_order ASC LIMIT 3");
} catch (Exception $e) {
    $pricingPlans = [];
}

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-content" data-aos="fade-up">
                <span class="hero-badge">
                    <i class="fas fa-star"></i>
                    شريكك الموثوق في النجاح
                </span>
                <h1 class="hero-title">
                    نحوّل أفكارك إلى
                    <span class="highlight">نجاحات رقمية</span>
                    مستدامة
                </h1>
                <p class="hero-description">
                    نقدم استشارات تسويقية متخصصة وحلول التحول الرقمي للشركات في المملكة العربية السعودية.
                    اكتشف كيف يمكننا مساعدتك في تحقيق نمو مستدام وتعزيز تواجدك الرقمي.
                </p>
                <div class="hero-actions">
                    <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-check"></i>
                        احجز استشارة مجانية
                    </a>
                    <a href="<?= url('pages/diagnostic.php') ?>" class="btn btn-outline btn-lg">
                        <i class="fas fa-clipboard-check"></i>
                        أداة التشخيص المجانية
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number" data-counter="150" data-suffix="+">0+</span>
                        <span class="stat-label">عميل راضٍ</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" data-counter="10" data-suffix="+">0+</span>
                        <span class="stat-label">سنوات خبرة</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" data-counter="95" data-suffix="%">0%</span>
                        <span class="stat-label">نسبة النجاح</span>
                    </div>
                </div>
            </div>
            <div class="hero-image" data-aos="fade-up" data-aos-delay="200">
                <img src="<?= asset('images/hero-image.svg') ?>" alt="استشارات التسويق والتحول الرقمي" loading="lazy">
                <div class="floating-card card-1">
                    <i class="fas fa-chart-line" style="color: var(--success); font-size: 1.5rem;"></i>
                    <div>
                        <strong>+340%</strong>
                        <small style="display: block; color: var(--text-muted);">زيادة في المبيعات</small>
                    </div>
                </div>
                <div class="floating-card card-2">
                    <i class="fas fa-users" style="color: var(--primary); font-size: 1.5rem;"></i>
                    <div>
                        <strong>+150</strong>
                        <small style="display: block; color: var(--text-muted);">عميل جديد شهرياً</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="services-section" id="services">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">خدماتنا</span>
            <h2>حلول متكاملة لنمو أعمالك</h2>
            <p>نقدم مجموعة شاملة من الخدمات الاستشارية المصممة خصيصاً لتلبية احتياجات عملك وتحقيق أهدافك</p>
        </div>

        <div class="services-grid">
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $index => $service): ?>
                <div class="service-card" data-aos="fade-up" data-aos-delay="<?= ($index + 1) * 100 ?>">
                    <div class="service-icon">
                        <i class="fas <?= e($service['icon']) ?>"></i>
                    </div>
                    <h3><?= e($service['name']) ?></h3>
                    <p><?= e($service['short_description']) ?></p>
                    <a href="<?= url('pages/services.php#' . e($service['slug'])) ?>" class="btn btn-ghost">
                        اكتشف المزيد
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default services -->
                <div class="service-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="service-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>الاستشارات التسويقية</h3>
                    <p>استراتيجيات تسويقية مخصصة مبنية على تحليل معمق للسوق والمنافسين لتحقيق نمو مستدام</p>
                    <a href="<?= url('pages/services.php#consulting') ?>" class="btn btn-ghost">
                        اكتشف المزيد
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="service-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h3>التحول الرقمي</h3>
                    <p>رقمنة العمليات وتحسين الكفاءة التشغيلية باستخدام أحدث التقنيات والأدوات الرقمية</p>
                    <a href="<?= url('pages/services.php#digital') ?>" class="btn btn-ghost">
                        اكتشف المزيد
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="service-icon">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h3>بناء الهوية التجارية</h3>
                    <p>تصميم هوية تجارية مميزة ومؤثرة تعكس قيم شركتك وتترك انطباعاً لا يُنسى</p>
                    <a href="<?= url('pages/services.php#branding') ?>" class="btn btn-ghost">
                        اكتشف المزيد
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                <div class="service-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="service-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>التدريب والتطوير</h3>
                    <p>برامج تدريبية متخصصة لتطوير مهارات فريقك وتعزيز قدراتهم التسويقية والرقمية</p>
                    <a href="<?= url('pages/services.php#training') ?>" class="btn btn-ghost">
                        اكتشف المزيد
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Methodology Section -->
<section class="methodology-section" id="methodology">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">منهجيتنا</span>
            <h2>ثلاث خطوات نحو النجاح</h2>
            <p>نتبع منهجية مثبتة تضمن تحقيق نتائج ملموسة وقابلة للقياس لعملائنا</p>
        </div>

        <div class="methodology-grid">
            <div class="methodology-step" data-aos="fade-up" data-aos-delay="100">
                <div class="step-number">1</div>
                <h3>التشخيص والتحليل</h3>
                <p>نبدأ بفهم عميق لعملك، أهدافك، والتحديات التي تواجهها من خلال تحليل شامل للسوق والمنافسين</p>
            </div>
            <div class="methodology-step" data-aos="fade-up" data-aos-delay="200">
                <div class="step-number">2</div>
                <h3>التخطيط والاستراتيجية</h3>
                <p>نصمم استراتيجية مخصصة تتناسب مع طبيعة عملك وميزانيتك مع خطة تنفيذية واضحة المعالم</p>
            </div>
            <div class="methodology-step" data-aos="fade-up" data-aos-delay="300">
                <div class="step-number">3</div>
                <h3>التنفيذ والمتابعة</h3>
                <p>نعمل معك جنباً إلى جنب لتنفيذ الاستراتيجية مع مراقبة مستمرة وتحسين الأداء</p>
            </div>
        </div>
    </div>
</section>

<!-- Success Stories Section -->
<section class="testimonials-section" id="testimonials">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">قصص النجاح</span>
            <h2>عملاؤنا يتحدثون</h2>
            <p>اكتشف كيف ساعدنا عملاءنا في تحقيق نتائج استثنائية وتحويل تحدياتهم إلى فرص نمو</p>
        </div>

        <div class="testimonials-grid">
            <?php if (!empty($testimonials)): ?>
                <?php foreach ($testimonials as $index => $story): ?>
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="<?= ($index + 1) * 100 ?>">
                    <div class="testimonial-content">
                        <p>"<?= e($story['testimonial']) ?>"</p>
                    </div>
                    <div class="testimonial-author">
                        <?php if ($story['client_image']): ?>
                        <img src="<?= url('uploads/' . e($story['client_image'])) ?>" alt="<?= e($story['client_name']) ?>" class="author-avatar">
                        <?php else: ?>
                        <div class="author-avatar" style="background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?= mb_substr($story['client_name'], 0, 1) ?>
                        </div>
                        <?php endif; ?>
                        <div class="author-info">
                            <h4><?= e($story['client_name']) ?></h4>
                            <p><?= e($story['client_position']) ?> - <?= e($story['client_company']) ?></p>
                        </div>
                    </div>
                    <?php if ($story['metrics']): ?>
                    <?php $metrics = json_decode($story['metrics'], true); ?>
                    <div class="testimonial-metrics">
                        <?php foreach (array_slice($metrics, 0, 3) as $metric): ?>
                        <div class="metric">
                            <span class="metric-value"><?= e($metric['value']) ?></span>
                            <span class="metric-label"><?= e($metric['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default testimonials -->
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-content">
                        <p>"بفضل خبرة فريق خالد سعد، تمكنا من زيادة مبيعاتنا بنسبة 340% خلال 6 أشهر فقط. الاستراتيجية التي وضعوها كانت واضحة وفعالة."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar" style="background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">م</div>
                        <div class="author-info">
                            <h4>محمد العمري</h4>
                            <p>المدير التنفيذي - شركة تقنية الابتكار</p>
                        </div>
                    </div>
                    <div class="testimonial-metrics">
                        <div class="metric">
                            <span class="metric-value">+340%</span>
                            <span class="metric-label">زيادة المبيعات</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">6 أشهر</span>
                            <span class="metric-label">الفترة الزمنية</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">5X</span>
                            <span class="metric-label">العائد على الاستثمار</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-content">
                        <p>"التحول الرقمي الذي ساعدنا فريق خالد سعد في تنفيذه غيّر طريقة عملنا بالكامل. أصبحنا أكثر كفاءة وقدرة على المنافسة."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar" style="background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">س</div>
                        <div class="author-info">
                            <h4>سارة الخالدي</h4>
                            <p>مديرة التسويق - مجموعة الرياض التجارية</p>
                        </div>
                    </div>
                    <div class="testimonial-metrics">
                        <div class="metric">
                            <span class="metric-value">-60%</span>
                            <span class="metric-label">تخفيض التكاليف</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">+200%</span>
                            <span class="metric-label">زيادة الإنتاجية</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">100%</span>
                            <span class="metric-label">رقمنة العمليات</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-content">
                        <p>"الهوية التجارية الجديدة التي صممها الفريق منحتنا مظهراً احترافياً ومتميزاً في السوق. أوصي بشدة بالتعامل معهم."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar" style="background: var(--success); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">أ</div>
                        <div class="author-info">
                            <h4>أحمد الغامدي</h4>
                            <p>مؤسس - مطاعم الذواقة</p>
                        </div>
                    </div>
                    <div class="testimonial-metrics">
                        <div class="metric">
                            <span class="metric-value">+150%</span>
                            <span class="metric-label">زيادة الوعي</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">3</span>
                            <span class="metric-label">فروع جديدة</span>
                        </div>
                        <div class="metric">
                            <span class="metric-value">+80%</span>
                            <span class="metric-label">زيادة العملاء</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-8" data-aos="fade-up">
            <a href="<?= url('pages/success-stories.php') ?>" class="btn btn-outline">
                عرض جميع قصص النجاح
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section class="pricing-section" id="pricing">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">باقات الأسعار</span>
            <h2>خطط مرنة تناسب احتياجاتك</h2>
            <p>اختر الباقة المناسبة لحجم عملك وميزانيتك مع إمكانية التخصيص حسب متطلباتك</p>
        </div>

        <div class="pricing-grid">
            <?php if (!empty($pricingPlans)): ?>
                <?php foreach ($pricingPlans as $index => $plan): ?>
                <?php $features = json_decode($plan['features'], true) ?: []; ?>
                <div class="pricing-card <?= $plan['is_popular'] ? 'popular' : '' ?>" data-aos="fade-up" data-aos-delay="<?= ($index + 1) * 100 ?>">
                    <div class="pricing-header">
                        <h3><?= e($plan['name']) ?></h3>
                        <p><?= e($plan['description']) ?></p>
                        <div class="price">
                            <span class="price-amount"><?= formatNumber($plan['price']) ?></span>
                            <span class="price-currency">ر.س</span>
                            <span class="price-period">/ شهرياً</span>
                        </div>
                    </div>
                    <ul class="pricing-features">
                        <?php foreach ($features as $feature): ?>
                        <li>
                            <i class="fas fa-check"></i>
                            <span><?= e($feature) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= url('pages/contact.php?plan=' . e($plan['slug'])) ?>" class="btn <?= $plan['is_popular'] ? 'btn-primary' : 'btn-outline' ?> w-100">
                        ابدأ الآن
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default pricing -->
                <div class="pricing-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="pricing-header">
                        <h3>باقة الانطلاق</h3>
                        <p>مثالية للشركات الناشئة</p>
                        <div class="price">
                            <span class="price-amount">5,000</span>
                            <span class="price-currency">ر.س</span>
                            <span class="price-period">/ شهرياً</span>
                        </div>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i><span>تحليل السوق الأولي</span></li>
                        <li><i class="fas fa-check"></i><span>استراتيجية تسويق أساسية</span></li>
                        <li><i class="fas fa-check"></i><span>تقرير شهري</span></li>
                        <li><i class="fas fa-check"></i><span>دعم عبر البريد</span></li>
                        <li><i class="fas fa-check"></i><span>جلسة استشارية واحدة</span></li>
                    </ul>
                    <a href="<?= url('pages/contact.php?plan=starter') ?>" class="btn btn-outline w-100">ابدأ الآن</a>
                </div>
                <div class="pricing-card popular" data-aos="fade-up" data-aos-delay="200">
                    <div class="pricing-header">
                        <h3>باقة النمو</h3>
                        <p>للشركات في مرحلة التوسع</p>
                        <div class="price">
                            <span class="price-amount">10,000</span>
                            <span class="price-currency">ر.س</span>
                            <span class="price-period">/ شهرياً</span>
                        </div>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check"></i><span>تحليل شامل للسوق</span></li>
                        <li><i class="fas fa-check"></i><span>استراتيجية تسويق متكاملة</span></li>
                        <li><i class="fas fa-check"></i><span>تقارير أسبوعية</span></li>
                        <li><i class="fas fa-check"></i><span>دعم على مدار الساعة</span></li>
                        <li><i class="fas fa-check"></i><span>4 جلسات استشارية شهرياً</span></li>
                        <li><i class="fas fa-check"></i><span>إدارة الحملات الإعلانية</span></li>
                    </ul>
                    <a href="<?= url('pages/contact.php?plan=growth') ?>" class="btn btn-primary w-100">ابدأ الآن</a>
                </div>
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
                        <li><i class="fas fa-check"></i><span>تقارير يومية</span></li>
                        <li><i class="fas fa-check"></i><span>فريق دعم مخصص</span></li>
                        <li><i class="fas fa-check"></i><span>جلسات استشارية غير محدودة</span></li>
                        <li><i class="fas fa-check"></i><span>إدارة كاملة للحملات</span></li>
                        <li><i class="fas fa-check"></i><span>تدريب الفريق</span></li>
                    </ul>
                    <a href="<?= url('pages/contact.php?plan=enterprise') ?>" class="btn btn-outline w-100">تواصل معنا</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Blog Preview Section -->
<section class="blog-section">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <span class="badge">المدونة</span>
            <h2>أحدث المقالات والنصائح</h2>
            <p>تابع آخر الأخبار والمقالات في مجال التسويق الرقمي والتحول الرقمي</p>
        </div>

        <div class="services-grid">
            <?php if (!empty($recentPosts)): ?>
                <?php foreach ($recentPosts as $index => $post): ?>
                <article class="card" data-aos="fade-up" data-aos-delay="<?= ($index + 1) * 100 ?>">
                    <?php if ($post['featured_image']): ?>
                    <div class="card-image">
                        <img src="<?= url('uploads/' . e($post['featured_image'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
                    </div>
                    <?php else: ?>
                    <div class="card-image" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);"></div>
                    <?php endif; ?>
                    <div class="card-body">
                        <?php if ($post['category_name']): ?>
                        <span class="badge"><?= e($post['category_name']) ?></span>
                        <?php endif; ?>
                        <h3 class="card-title">
                            <a href="<?= url('pages/blog-post.php?slug=' . e($post['slug'])) ?>"><?= e($post['title']) ?></a>
                        </h3>
                        <p class="card-text"><?= e(truncate($post['excerpt'] ?: strip_tags($post['content']), 120)) ?></p>
                        <div class="card-meta">
                            <span><i class="far fa-calendar"></i> <?= formatDate($post['published_at'], 'short') ?></span>
                            <span><i class="far fa-clock"></i> <?= $post['reading_time'] ?: readingTime($post['content']) ?> دقائق</span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default articles -->
                <article class="card" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-image" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chart-pie" style="font-size: 3rem; color: white;"></i>
                    </div>
                    <div class="card-body">
                        <span class="badge">التسويق الرقمي</span>
                        <h3 class="card-title"><a href="#">10 استراتيجيات تسويقية فعالة لعام 2024</a></h3>
                        <p class="card-text">اكتشف أحدث الاستراتيجيات التسويقية التي ستساعدك في تحقيق أهدافك...</p>
                        <div class="card-meta">
                            <span><i class="far fa-calendar"></i> 15 يناير</span>
                            <span><i class="far fa-clock"></i> 5 دقائق</span>
                        </div>
                    </div>
                </article>
                <article class="card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-image" style="background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-rocket" style="font-size: 3rem; color: white;"></i>
                    </div>
                    <div class="card-body">
                        <span class="badge">التحول الرقمي</span>
                        <h3 class="card-title"><a href="#">دليلك الشامل للتحول الرقمي في المؤسسات</a></h3>
                        <p class="card-text">خطوات عملية لتنفيذ التحول الرقمي في شركتك بنجاح...</p>
                        <div class="card-meta">
                            <span><i class="far fa-calendar"></i> 10 يناير</span>
                            <span><i class="far fa-clock"></i> 8 دقائق</span>
                        </div>
                    </div>
                </article>
                <article class="card" data-aos="fade-up" data-aos-delay="300">
                    <div class="card-image" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-bullseye" style="font-size: 3rem; color: white;"></i>
                    </div>
                    <div class="card-body">
                        <span class="badge">ريادة الأعمال</span>
                        <h3 class="card-title"><a href="#">كيف تبني علامة تجارية قوية من الصفر</a></h3>
                        <p class="card-text">نصائح عملية لبناء هوية تجارية مميزة تترك انطباعاً دائماً...</p>
                        <div class="card-meta">
                            <span><i class="far fa-calendar"></i> 5 يناير</span>
                            <span><i class="far fa-clock"></i> 6 دقائق</span>
                        </div>
                    </div>
                </article>
            <?php endif; ?>
        </div>

        <div class="text-center mt-8" data-aos="fade-up">
            <a href="<?= url('pages/blog.php') ?>" class="btn btn-outline">
                عرض جميع المقالات
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section" data-aos="fade-up">
    <div class="container">
        <h2>هل أنت مستعد لتحقيق النمو؟</h2>
        <p>احجز استشارة مجانية اليوم واكتشف كيف يمكننا مساعدتك في تحقيق أهدافك التسويقية والرقمية</p>
        <div class="d-flex justify-center gap-4 flex-wrap">
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-calendar-check"></i>
                احجز استشارة مجانية
            </a>
            <a href="tel:+966500000000" class="btn btn-outline btn-lg">
                <i class="fas fa-phone-alt"></i>
                اتصل بنا الآن
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * صفحة عرض دراسة حالة واحدة
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Get case ID
$caseId = $_GET['id'] ?? '';

// قراءة دراسات الحالة من JSON
$casesFile = dirname(__DIR__) . '/data/case-studies.json';
$cases = json_decode(file_get_contents($casesFile), true);

// Find the specific case
$case = null;
foreach ($cases as $c) {
    if ($c['id'] === $caseId) {
        $case = $c;
        break;
    }
}

// Redirect if not found
if (!$case) {
    header('Location: ' . url('pages/case-studies.php'));
    exit;
}

$pageTitle = $case['title'] . ' - دراسات الحالة - ' . SITE_NAME;
$pageDescription = $case['description'];

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= url('assets/css/utilities.css') ?>">

<!-- Hero Section -->
<section class="case-hero" style="background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);">
    <div class="container">
        <div class="case-hero-content" data-aos="fade-up">
            <a href="<?= url('pages/case-studies.php') ?>" class="back-link">
                <i class="fas fa-arrow-right"></i> العودة لدراسات الحالة
            </a>
            <h1><?= $case['title'] ?></h1>
            <p class="subtitle"><?= $case['subtitle'] ?></p>
            <div class="case-meta">
                <span><i class="fas fa-clock"></i> <?= $case['duration'] ?></span>
                <span><i class="fas fa-tag"></i> <?= getCategoryName($case['category']) ?></span>
            </div>
        </div>
    </div>
</section>

<!-- Case Image -->
<section class="case-image-section">
    <div class="container">
        <div class="case-main-image glass-card" data-aos="fade-up">
            <?php 
            $imagePath = 'assets/images/case-studies/' . $case['image'];
            if (file_exists(dirname(__DIR__) . '/' . $imagePath) && $case['image'] !== 'placeholder.jpg'): 
            ?>
                <img src="<?= url($imagePath) ?>" alt="<?= $case['title'] ?>">
            <?php else: ?>
                <div class="placeholder-large">
                    <i class="fas fa-chart-line"></i>
                    <p>نتائج استثنائية</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Overview -->
<section class="overview-section">
    <div class="container">
        <div class="overview-grid">
            <!-- Challenge -->
            <div class="overview-card glass-card" data-aos="fade-up">
                <div class="card-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3>التحدي</h3>
                <p><?= nl2br($case['challenge']) ?></p>
            </div>
            
           <!-- Solution -->
            <div class="overview-card glass-card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3>الحل</h3>
                <p><?= nl2br($case['solution']) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Results -->
<section class="results-section">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <h2>النتائج المحققة</h2>
            <p>أرقام حقيقية تعكس النجاح الذي حققناه</p>
        </div>
        
        <div class="results-grid">
            <?php foreach ($case['results'] as $index => $result): ?>
            <div class="result-card glass-card glow-on-hover" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                <div class="result-number text-gradient"><?= $result['value'] ?></div>
                <div class="result-label"><?= $result['label'] ?></div>
                <?php if (isset($result['percentage'])): ?>
                <div class="result-percentage">
                    <i class="fas fa-arrow-up"></i> <?= $result['percentage'] ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Tools Used -->
<section class="tools-section">
    <div class="container">
        <div class="section-header" data-aos="fade-up">
            <h3>الأدوات المستخدمة</h3>
        </div>
        <div class="tools-grid" data-aos="fade-up">
            <?php foreach ($case['tools'] as $tool): ?>
            <div class="tool-badge">
                <i class="fas fa-check-circle"></i> <?= $tool ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content glass-card" data-aos="fade-up">
            <h2>هل تريد نتائج مماثلة؟</h2>
            <p>دعنا نناقش كيف يمكننا مساعدتك في تحقيق أهدافك</p>
            <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg">
                احجز استشارة مجانية <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</section>

<style>
.case-hero {
    padding: calc(var(--header-height) + var(--space-12)) 0 var(--space-12);
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    color: var(--text-secondary);
    margin-bottom: var(--space-6);
    transition: color var(--transition);
}

.back-link:hover {
    color: var(--primary);
}

.case-hero-content h1 {
    font-size: var(--font-size-5xl);
    margin-bottom: var(--space-3);
}

.subtitle {
    font-size: var(--font-size-xl);
    color: var(--primary);
    font-weight: 600;
    margin-bottom: var(--space-6);
}

.case-meta {
    display: flex;
    gap: var(--space-8);
    color: var(--text-secondary);
}

.case-meta span {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.case-image-section {
    padding: var(--space-16) 0;
}

.case-main-image {
    border-radius: var(--radius-2xl);
    overflow: hidden;
    max-height: 500px;
}

.case-main-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.placeholder-large {
    height: 400px;
    background: var(--bg-tertiary);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.placeholder-large i {
    font-size: 4rem;
    margin-bottom: var(--space-4);
}

.overview-section {
    padding: var(--space-16) 0;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-8);
}

.overview-card {
    padding: var(--space-8);
}

.card-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient-primary);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: var(--space-4);
}

.card-icon i {
    font-size: 1.5rem;
    color: white;
}

.overview-card h3 {
    font-size: var(--font-size-2xl);
    margin-bottom: var(--space-4);
}

.overview-card p {
    color: var(--text-secondary);
    line-height: 1.8;
}

.results-section {
    padding: var(--space-20) 0;
    background: var(--gradient-dark);
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-6);
    margin-top: var(--space-12);
}

.result-card {
    padding: var(--space-8);
    text-align: center;
}

.result-number {
    font-size: var(--font-size-5xl);
    font-weight: 800;
    margin-bottom: var(--space-3);
}

.result-label {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
    margin-bottom: var(--space-3);
}

.result-percentage {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    background: rgba(16, 185, 129, 0.2);
    color: var(--success);
    border-radius: var(--radius-full);
    font-weight: 600;
}

.tools-section {
    padding: var(--space-16) 0;
}

.tools-grid {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-4);
    justify-content: center;
    margin-top: var(--space-8);
}

.tool-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-6);
    background: rgba(255, 107, 53, 0.1);
    color: var(--primary);
    border: 2px solid rgba(255, 107, 53, 0.3);
    border-radius: var(--radius-full);
    font-weight: 500;
}

.cta-section {
    padding: var(--space-20) 0;
}

.cta-content {
    text-align: center;
    padding: var(--space-16);
    border-radius: var(--radius-2xl);
}

.cta-content h2 {
    font-size: var(--font-size-4xl);
    margin-bottom: var(--space-4);
}

.cta-content p {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
    margin-bottom: var(--space-8);
}

@media (max-width: 992px) {
    .overview-grid,
    .results-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php 
function getCategoryName($category) {
    $names = [
        'education' => 'تعليم',
        'b2b' => 'خدمات مهنية',
        'ecommerce' => 'تجارة إلكترونية',
        'retail' => 'بيع بالتجزئة',
        'technology' => 'تقنية',
        'fitness' => 'رياضة ولياقة'
    ];
    return $names[$category] ?? $category;
}

include dirname(__DIR__) . '/includes/footer.php'; 
?>

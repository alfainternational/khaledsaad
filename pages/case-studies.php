<?php
/**
 * صفحة دراسات الحالة
 * عرض جميع المشاريع الناجحة
 */

require_once dirname(__DIR__) . '/includes/init.php';

// قراءة دراسات الحالة من JSON
$casesFile = dirname(__DIR__) . '/data/case-studies.json';
$cases = json_decode(file_get_contents($casesFile), true);

// Filter by category from query string
$selectedCategory = $_GET['category'] ?? 'all';
if ($selectedCategory !== 'all') {
    $cases = array_filter($cases, function($case) use ($selectedCategory) {
        return $case['category'] === $selectedCategory;
    });
}

$pageTitle = 'دراسات الحالة - ' . SITE_NAME;
$pageDescription = 'استعرض مشاريعنا الناجحة وكيف ساعدنا عملاءنا في تحقيق نتائج استثنائية';

include dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= url('assets/css/utilities.css') ?>">

<!-- Hero Section -->
<section class="case-studies-hero">
    <div class="container">
        <div class="hero-content" data-aos="fade-up">
            <span class="section-badge">نتائج حقيقية</span>
            <h1>مشاريع ناجحة حققت نتائج مذهلة</h1>
            <p>استعرض كيف ساعدنا عملاءنا في مختلف القطاعات على تحقيق أهدافهم وتجاوز التوقعات</p>
        </div>
    </div>
</section>

<!-- Filter Tabs -->
<section class="filter-section">
    <div class="container">
        <div class="filter-tabs" data-aos="fade-up">
            <a href="?category=all" class="filter-tab <?= $selectedCategory === 'all' ? 'active' : '' ?>">
                <i class="fas fa-th"></i> الكل
            </a>
            <a href="?category=education" class="filter-tab <?= $selectedCategory === 'education' ? 'active' : '' ?>">
                <i class="fas fa-graduation-cap"></i> تعليم
            </a>
            <a href="?category=b2b" class="filter-tab <?= $selectedCategory === 'b2b' ? 'active' : '' ?>">
                <i class="fas fa-briefcase"></i> B2B
            </a>
            <a href="?category=ecommerce" class="filter-tab <?= $selectedCategory === 'ecommerce' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i> تجارة
            </a>
            <a href="?category=retail" class="filter-tab <?= $selectedCategory === 'retail' ? 'active' : '' ?>">
                <i class="fas fa-store"></i> بيع بالتجزئة
            </a>
            <a href="?category=technology" class="filter-tab <?= $selectedCategory === 'technology' ? 'active' : '' ?>">
                <i class="fas fa-laptop-code"></i> تقنية
            </a>
            <a href="?category=fitness" class="filter-tab <?= $selectedCategory === 'fitness' ? 'active' : '' ?>">
                <i class="fas fa-dumbbell"></i> رياضة
            </a>
        </div>
    </div>
</section>

<!-- Case Studies Grid -->
<section class="cases-grid-section">
    <div class="container">
        <div class="cases-grid">
            <?php foreach ($cases as $case): ?>
            <div class="case-card glass-card glow-on-hover" data-aos="fade-up">
                <div class="case-image">
                    <?php 
                    $imagePath = 'assets/images/case-studies/' . $case['image'];
                    if (file_exists(dirname(__DIR__) . '/' . $imagePath) && $case['image'] !== 'placeholder.jpg'): 
                    ?>
                        <img src="<?= url($imagePath) ?>" alt="<?= $case['title'] ?>">
                    <?php else: ?>
                        <div class="placeholder-image">
                            <i class="fas fa-image"></i>
                            <span>قريباً</span>
                        </div>
                    <?php endif; ?>
                    <div class="case-category"><?= getCategoryName($case['category']) ?></div>
                </div>
                <div class="case-body">
                    <h3><?= $case['title'] ?></h3>
                    <p class="case-subtitle"><?= $case['subtitle'] ?></p>
                    <p class="case-desc"><?= $case['description'] ?></p>
                    
                    <div class="case-stats">
                        <?php foreach (array_slice($case['results'], 0, 2) as $result): ?>
                        <div class="stat">
                            <span class="stat-value text-primary"><?= $result['value'] ?></span>
                            <span class="stat-label"><?= $result['label'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="<?= url('pages/case-study-single.php?id=' . $case['id']) ?>" class="btn btn-primary">
                        عرض التفاصيل <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
.case-studies-hero {
    background: var(--gradient-dark);
    padding: var(--space-16) 0 var(--space-12);
    position: relative;
    overflow: hidden;
}

.case-studies-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100%;
    background: var(--gradient-glow);
    opacity: 0.5;
}

.hero-content {
    text-align: center;
    max-width: 700px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.section-badge {
    display: inline-block;
    padding: var(--space-2) var(--space-4);
    background: rgba(255, 107, 53, 0.2);
    color: var(--primary);
    border-radius: var(--radius-full);
    font-size: var(--font-size-sm);
    font-weight: 600;
    margin-bottom: var(--space-4);
}

.hero-content h1 {
    font-size: var(--font-size-4xl);
    margin-bottom: var(--space-4);
}

.hero-content p {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
}

/* Filter Tabs */
.filter-section {
    background: var(--bg-secondary);
    padding: var(--space-8) 0;
    border-bottom: 1px solid var(--border-color);
}

.filter-tabs {
    display: flex;
    gap: var(--space-2);
    flex-wrap: wrap;
    justify-content: center;
}

.filter-tab {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-6);
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border-radius: var(--radius-full);
    font-weight: 500;
    transition: all var(--transition);
    border: 2px solid transparent;
}

.filter-tab:hover,
.filter-tab.active {
    background: rgba(255, 107, 53, 0.1);
    color: var(--primary);
    border-color: var(--primary);
}

/* Cases Grid */
.cases-grid-section {
    padding: var(--space-20) 0;
}

.cases-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: var(--space-8);
}

.case-card {
    overflow: hidden;
    transition: all var(--transition);
}

.case-image {
    position: relative;
    height: 240px;
    overflow: hidden;
}

.case-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform var(--transition-slow);
}

.case-card:hover .case-image img {
    transform: scale(1.1);
}

.placeholder-image {
    width: 100%;
    height: 100%;
    background: var(--bg-tertiary);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

.placeholder-image i {
    font-size: 3rem;
    margin-bottom: var(--space-2);
}

.case-category {
    position: absolute;
    top: var(--space-4);
    right: var(--space-4);
    padding: var(--space-2) var(--space-4);
    background: rgba(10, 14, 39, 0.9);
    color: var(--primary);
    border-radius: var(--radius-full);
    font-size: var(--font-size-sm);
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.case-body {
    padding: var(--space-6);
}

.case-body h3 {
    font-size: var(--font-size-2xl);
    margin-bottom: var(--space-2);
}

.case-subtitle {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: var(--space-3);
}

.case-desc {
    color: var(--text-secondary);
    margin-bottom: var(--space-6);
    line-height: 1.7;
}

.case-stats {
    display: flex;
    gap: var(--space-8);
    margin-bottom: var(--space-6);
    padding: var(--space-4);
    background: rgba(255, 107, 53, 0.05);
    border-radius: var(--radius-lg);
}

.stat {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: var(--font-size-2xl);
    font-weight: 700;
    margin-bottom: var(--space-1);
}

.stat-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .cases-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-tabs {
        overflow-x: auto;
        justify-content: flex-start;
        padding-bottom: var(--space-2);
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

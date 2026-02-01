<?php
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'المدونة - ' . SITE_NAME;
$pageDescription = 'أحدث المقالات والنصائح في مجال التسويق الرقمي والتحول الرقمي وريادة الأعمال.';

$category = isset($_GET['category']) ? clean($_GET['category']) : '';
$search = isset($_GET['q']) ? clean($_GET['q']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

try {
    $categories = db()->fetchAll("SELECT * FROM blog_categories WHERE is_active = 1 ORDER BY sort_order ASC");
    
    $where = "WHERE p.status = 'published'";
    $params = [];
    
    if ($category) {
        $where .= " AND c.slug = ?";
        $params[] = $category;
    }
    
    if ($search) {
        $where .= " AND (p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $total = db()->fetchOne("SELECT COUNT(*) as count FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id $where", $params)['count'];
    $posts = db()->fetchAll("SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id LEFT JOIN users u ON p.author_id = u.id $where ORDER BY p.published_at DESC LIMIT $perPage OFFSET $offset", $params);
    $totalPages = ceil($total / $perPage);
} catch (Exception $e) {
    $categories = [];
    $posts = [];
    $totalPages = 0;
}

include dirname(__DIR__) . '/includes/header.php';
?>

<section style="background: var(--bg-secondary); padding: var(--space-12) 0;">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <span class="hero-badge"><i class="fas fa-blog"></i> المدونة</span>
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-4);">أحدث المقالات</h1>
            <p style="font-size: var(--font-size-lg); color: var(--text-secondary); max-width: 600px; margin: 0 auto;">مقالات ونصائح في التسويق الرقمي والتحول الرقمي</p>
        </div>
    </div>
</section>

<section style="padding: var(--space-12) 0;">
    <div class="container">
        <!-- Filters -->
        <div style="display: flex; flex-wrap: wrap; gap: var(--space-4); margin-bottom: var(--space-8); justify-content: center;">
            <a href="<?= url('pages/blog.php') ?>" class="btn <?= empty($category) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">الكل</a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= url('pages/blog.php?category=' . e($cat['slug'])) ?>" class="btn <?= $category === $cat['slug'] ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Posts Grid -->
        <?php if (!empty($posts)): ?>
        <div class="services-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
            <?php foreach ($posts as $post): ?>
            <article class="card" data-aos="fade-up">
                <div class="card-image">
                    <?php if ($post['featured_image']): ?>
                    <img src="<?= url('uploads/' . e($post['featured_image'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
                    <?php else: ?>
                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-newspaper" style="font-size: 2rem; color: white;"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($post['category_name']): ?>
                    <a href="<?= url('pages/blog.php?category=' . e($post['category_slug'])) ?>" class="badge"><?= e($post['category_name']) ?></a>
                    <?php endif; ?>
                    <h3 class="card-title"><a href="<?= url('pages/blog-post.php?slug=' . e($post['slug'])) ?>"><?= e($post['title']) ?></a></h3>
                    <p class="card-text"><?= e(truncate($post['excerpt'] ?: strip_tags($post['content']), 120)) ?></p>
                    <div class="card-meta">
                        <span><i class="far fa-calendar"></i> <?= formatDate($post['published_at'], 'short') ?></span>
                        <span><i class="far fa-clock"></i> <?= $post['reading_time'] ?: readingTime($post['content']) ?> دقائق</span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav style="display: flex; justify-content: center; gap: var(--space-2); margin-top: var(--space-12);">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?><?= $category ? '&category=' . e($category) : '' ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center" style="padding: var(--space-12);">
            <i class="fas fa-newspaper" style="font-size: 4rem; color: var(--text-muted); margin-bottom: var(--space-4);"></i>
            <h3>لا توجد مقالات</h3>
            <p style="color: var(--text-secondary);">لم نجد مقالات مطابقة للبحث</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

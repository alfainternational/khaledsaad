<?php
/**
 * Saved Posts
 * المقالات المحفوظة
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'المحفوظات';

// Handle unsave action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsave'])) {
    if (Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $postId = (int)$_POST['post_id'];
        db()->delete('saved_posts', 'client_id = ? AND post_id = ?', [$_SESSION['client_id'], $postId]);
    }
}

// Get saved posts
$savedPosts = db()->fetchAll(
    "SELECT p.*, c.name as category_name, sp.created_at as saved_at
     FROM blog_posts p
     INNER JOIN saved_posts sp ON p.id = sp.post_id
     LEFT JOIN blog_categories c ON p.category_id = c.id
     WHERE sp.client_id = ? AND p.status = 'published'
     ORDER BY sp.created_at DESC",
    [$_SESSION['client_id']]
);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>المحفوظات</h1>
        <p>المقالات التي قمت بحفظها للقراءة لاحقاً</p>
    </div>
    <a href="<?= url('blog') ?>" class="btn btn-secondary">
        <i class="fas fa-newspaper"></i>
        تصفح المدونة
    </a>
</div>

<?php if (!empty($savedPosts)): ?>
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php foreach ($savedPosts as $post): ?>
        <div class="saved-post" style="display: flex; gap: 1.5rem; align-items: flex-start; padding: 1.5rem;">
            <?php if ($post['featured_image']): ?>
            <div class="saved-post-image" style="width: 150px; height: 100px; border-radius: 8px; overflow: hidden; flex-shrink: 0;">
                <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <?php endif; ?>
            <div class="saved-post-info" style="flex: 1;">
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <?php if ($post['category_name']): ?>
                    <span class="badge badge-primary"><?= e($post['category_name']) ?></span>
                    <?php endif; ?>
                </div>
                <h4 style="margin: 0 0 0.5rem;">
                    <a href="<?= url('blog/' . $post['slug']) ?>" style="color: var(--dash-text); text-decoration: none;">
                        <?= e($post['title']) ?>
                    </a>
                </h4>
                <?php if ($post['excerpt']): ?>
                <p style="color: var(--dash-text-muted); font-size: 0.875rem; margin: 0 0 0.75rem; line-height: 1.6;">
                    <?= e(mb_substr($post['excerpt'], 0, 150)) ?>...
                </p>
                <?php endif; ?>
                <div style="display: flex; gap: 1.5rem; font-size: 0.8rem; color: var(--dash-text-muted);">
                    <span><i class="fas fa-calendar"></i> <?= formatDate($post['published_at']) ?></span>
                    <span><i class="fas fa-eye"></i> <?= formatNumber($post['views_count']) ?></span>
                    <span><i class="fas fa-clock"></i> <?= $post['reading_time'] ?: '5' ?> دقائق قراءة</span>
                </div>
            </div>
            <div class="saved-post-actions" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="<?= url('blog/' . $post['slug']) ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-book-open"></i> قراءة
                </a>
                <form method="POST" style="margin: 0;" onsubmit="return confirm('هل تريد إزالة هذا المقال من المحفوظات؟')">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <button type="submit" name="unsave" class="btn btn-danger btn-sm" style="width: 100%;">
                        <i class="fas fa-trash"></i> إزالة
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-bookmark"></i>
            <h3>لا توجد مقالات محفوظة</h3>
            <p>لم تقم بحفظ أي مقالات بعد. تصفح المدونة واحفظ المقالات التي تهمك</p>
            <a href="<?= url('blog') ?>" class="btn btn-primary">
                <i class="fas fa-newspaper"></i>
                تصفح المدونة
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

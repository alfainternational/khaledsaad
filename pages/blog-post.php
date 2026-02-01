<?php
/**
 * صفحة المقال الفردي
 * موقع خالد سعد للاستشارات
 */

require_once dirname(__DIR__) . '/includes/init.php';

$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: ' . url('pages/blog.php'));
    exit;
}

// جلب المقال
try {
    $post = db()->fetchOne("
        SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.slug = ? AND p.status = 'published'
    ", [$slug]);

    if (!$post) {
        header('Location: ' . url('pages/error.php?code=404'));
        exit;
    }

    // زيادة عدد المشاهدات
    db()->query("UPDATE blog_posts SET views_count = views_count + 1 WHERE id = ?", [$post['id']]);

    // جلب المقالات ذات الصلة
    $relatedPosts = db()->fetchAll("
        SELECT p.*, c.name as category_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        WHERE p.status = 'published'
        AND p.id != ?
        AND (p.category_id = ? OR p.category_id IS NULL)
        ORDER BY p.published_at DESC
        LIMIT 3
    ", [$post['id'], $post['category_id']]);

    // جلب الوسوم
    $tags = db()->fetchAll("
        SELECT t.*
        FROM tags t
        INNER JOIN post_tags pt ON t.id = pt.tag_id
        WHERE pt.post_id = ?
    ", [$post['id']]);

} catch (Exception $e) {
    header('Location: ' . url('pages/error.php?code=500'));
    exit;
}

// إعدادات SEO
$pageTitle = $post['meta_title'] ?: $post['title'] . ' - ' . SITE_NAME;
$pageDescription = $post['meta_description'] ?: truncate(strip_tags($post['content']), 160);
$pageKeywords = $post['meta_keywords'] ?: '';
$pageImage = $post['og_image'] ?: ($post['featured_image'] ? url('uploads/' . $post['featured_image']) : '');
$canonicalUrl = url('blog/' . $post['slug']);

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Article Header -->
<section style="background: var(--bg-secondary); padding: var(--space-12) 0;">
    <div class="container" style="max-width: 900px;">
        <article>
            <!-- Breadcrumb -->
            <nav style="margin-bottom: var(--space-6);" aria-label="مسار التنقل">
                <ol style="display: flex; flex-wrap: wrap; gap: var(--space-2); list-style: none; font-size: var(--font-size-sm); color: var(--text-muted);">
                    <li><a href="<?= url('') ?>" style="color: var(--text-muted);">الرئيسية</a></li>
                    <li>/</li>
                    <li><a href="<?= url('pages/blog.php') ?>" style="color: var(--text-muted);">المدونة</a></li>
                    <?php if ($post['category_name']): ?>
                    <li>/</li>
                    <li><a href="<?= url('pages/blog.php?category=' . e($post['category_slug'])) ?>" style="color: var(--text-muted);"><?= e($post['category_name']) ?></a></li>
                    <?php endif; ?>
                </ol>
            </nav>

            <!-- Category & Reading Time -->
            <div style="display: flex; gap: var(--space-4); margin-bottom: var(--space-4);">
                <?php if ($post['category_name']): ?>
                <a href="<?= url('pages/blog.php?category=' . e($post['category_slug'])) ?>" class="badge"><?= e($post['category_name']) ?></a>
                <?php endif; ?>
                <span style="color: var(--text-muted); font-size: var(--font-size-sm);">
                    <i class="far fa-clock"></i>
                    <?= $post['reading_time'] ?: readingTime($post['content']) ?> دقائق للقراءة
                </span>
            </div>

            <!-- Title -->
            <h1 style="font-size: var(--font-size-4xl); margin-bottom: var(--space-6); line-height: 1.3;">
                <?= e($post['title']) ?>
            </h1>

            <!-- Meta -->
            <div style="display: flex; flex-wrap: wrap; gap: var(--space-6); align-items: center; padding-bottom: var(--space-6); border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <div style="width: 50px; height: 50px; background: var(--primary); color: white; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: var(--font-size-lg);">
                        <?= mb_substr($post['author_name'] ?? 'م', 0, 1) ?>
                    </div>
                    <div>
                        <strong><?= e($post['author_name'] ?? 'فريق التحرير') ?></strong>
                        <span style="display: block; font-size: var(--font-size-sm); color: var(--text-muted);">
                            <?= formatDate($post['published_at'], 'full') ?>
                        </span>
                    </div>
                </div>

                <div style="display: flex; gap: var(--space-4); margin-right: auto;">
                    <span style="color: var(--text-muted); font-size: var(--font-size-sm);">
                        <i class="far fa-eye"></i> <?= formatNumber($post['views_count']) ?> مشاهدة
                    </span>
                </div>

                <!-- Share Buttons -->
                <div style="display: flex; gap: var(--space-2);">
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode($canonicalUrl) ?>&text=<?= urlencode($post['title']) ?>"
                       target="_blank" rel="noopener"
                       style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: var(--bg-tertiary); border-radius: var(--radius-full); color: var(--text-secondary);"
                       aria-label="مشاركة على تويتر">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode($canonicalUrl) ?>&title=<?= urlencode($post['title']) ?>"
                       target="_blank" rel="noopener"
                       style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: var(--bg-tertiary); border-radius: var(--radius-full); color: var(--text-secondary);"
                       aria-label="مشاركة على لينكدإن">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="https://wa.me/?text=<?= urlencode($post['title'] . ' ' . $canonicalUrl) ?>"
                       target="_blank" rel="noopener"
                       style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: var(--bg-tertiary); border-radius: var(--radius-full); color: var(--text-secondary);"
                       aria-label="مشاركة على واتساب">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>
        </article>
    </div>
</section>

<!-- Article Content -->
<section style="padding: var(--space-12) 0;">
    <div class="container" style="max-width: 900px;">
        <article>
            <!-- Featured Image -->
            <?php if ($post['featured_image']): ?>
            <figure style="margin-bottom: var(--space-8);">
                <img src="<?= url('uploads/' . e($post['featured_image'])) ?>"
                     alt="<?= e($post['title']) ?>"
                     style="width: 100%; border-radius: var(--radius-xl); aspect-ratio: 16/9; object-fit: cover;">
            </figure>
            <?php endif; ?>

            <!-- Content -->
            <div class="article-content" style="font-size: var(--font-size-lg); line-height: 2;">
                <?= $post['content'] ?>
            </div>

            <!-- Tags -->
            <?php if (!empty($tags)): ?>
            <div style="margin-top: var(--space-8); padding-top: var(--space-6); border-top: 1px solid var(--border-color);">
                <h4 style="margin-bottom: var(--space-4); font-size: var(--font-size-base);">
                    <i class="fas fa-tags" style="margin-left: var(--space-2); color: var(--primary);"></i>
                    الوسوم
                </h4>
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                    <?php foreach ($tags as $tag): ?>
                    <a href="<?= url('pages/blog.php?tag=' . e($tag['slug'])) ?>"
                       style="padding: var(--space-2) var(--space-4); background: var(--bg-tertiary); border-radius: var(--radius-full); font-size: var(--font-size-sm); color: var(--text-secondary);">
                        #<?= e($tag['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Author Box -->
            <div style="margin-top: var(--space-8); padding: var(--space-6); background: var(--bg-secondary); border-radius: var(--radius-xl);">
                <div style="display: flex; gap: var(--space-4); align-items: center;">
                    <div style="width: 80px; height: 80px; background: var(--primary); color: white; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: var(--font-size-2xl); flex-shrink: 0;">
                        <?= mb_substr($post['author_name'] ?? 'م', 0, 1) ?>
                    </div>
                    <div>
                        <h4 style="margin-bottom: var(--space-1);"><?= e($post['author_name'] ?? 'فريق التحرير') ?></h4>
                        <p style="color: var(--text-secondary); margin: 0; font-size: var(--font-size-sm);">
                            خبير في التسويق الرقمي والتحول الرقمي. يشارك خبرته ومعرفته لمساعدة الشركات في تحقيق النمو.
                        </p>
                    </div>
                </div>
            </div>
        </article>
    </div>
</section>

<!-- Related Posts -->
<?php if (!empty($relatedPosts)): ?>
<section style="padding: var(--space-12) 0; background: var(--bg-secondary);">
    <div class="container">
        <h2 style="text-align: center; margin-bottom: var(--space-8);">مقالات ذات صلة</h2>

        <div class="services-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            <?php foreach ($relatedPosts as $related): ?>
            <article class="card">
                <div class="card-image">
                    <?php if ($related['featured_image']): ?>
                    <img src="<?= url('uploads/' . e($related['featured_image'])) ?>" alt="<?= e($related['title']) ?>" loading="lazy">
                    <?php else: ?>
                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-newspaper" style="font-size: 2rem; color: white;"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($related['category_name']): ?>
                    <span class="badge" style="margin-bottom: var(--space-2);"><?= e($related['category_name']) ?></span>
                    <?php endif; ?>
                    <h3 class="card-title" style="font-size: var(--font-size-lg);">
                        <a href="<?= url('pages/blog-post.php?slug=' . e($related['slug'])) ?>"><?= e($related['title']) ?></a>
                    </h3>
                    <div class="card-meta">
                        <span><i class="far fa-calendar"></i> <?= formatDate($related['published_at'], 'short') ?></span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <h2>هل استفدت من هذا المقال؟</h2>
        <p>احجز استشارة مجانية واحصل على نصائح مخصصة لأعمالك</p>
        <a href="<?= url('pages/contact.php') ?>" class="btn btn-primary btn-lg">
            <i class="fas fa-calendar-check"></i>
            احجز استشارة مجانية
        </a>
    </div>
</section>

<style>
/* Article Content Styling */
.article-content h2 {
    font-size: var(--font-size-2xl);
    margin-top: var(--space-8);
    margin-bottom: var(--space-4);
}
.article-content h3 {
    font-size: var(--font-size-xl);
    margin-top: var(--space-6);
    margin-bottom: var(--space-3);
}
.article-content p {
    margin-bottom: var(--space-6);
}
.article-content ul, .article-content ol {
    margin-bottom: var(--space-6);
    padding-right: var(--space-6);
}
.article-content li {
    margin-bottom: var(--space-2);
}
.article-content blockquote {
    padding: var(--space-6);
    background: var(--bg-secondary);
    border-right: 4px solid var(--primary);
    border-radius: var(--radius-lg);
    margin: var(--space-6) 0;
    font-style: italic;
}
.article-content img {
    max-width: 100%;
    height: auto;
    border-radius: var(--radius-lg);
    margin: var(--space-6) 0;
}
.article-content a {
    color: var(--primary);
    text-decoration: underline;
}
.article-content code {
    background: var(--bg-tertiary);
    padding: 0.2em 0.4em;
    border-radius: var(--radius-sm);
    font-family: monospace;
}
.article-content pre {
    background: var(--bg-secondary);
    padding: var(--space-4);
    border-radius: var(--radius-lg);
    overflow-x: auto;
    margin: var(--space-6) 0;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
/**
 * Post Edit/Create
 * تعديل/إنشاء مقال
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$id = (int)($_GET['id'] ?? 0);
$post = null;

if ($id) {
    $post = db()->fetchOne("SELECT * FROM blog_posts WHERE id = ?", [$id]);
    if (!$post) {
        header('Location: posts.php');
        exit;
    }
    $pageTitle = 'تعديل المقال';
} else {
    $pageTitle = 'مقال جديد';
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $title = clean($_POST['title'] ?? '');
        $slug = clean($_POST['slug'] ?? '') ?: generateSlug($title);
        $content = $_POST['content'] ?? '';
        $excerpt = clean($_POST['excerpt'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $status = in_array($_POST['status'] ?? '', ['draft', 'published']) ? $_POST['status'] : 'draft';
        $featuredImage = clean($_POST['featured_image'] ?? '');
        $metaTitle = clean($_POST['meta_title'] ?? '');
        $metaDescription = clean($_POST['meta_description'] ?? '');

        if (!$title) {
            $error = 'العنوان مطلوب';
        } else {
            $data = [
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'excerpt' => $excerpt,
                'category_id' => $categoryId,
                'status' => $status,
                'featured_image' => $featuredImage,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'author_id' => $_SESSION['admin_id'],
            ];

            try {
                if ($id) {
                    db()->update('blog_posts', $data, 'id = ?', ['id' => $id]);
                    Security::logActivity('post_updated', 'blog_posts', $id);
                    $success = 'تم تحديث المقال بنجاح';
                    $post = array_merge($post, $data);
                } else {
                    if ($status === 'published') {
                        $data['published_at'] = date('Y-m-d H:i:s');
                    }
                    $newId = db()->insert('blog_posts', $data);
                    Security::logActivity('post_created', 'blog_posts', $newId);
                    header('Location: post-edit.php?id=' . $newId . '&created=1');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'حدث خطأ أثناء الحفظ';
            }
        }
    }
}

$categories = db()->fetchAll("SELECT * FROM blog_categories ORDER BY name");

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= $pageTitle ?></h1>
    </div>
    <div class="quick-actions">
        <a href="posts.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> العودة</a>
        <?php if ($post && $post['status'] === 'published'): ?>
        <a href="<?= url('pages/blog-post.php?slug=' . $post['slug']) ?>" target="_blank" class="btn btn-secondary">
            <i class="fas fa-external-link-alt"></i> عرض
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['created'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم إنشاء المقال بنجاح</div>
<?php endif; ?>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= Security::csrfField() ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <!-- Main Content -->
        <div>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">عنوان المقال <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= e($post['title'] ?? '') ?>" required placeholder="أدخل عنوان المقال" id="postTitle">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الرابط المختصر (Slug)</label>
                        <input type="text" name="slug" class="form-control" value="<?= e($post['slug'] ?? '') ?>" placeholder="سيتم توليده تلقائياً" dir="ltr" data-slug-source="postTitle">
                    </div>

                    <div class="form-group">
                        <label class="form-label">المحتوى</label>
                        <textarea name="content" class="form-control" rows="15" placeholder="اكتب محتوى المقال..."><?= e($post['content'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">المقتطف</label>
                        <textarea name="excerpt" class="form-control" rows="3" placeholder="وصف قصير للمقال..." data-max-length="300"><?= e($post['excerpt'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- SEO -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-search"></i> تحسين محركات البحث (SEO)</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">عنوان SEO</label>
                        <input type="text" name="meta_title" class="form-control" value="<?= e($post['meta_title'] ?? '') ?>" placeholder="عنوان مخصص لمحركات البحث" data-max-length="60">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">وصف SEO</label>
                        <textarea name="meta_description" class="form-control" rows="2" placeholder="وصف مخصص لمحركات البحث" data-max-length="160"><?= e($post['meta_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Publish Box -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-paper-plane"></i> النشر</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-control">
                            <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>مسودة</option>
                            <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>منشور</option>
                        </select>
                    </div>
                    <?php if ($post): ?>
                    <div style="font-size: 0.8rem; color: var(--admin-text-muted); margin-bottom: 1rem;">
                        <p style="margin: 0;">تاريخ الإنشاء: <?= formatDate($post['created_at']) ?></p>
                        <?php if ($post['published_at']): ?>
                        <p style="margin: 0;">تاريخ النشر: <?= formatDate($post['published_at']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> <?= $id ? 'حفظ التغييرات' : 'نشر المقال' ?>
                    </button>
                </div>
            </div>

            <!-- Category -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-folder"></i> التصنيف</h3>
                </div>
                <div class="card-body">
                    <select name="category_id" class="form-control">
                        <option value="">-- بدون تصنيف --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($post['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Featured Image -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-image"></i> الصورة البارزة</h3>
                </div>
                <div class="card-body">
                    <div class="form-group mb-0">
                        <input type="text" name="featured_image" class="form-control" value="<?= e($post['featured_image'] ?? '') ?>" placeholder="رابط الصورة" dir="ltr">
                        <span class="form-hint">أدخل رابط الصورة أو استخدم مدير الملفات</span>
                    </div>
                    <?php if (!empty($post['featured_image'])): ?>
                    <img src="<?= e($post['featured_image']) ?>" alt="" style="max-width: 100%; margin-top: 0.5rem; border-radius: var(--admin-radius);">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Blog Posts Management
 * إدارة المقالات
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'المقالات';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db()->delete('blog_posts', 'id = ?', ['id' => $id]);
            Security::logActivity('post_deleted', 'blog_posts', $id);
        }
    }
    header('Location: posts.php?deleted=1');
    exit;
}

// Filters
$status = clean($_GET['status'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$search = clean($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Build query
$where = [];
$params = [];

if ($status) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}

if ($category) {
    $where[] = 'p.category_id = ?';
    $params[] = $category;
}

if ($search) {
    $where[] = '(p.title LIKE ? OR p.content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db()->fetchOne("SELECT COUNT(*) as c FROM blog_posts p $whereClause", $params)['c'] ?? 0;
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$posts = db()->fetchAll("
    SELECT p.*, c.name as category_name
    FROM blog_posts p
    LEFT JOIN blog_categories c ON p.category_id = c.id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

$categories = db()->fetchAll("SELECT * FROM blog_categories ORDER BY name");

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>المقالات</h1>
        <p>إدارة مقالات المدونة</p>
    </div>
    <a href="post-edit.php" class="btn btn-primary"><i class="fas fa-plus"></i> مقال جديد</a>
</div>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> تم حذف المقال بنجاح</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="بحث..." value="<?= e($search) ?>">
            </div>
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">جميع الحالات</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>منشور</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>مسودة</option>
            </select>
            <select name="category" class="form-control" onchange="this.form.submit()">
                <option value="">جميع التصنيفات</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<!-- Posts Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($posts)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>العنوان</th>
                        <th>التصنيف</th>
                        <th>الحالة</th>
                        <th>المشاهدات</th>
                        <th>التاريخ</th>
                        <th style="width: 150px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <a href="post-edit.php?id=<?= $post['id'] ?>" class="text-primary" style="font-weight: 500;">
                                <?= e(mb_substr($post['title'], 0, 60)) ?><?= mb_strlen($post['title']) > 60 ? '...' : '' ?>
                            </a>
                        </td>
                        <td><?= e($post['category_name'] ?? '-') ?></td>
                        <td>
                            <span class="badge badge-<?= $post['status'] === 'published' ? 'success' : 'secondary' ?>">
                                <?= $post['status'] === 'published' ? 'منشور' : 'مسودة' ?>
                            </span>
                        </td>
                        <td><?= formatNumber($post['views_count']) ?></td>
                        <td><?= formatDate($post['created_at'], 'short') ?></td>
                        <td>
                            <div class="actions">
                                <a href="<?= url('pages/blog-post.php?slug=' . $post['slug']) ?>" target="_blank" class="btn btn-sm btn-icon btn-secondary" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="post-edit.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-icon btn-secondary" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-danger" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= e($status) ?>&category=<?= $category ?>&search=<?= e($search) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <h3>لا توجد مقالات</h3>
            <p>ابدأ بإنشاء مقالك الأول</p>
            <a href="post-edit.php" class="btn btn-primary"><i class="fas fa-plus"></i> مقال جديد</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

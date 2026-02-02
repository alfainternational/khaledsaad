<?php
/**
 * Blog Categories Management
 * إدارة التصنيفات
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'التصنيفات';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = clean($_POST['name'] ?? '');
            $slug = clean($_POST['slug'] ?? '') ?: generateSlug($name);
            $description = clean($_POST['description'] ?? '');

            if (!$name) {
                $error = 'اسم التصنيف مطلوب';
            } else {
                $data = ['name' => $name, 'slug' => $slug, 'description' => $description];

                try {
                    if ($id) {
                        db()->update('blog_categories', $data, 'id = ?', ['id' => $id]);
                        $success = 'تم تحديث التصنيف بنجاح';
                    } else {
                        db()->insert('blog_categories', $data);
                        $success = 'تم إنشاء التصنيف بنجاح';
                    }
                } catch (Exception $e) {
                    $error = 'حدث خطأ';
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                db()->update('blog_posts', ['category_id' => null], 'category_id = ?', ['category_id' => $id]);
                db()->delete('blog_categories', 'id = ?', ['id' => $id]);
                $success = 'تم حذف التصنيف بنجاح';
            }
        }
    }
}

$categories = db()->fetchAll("
    SELECT c.*, COUNT(p.id) as posts_count
    FROM blog_categories c
    LEFT JOIN blog_posts p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.name
");

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>التصنيفات</h1>
        <p>إدارة تصنيفات المدونة</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('categoryModal')"><i class="fas fa-plus"></i> تصنيف جديد</button>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (!empty($categories)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الرابط</th>
                        <th>الوصف</th>
                        <th>المقالات</th>
                        <th style="width: 120px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= e($cat['name']) ?></td>
                        <td><code style="font-size: 0.8rem;"><?= e($cat['slug']) ?></code></td>
                        <td><?= e(mb_substr($cat['description'] ?? '', 0, 50)) ?><?= mb_strlen($cat['description'] ?? '') > 50 ? '...' : '' ?></td>
                        <td><span class="badge badge-secondary"><?= $cat['posts_count'] ?></span></td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-sm btn-icon btn-secondary" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
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
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-folder"></i>
            <h3>لا توجد تصنيفات</h3>
            <p>ابدأ بإنشاء أول تصنيف</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Category Modal -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">تصنيف جديد</h3>
            <button class="modal-close" onclick="closeModal('categoryModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="create" id="categoryAction">
            <input type="hidden" name="id" value="" id="categoryId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">اسم التصنيف <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required id="categoryName">
                </div>
                <div class="form-group">
                    <label class="form-label">الرابط المختصر</label>
                    <input type="text" name="slug" class="form-control" id="categorySlug" dir="ltr">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" class="form-control" rows="3" id="categoryDesc"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('categoryModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(cat) {
    document.getElementById('modalTitle').textContent = 'تعديل التصنيف';
    document.getElementById('categoryAction').value = 'update';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.name;
    document.getElementById('categorySlug').value = cat.slug;
    document.getElementById('categoryDesc').value = cat.description || '';
    openModal('categoryModal');
}

document.querySelector('#categoryModal form').addEventListener('reset', function() {
    document.getElementById('modalTitle').textContent = 'تصنيف جديد';
    document.getElementById('categoryAction').value = 'create';
    document.getElementById('categoryId').value = '';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

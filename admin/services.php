<?php
/**
 * Services Management
 * إدارة الخدمات
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'الخدمات';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'save') {
            $data = [
                'name' => clean($_POST['name'] ?? ''),
                'slug' => clean($_POST['slug'] ?? '') ?: generateSlug($_POST['name'] ?? ''),
                'description' => clean($_POST['description'] ?? ''),
                'short_description' => clean($_POST['short_description'] ?? ''),
                'icon' => clean($_POST['icon'] ?? 'fas fa-cog'),
                'price_from' => (float)($_POST['price_from'] ?? 0),
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];

            if (!$data['name']) {
                $error = 'اسم الخدمة مطلوب';
            } else {
                try {
                    if ($id) {
                        db()->update('services', $data, 'id = ?', ['id' => $id]);
                        $success = 'تم تحديث الخدمة بنجاح';
                    } else {
                        db()->insert('services', $data);
                        $success = 'تم إنشاء الخدمة بنجاح';
                    }
                } catch (Exception $e) {
                    $error = 'حدث خطأ';
                }
            }
        }

        if ($action === 'delete' && $id) {
            db()->delete('services', 'id = ?', ['id' => $id]);
            $success = 'تم حذف الخدمة بنجاح';
        }
    }
}

$services = db()->fetchAll("SELECT * FROM services ORDER BY sort_order, name");
$editService = null;
if (isset($_GET['edit'])) {
    $editService = db()->fetchOne("SELECT * FROM services WHERE id = ?", [(int)$_GET['edit']]);
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>الخدمات</h1>
        <p>إدارة خدماتك المقدمة</p>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 1.5rem;">
    <!-- Services List -->
    <div class="card">
        <div class="card-header">
            <h3>قائمة الخدمات</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($services)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">الترتيب</th>
                            <th>الخدمة</th>
                            <th>السعر يبدأ من</th>
                            <th>الحالة</th>
                            <th style="width: 120px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?= $service['sort_order'] ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 36px; height: 36px; background: var(--admin-bg); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <i class="<?= e($service['icon']) ?>" style="color: var(--admin-primary);"></i>
                                    </div>
                                    <div>
                                        <strong><?= e($service['name']) ?></strong>
                                        <?php if ($service['is_featured']): ?>
                                        <span class="badge badge-warning" style="margin-right: 0.5rem;">مميز</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= $service['price_from'] ? formatNumber($service['price_from']) . ' ر.س' : '-' ?></td>
                            <td>
                                <span class="badge badge-<?= $service['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $service['is_active'] ? 'مفعّل' : 'معطّل' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?edit=<?= $service['id'] ?>" class="btn btn-sm btn-icon btn-secondary" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $service['id'] ?>">
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
                <i class="fas fa-concierge-bell"></i>
                <h3>لا توجد خدمات</h3>
                <p>أضف خدماتك من النموذج الجانبي</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Form -->
    <div class="card">
        <div class="card-header">
            <h3><?= $editService ? 'تعديل الخدمة' : 'خدمة جديدة' ?></h3>
            <?php if ($editService): ?>
            <a href="services.php" class="btn btn-sm btn-secondary">إلغاء</a>
            <?php endif; ?>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editService['id'] ?? '' ?>">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">اسم الخدمة <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($editService['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الرابط المختصر</label>
                    <input type="text" name="slug" class="form-control" value="<?= e($editService['slug'] ?? '') ?>" dir="ltr">
                </div>
                <div class="form-group">
                    <label class="form-label">الأيقونة (Font Awesome)</label>
                    <input type="text" name="icon" class="form-control" value="<?= e($editService['icon'] ?? 'fas fa-cog') ?>" dir="ltr" placeholder="fas fa-cog">
                </div>
                <div class="form-group">
                    <label class="form-label">وصف مختصر</label>
                    <input type="text" name="short_description" class="form-control" value="<?= e($editService['short_description'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف الكامل</label>
                    <textarea name="description" class="form-control" rows="4"><?= e($editService['description'] ?? '') ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">السعر يبدأ من</label>
                        <input type="number" name="price_from" class="form-control" value="<?= e($editService['price_from'] ?? '') ?>" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= e($editService['sort_order'] ?? 0) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?= ($editService['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <span>مفعّل</span>
                    </label>
                </div>
                <div class="form-group mb-0">
                    <label class="form-check">
                        <input type="checkbox" name="is_featured" value="1" <?= ($editService['is_featured'] ?? 0) ? 'checked' : '' ?>>
                        <span>خدمة مميزة</span>
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> <?= $editService ? 'حفظ التغييرات' : 'إضافة الخدمة' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Success Stories Management
 * إدارة قصص النجاح
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'قصص النجاح';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'save') {
            $data = [
                'client_name' => clean($_POST['client_name'] ?? ''),
                'client_company' => clean($_POST['client_company'] ?? ''),
                'client_position' => clean($_POST['client_position'] ?? ''),
                'client_image' => clean($_POST['client_image'] ?? ''),
                'testimonial' => clean($_POST['testimonial'] ?? ''),
                'results' => clean($_POST['results'] ?? ''),
                'service_type' => clean($_POST['service_type'] ?? ''),
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];

            if ($id) {
                db()->update('success_stories', $data, 'id = ?', ['id' => $id]);
                $success = 'تم تحديث القصة';
            } else {
                db()->insert('success_stories', $data);
                $success = 'تم إضافة القصة';
            }
        }

        if ($action === 'delete' && $id) {
            db()->delete('success_stories', 'id = ?', ['id' => $id]);
            $success = 'تم حذف القصة';
        }
    }
}

$stories = db()->fetchAll("SELECT * FROM success_stories ORDER BY sort_order, created_at DESC");
$editStory = null;
if (isset($_GET['edit'])) {
    $editStory = db()->fetchOne("SELECT * FROM success_stories WHERE id = ?", [(int)$_GET['edit']]);
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>قصص النجاح</h1>
        <p>إدارة شهادات العملاء وقصص النجاح</p>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 1.5rem;">
    <div class="card">
        <div class="card-header"><h3>القصص</h3></div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($stories)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>العميل</th>
                            <th>الشركة</th>
                            <th>الخدمة</th>
                            <th>الحالة</th>
                            <th style="width: 100px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stories as $story): ?>
                        <tr>
                            <td style="font-weight: 500;">
                                <?= e($story['client_name']) ?>
                                <?php if ($story['is_featured']): ?>
                                <span class="badge badge-warning">مميز</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($story['client_company'] ?: '-') ?></td>
                            <td><?= e($story['service_type'] ?: '-') ?></td>
                            <td>
                                <span class="badge badge-<?= $story['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $story['is_active'] ? 'مفعّل' : 'معطّل' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?edit=<?= $story['id'] ?>" class="btn btn-sm btn-icon btn-secondary"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('متأكد؟');">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $story['id'] ?>">
                                        <button class="btn btn-sm btn-icon btn-danger"><i class="fas fa-trash"></i></button>
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
                <i class="fas fa-trophy"></i>
                <h3>لا توجد قصص</h3>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><?= $editStory ? 'تعديل' : 'إضافة قصة' ?></h3>
            <?php if ($editStory): ?><a href="stories.php" class="btn btn-sm btn-secondary">إلغاء</a><?php endif; ?>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editStory['id'] ?? '' ?>">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">اسم العميل <span class="required">*</span></label>
                    <input type="text" name="client_name" class="form-control" value="<?= e($editStory['client_name'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">الشركة</label>
                        <input type="text" name="client_company" class="form-control" value="<?= e($editStory['client_company'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">المنصب</label>
                        <input type="text" name="client_position" class="form-control" value="<?= e($editStory['client_position'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">الشهادة</label>
                    <textarea name="testimonial" class="form-control" rows="3"><?= e($editStory['testimonial'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">النتائج</label>
                    <textarea name="results" class="form-control" rows="2"><?= e($editStory['results'] ?? '') ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">الخدمة</label>
                        <input type="text" name="service_type" class="form-control" value="<?= e($editStory['service_type'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= e($editStory['sort_order'] ?? 0) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?= ($editStory['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <span>مفعّل</span>
                    </label>
                </div>
                <div class="form-group mb-0">
                    <label class="form-check">
                        <input type="checkbox" name="is_featured" value="1" <?= ($editStory['is_featured'] ?? 0) ? 'checked' : '' ?>>
                        <span>مميز</span>
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> حفظ</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

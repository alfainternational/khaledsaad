<?php
/**
 * Pricing Plans Management
 * إدارة باقات الأسعار
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'باقات الأسعار';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'save') {
            $data = [
                'name' => clean($_POST['name'] ?? ''),
                'description' => clean($_POST['description'] ?? ''),
                'price' => (float)($_POST['price'] ?? 0),
                'billing_period' => clean($_POST['billing_period'] ?? 'monthly'),
                'features' => clean($_POST['features'] ?? ''),
                'is_popular' => isset($_POST['is_popular']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
                'cta_text' => clean($_POST['cta_text'] ?? 'ابدأ الآن'),
            ];

            if ($id) {
                db()->update('pricing_plans', $data, 'id = ?', ['id' => $id]);
                $success = 'تم تحديث الباقة';
            } else {
                db()->insert('pricing_plans', $data);
                $success = 'تم إضافة الباقة';
            }
        }

        if ($action === 'delete' && $id) {
            db()->delete('pricing_plans', 'id = ?', ['id' => $id]);
            $success = 'تم حذف الباقة';
        }
    }
}

$plans = db()->fetchAll("SELECT * FROM pricing_plans ORDER BY sort_order, price");
$editPlan = isset($_GET['edit']) ? db()->fetchOne("SELECT * FROM pricing_plans WHERE id = ?", [(int)$_GET['edit']]) : null;

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>باقات الأسعار</h1>
        <p>إدارة خطط الأسعار والباقات</p>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 400px; gap: 1.5rem;">
    <div class="card">
        <div class="card-header"><h3>الباقات</h3></div>
        <div class="card-body" style="padding: 0;">
            <?php if (!empty($plans)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>الباقة</th>
                            <th>السعر</th>
                            <th>الفترة</th>
                            <th>الحالة</th>
                            <th style="width: 100px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td style="font-weight: 500;">
                                <?= e($plan['name']) ?>
                                <?php if ($plan['is_popular']): ?>
                                <span class="badge badge-warning">الأكثر طلباً</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatNumber($plan['price']) ?> ر.س</td>
                            <td><?= $plan['billing_period'] === 'monthly' ? 'شهري' : ($plan['billing_period'] === 'yearly' ? 'سنوي' : 'لمرة واحدة') ?></td>
                            <td>
                                <span class="badge badge-<?= $plan['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $plan['is_active'] ? 'مفعّل' : 'معطّل' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?edit=<?= $plan['id'] ?>" class="btn btn-sm btn-icon btn-secondary"><i class="fas fa-edit"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('متأكد؟');">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $plan['id'] ?>">
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
                <i class="fas fa-tags"></i>
                <h3>لا توجد باقات</h3>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><?= $editPlan ? 'تعديل' : 'باقة جديدة' ?></h3>
            <?php if ($editPlan): ?><a href="pricing.php" class="btn btn-sm btn-secondary">إلغاء</a><?php endif; ?>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editPlan['id'] ?? '' ?>">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">اسم الباقة <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($editPlan['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <input type="text" name="description" class="form-control" value="<?= e($editPlan['description'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">السعر</label>
                        <input type="number" name="price" class="form-control" value="<?= e($editPlan['price'] ?? '') ?>" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">فترة الفوترة</label>
                        <select name="billing_period" class="form-control">
                            <option value="monthly" <?= ($editPlan['billing_period'] ?? '') === 'monthly' ? 'selected' : '' ?>>شهري</option>
                            <option value="yearly" <?= ($editPlan['billing_period'] ?? '') === 'yearly' ? 'selected' : '' ?>>سنوي</option>
                            <option value="once" <?= ($editPlan['billing_period'] ?? '') === 'once' ? 'selected' : '' ?>>لمرة واحدة</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">المميزات (سطر لكل ميزة)</label>
                    <textarea name="features" class="form-control" rows="5" placeholder="ميزة 1&#10;ميزة 2&#10;ميزة 3"><?= e($editPlan['features'] ?? '') ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">نص الزر</label>
                        <input type="text" name="cta_text" class="form-control" value="<?= e($editPlan['cta_text'] ?? 'ابدأ الآن') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= e($editPlan['sort_order'] ?? 0) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?= ($editPlan['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <span>مفعّل</span>
                    </label>
                </div>
                <div class="form-group mb-0">
                    <label class="form-check">
                        <input type="checkbox" name="is_popular" value="1" <?= ($editPlan['is_popular'] ?? 0) ? 'checked' : '' ?>>
                        <span>الأكثر طلباً</span>
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

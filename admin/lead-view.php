<?php
/**
 * Lead View/Edit
 * عرض وتعديل بيانات العميل
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: leads.php');
    exit;
}

$lead = db()->fetchOne("SELECT * FROM leads WHERE id = ?", [$id]);
if (!$lead) {
    header('Location: leads.php');
    exit;
}

$pageTitle = 'عرض العميل - ' . $lead['full_name'];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $data = [
            'status' => clean($_POST['status'] ?? $lead['status']),
            'notes' => clean($_POST['notes'] ?? ''),
        ];

        try {
            db()->update('leads', $data, 'id = ?', ['id' => $id]);
            Security::logActivity('lead_updated', 'leads', $id);
            $success = 'تم تحديث البيانات بنجاح';
            $lead = array_merge($lead, $data);
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء التحديث';
        }
    }
}

$statusLabels = [
    'new' => ['label' => 'جديد', 'class' => 'primary'],
    'contacted' => ['label' => 'تم التواصل', 'class' => 'info'],
    'qualified' => ['label' => 'مؤهل', 'class' => 'success'],
    'proposal' => ['label' => 'عرض سعر', 'class' => 'warning'],
    'negotiation' => ['label' => 'تفاوض', 'class' => 'warning'],
    'won' => ['label' => 'مكتمل', 'class' => 'success'],
    'lost' => ['label' => 'خسارة', 'class' => 'danger'],
];

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><?= e($lead['full_name']) ?></h1>
        <p>تفاصيل العميل المحتمل</p>
    </div>
    <div class="quick-actions">
        <a href="leads.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> العودة</a>
        <a href="mailto:<?= e($lead['email']) ?>" class="btn btn-primary"><i class="fas fa-envelope"></i> مراسلة</a>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Main Info -->
    <div>
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> معلومات العميل</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">الاسم الكامل</label>
                        <p style="font-weight: 500; margin: 0;"><?= e($lead['full_name']) ?></p>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">البريد الإلكتروني</label>
                        <p style="margin: 0;"><a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></p>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">رقم الهاتف</label>
                        <p style="margin: 0;"><?= e($lead['phone'] ?: '-') ?></p>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">الشركة</label>
                        <p style="margin: 0;"><?= e($lead['company'] ?: '-') ?></p>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">حجم الشركة</label>
                        <p style="margin: 0;"><?= e($lead['company_size'] ?: '-') ?></p>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">القطاع</label>
                        <p style="margin: 0;"><?= e($lead['industry'] ?: '-') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-briefcase"></i> تفاصيل الطلب</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">الخدمة المطلوبة</label>
                        <p style="margin: 0;"><?= e($lead['service_interested'] ?: '-') ?></p>
                    </div>
                    <div>
                        <label class="text-muted" style="font-size: 0.8rem;">الميزانية</label>
                        <p style="margin: 0;"><?= e($lead['budget'] ?: '-') ?></p>
                    </div>
                </div>
                <div>
                    <label class="text-muted" style="font-size: 0.8rem;">الرسالة</label>
                    <div style="background: var(--admin-bg); padding: 1rem; border-radius: var(--admin-radius); margin-top: 0.5rem;">
                        <?= nl2br(e($lead['message'] ?: 'لا توجد رسالة')) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sticky-note"></i> ملاحظات</h3>
            </div>
            <form method="POST">
                <?= Security::csrfField() ?>
                <div class="card-body">
                    <div class="form-group mb-0">
                        <textarea name="notes" class="form-control" rows="4" placeholder="أضف ملاحظاتك هنا..."><?= e($lead['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="hidden" name="status" value="<?= e($lead['status']) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ الملاحظات</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Status Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-flag"></i> حالة العميل</h3>
            </div>
            <form method="POST">
                <?= Security::csrfField() ?>
                <input type="hidden" name="notes" value="<?= e($lead['notes'] ?? '') ?>">
                <div class="card-body">
                    <div class="form-group mb-0">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($statusLabels as $key => $val): ?>
                            <option value="<?= $key ?>" <?= $lead['status'] === $key ? 'selected' : '' ?>><?= $val['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Timeline -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> الجدول الزمني</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; gap: 0.75rem;">
                        <div style="width: 32px; height: 32px; background: rgba(37, 99, 235, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-plus" style="font-size: 0.75rem; color: var(--admin-primary);"></i>
                        </div>
                        <div>
                            <p style="margin: 0; font-size: 0.875rem;">تم إنشاء الطلب</p>
                            <span style="font-size: 0.75rem; color: var(--admin-text-muted);"><?= formatDate($lead['created_at']) ?></span>
                        </div>
                    </div>
                    <?php if ($lead['updated_at'] && $lead['updated_at'] !== $lead['created_at']): ?>
                    <div style="display: flex; gap: 0.75rem;">
                        <div style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-edit" style="font-size: 0.75rem; color: var(--admin-success);"></i>
                        </div>
                        <div>
                            <p style="margin: 0; font-size: 0.875rem;">آخر تحديث</p>
                            <span style="font-size: 0.75rem; color: var(--admin-text-muted);"><?= formatDate($lead['updated_at']) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Client Settings
 * إعدادات العميل
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'الإعدادات';

$client = db()->fetchOne("SELECT * FROM clients WHERE id = ?", [$_SESSION['client_id']]);
$preferences = json_decode($client['preferences'] ?? '{}', true) ?: [];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'preferences') {
            $preferences = [
                'email_notifications' => isset($_POST['email_notifications']),
                'sms_notifications' => isset($_POST['sms_notifications']),
                'newsletter' => isset($_POST['newsletter']),
                'consultation_reminders' => isset($_POST['consultation_reminders']),
                'language' => $_POST['language'] ?? 'ar',
            ];

            db()->update('clients', [
                'preferences' => json_encode($preferences, JSON_UNESCAPED_UNICODE)
            ], 'id = ?', ['id' => $_SESSION['client_id']]);

            $success = 'تم حفظ الإعدادات بنجاح';
        }

        if ($action === 'delete_account') {
            $password = $_POST['confirm_password'] ?? '';

            if (!Security::verifyPassword($password, $client['password'])) {
                $error = 'كلمة المرور غير صحيحة';
            } else {
                // Soft delete or hard delete based on your needs
                db()->update('clients', ['is_active' => 0], 'id = ?', ['id' => $_SESSION['client_id']]);

                // Clear session and redirect
                session_destroy();
                header('Location: login.php?deleted=1');
                exit;
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>الإعدادات</h1>
        <p>تخصيص تجربتك وإدارة إعدادات حسابك</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Notifications -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> الإشعارات</h3>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="preferences">
            <div class="card-body">
                <div class="form-group">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                        <div>
                            <div style="font-weight: 500;">إشعارات البريد الإلكتروني</div>
                            <div style="font-size: 0.8rem; color: var(--dash-text-muted);">استلام الإشعارات عبر البريد</div>
                        </div>
                        <input type="checkbox" name="email_notifications" value="1" <?= ($preferences['email_notifications'] ?? true) ? 'checked' : '' ?>>
                    </label>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                        <div>
                            <div style="font-weight: 500;">إشعارات الرسائل النصية</div>
                            <div style="font-size: 0.8rem; color: var(--dash-text-muted);">استلام الإشعارات عبر SMS</div>
                        </div>
                        <input type="checkbox" name="sms_notifications" value="1" <?= ($preferences['sms_notifications'] ?? false) ? 'checked' : '' ?>>
                    </label>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                        <div>
                            <div style="font-weight: 500;">النشرة الإخبارية</div>
                            <div style="font-size: 0.8rem; color: var(--dash-text-muted);">استلام آخر المقالات والنصائح</div>
                        </div>
                        <input type="checkbox" name="newsletter" value="1" <?= ($preferences['newsletter'] ?? true) ? 'checked' : '' ?>>
                    </label>
                </div>

                <div class="form-group mb-0">
                    <label style="display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                        <div>
                            <div style="font-weight: 500;">تذكيرات الاستشارات</div>
                            <div style="font-size: 0.8rem; color: var(--dash-text-muted);">تذكير قبل موعد الاستشارة</div>
                        </div>
                        <input type="checkbox" name="consultation_reminders" value="1" <?= ($preferences['consultation_reminders'] ?? true) ? 'checked' : '' ?>>
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>

    <!-- Other Settings -->
    <div>
        <!-- Language -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3><i class="fas fa-globe"></i> اللغة والمنطقة</h3>
            </div>
            <form method="POST">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="preferences">
                <div class="card-body">
                    <div class="form-group mb-0">
                        <label class="form-label">لغة الواجهة</label>
                        <select name="language" class="form-control">
                            <option value="ar" <?= ($preferences['language'] ?? 'ar') === 'ar' ? 'selected' : '' ?>>العربية</option>
                            <option value="en" <?= ($preferences['language'] ?? 'ar') === 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-secondary btn-block">
                        <i class="fas fa-save"></i> حفظ
                    </button>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="card" style="border: 1px solid var(--dash-danger);">
            <div class="card-header" style="background: rgba(239, 68, 68, 0.1);">
                <h3 style="color: var(--dash-danger);"><i class="fas fa-exclamation-triangle"></i> منطقة الخطر</h3>
            </div>
            <div class="card-body">
                <p style="color: var(--dash-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                    حذف حسابك سيؤدي إلى إزالة جميع بياناتك بشكل نهائي. هذا الإجراء لا يمكن التراجع عنه.
                </p>
                <button class="btn btn-danger btn-block" onclick="showDeleteModal()">
                    <i class="fas fa-trash"></i> حذف الحساب
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="background: rgba(239, 68, 68, 0.1);">
            <h3 style="color: var(--dash-danger);"><i class="fas fa-exclamation-triangle"></i> تأكيد حذف الحساب</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="delete_account">
            <div class="modal-body">
                <div class="alert alert-danger" style="margin-bottom: 1rem;">
                    <strong>تحذير:</strong> سيتم حذف جميع بياناتك بما في ذلك الاستشارات ونتائج التشخيص والمحفوظات.
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">أدخل كلمة المرور للتأكيد</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="كلمة المرور الحالية">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">إلغاء</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> حذف الحساب نهائياً
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: var(--dash-card-bg);
    border-radius: var(--dash-radius);
    width: 90%;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--dash-border);
}
.modal-header h3 { margin: 0; }
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--dash-text-muted);
}
.modal-body { padding: 1.5rem; }
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--dash-border);
}

/* Toggle Switch Style */
input[type="checkbox"] {
    width: 50px;
    height: 26px;
    appearance: none;
    background: var(--dash-border);
    border-radius: 13px;
    position: relative;
    cursor: pointer;
    transition: background 0.3s;
}
input[type="checkbox"]::before {
    content: '';
    position: absolute;
    width: 22px;
    height: 22px;
    background: white;
    border-radius: 50%;
    top: 2px;
    right: 2px;
    transition: right 0.3s;
}
input[type="checkbox"]:checked {
    background: var(--dash-primary);
}
input[type="checkbox"]:checked::before {
    right: 26px;
}
</style>

<script>
function showDeleteModal() {
    document.getElementById('deleteModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.remove('active');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

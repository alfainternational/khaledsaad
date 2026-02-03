<?php
/**
 * Client Profile
 * الملف الشخصي للعميل
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'الملف الشخصي';

$client = db()->fetchOne("SELECT * FROM clients WHERE id = ?", [$_SESSION['client_id']]);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $action = $_POST['action'] ?? 'profile';

        if ($action === 'profile') {
            $fullName = clean($_POST['full_name'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $phone = clean($_POST['phone'] ?? '');
            $company = clean($_POST['company'] ?? '');
            $bio = clean($_POST['bio'] ?? '');

            if (!$fullName || !$email) {
                $error = 'الاسم والبريد الإلكتروني مطلوبان';
            } else {
                // Check email uniqueness
                $exists = db()->fetchOne("SELECT id FROM clients WHERE email = ? AND id != ?", [$email, $_SESSION['client_id']]);
                if ($exists) {
                    $error = 'البريد الإلكتروني مستخدم من قبل';
                } else {
                    db()->update('clients', [
                        'full_name' => $fullName,
                        'email' => $email,
                        'phone' => $phone ?: null,
                        'company' => $company ?: null,
                        'bio' => $bio ?: null
                    ], 'id = ?', ['id' => $_SESSION['client_id']]);

                    $_SESSION['client_name'] = $fullName;
                    $_SESSION['client_email'] = $email;
                    $success = 'تم تحديث الملف الشخصي بنجاح';

                    // Refresh client data
                    $client = db()->fetchOne("SELECT * FROM clients WHERE id = ?", [$_SESSION['client_id']]);
                }
            }
        }

        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!$current || !$new || !$confirm) {
                $error = 'جميع حقول كلمة المرور مطلوبة';
            } elseif ($new !== $confirm) {
                $error = 'كلمة المرور الجديدة غير متطابقة';
            } elseif (strlen($new) < PASSWORD_MIN_LENGTH) {
                $error = 'كلمة المرور يجب أن تكون ' . PASSWORD_MIN_LENGTH . ' أحرف على الأقل';
            } elseif (!Security::verifyPassword($current, $client['password'])) {
                $error = 'كلمة المرور الحالية غير صحيحة';
            } else {
                db()->update('clients', [
                    'password' => Security::hashPassword($new)
                ], 'id = ?', ['id' => $_SESSION['client_id']]);

                $success = 'تم تغيير كلمة المرور بنجاح';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>الملف الشخصي</h1>
        <p>قم بتحديث معلوماتك الشخصية وكلمة المرور</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user"></i> المعلومات الشخصية</h3>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="profile">
            <div class="card-body">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="width: 100px; height: 100px; background: var(--dash-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; margin: 0 auto;">
                        <?= mb_substr($client['full_name'], 0, 1) ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($client['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="<?= e($client['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" value="<?= e($client['phone'] ?? '') ?>" placeholder="+966...">
                </div>

                <div class="form-group">
                    <label class="form-label">الشركة</label>
                    <input type="text" name="company" class="form-control" value="<?= e($client['company'] ?? '') ?>" placeholder="اسم الشركة (اختياري)">
                </div>

                <div class="form-group">
                    <label class="form-label">نبذة عنك</label>
                    <textarea name="bio" class="form-control" rows="3" placeholder="أخبرنا عن نفسك أو عن عملك..."><?= e($client['bio'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </div>
        </form>
    </div>

    <!-- Password Change -->
    <div>
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> تغيير كلمة المرور</h3>
            </div>
            <form method="POST">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="password">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">كلمة المرور الحالية</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" name="new_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                        <div class="form-text"><?= PASSWORD_MIN_LENGTH ?> أحرف على الأقل</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-key"></i> تغيير كلمة المرور
                    </button>
                </div>
            </form>
        </div>

        <!-- Account Info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> معلومات الحساب</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid var(--dash-border);">
                        <span style="color: var(--dash-text-muted);">تاريخ الانضمام</span>
                        <span style="font-weight: 500;"><?= formatDate($client['created_at']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-bottom: 0.75rem; border-bottom: 1px solid var(--dash-border);">
                        <span style="color: var(--dash-text-muted);">آخر تسجيل دخول</span>
                        <span style="font-weight: 500;"><?= $client['last_login'] ? formatDate($client['last_login']) : '-' ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--dash-text-muted);">حالة الحساب</span>
                        <span class="badge badge-<?= $client['is_active'] ? 'success' : 'danger' ?>">
                            <?= $client['is_active'] ? 'نشط' : 'معطل' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

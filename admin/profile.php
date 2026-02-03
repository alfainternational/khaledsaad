<?php
/**
 * Admin Profile
 * الملف الشخصي
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$pageTitle = 'الملف الشخصي';

$user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['admin_id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $action = $_POST['action'] ?? 'profile';

        if ($action === 'profile') {
            $fullName = clean($_POST['full_name'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

            if (!$fullName || !$email) {
                $error = 'جميع الحقول مطلوبة';
            } else {
                $exists = db()->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $_SESSION['admin_id']]);
                if ($exists) {
                    $error = 'البريد مستخدم من قبل';
                } else {
                    db()->update('users', ['full_name' => $fullName, 'email' => $email], 'id = ?', ['id' => $_SESSION['admin_id']]);
                    $_SESSION['admin_name'] = $fullName;
                    $success = 'تم تحديث الملف الشخصي';
                    $user['full_name'] = $fullName;
                    $user['email'] = $email;
                }
            }
        }

        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!$current || !$new || !$confirm) {
                $error = 'جميع الحقول مطلوبة';
            } elseif ($new !== $confirm) {
                $error = 'كلمة المرور الجديدة غير متطابقة';
            } elseif (strlen($new) < 6) {
                $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
            } elseif (!Security::verifyPassword($current, $user['password'])) {
                $error = 'كلمة المرور الحالية غير صحيحة';
            } else {
                db()->update('users', ['password' => Security::hashPassword($new)], 'id = ?', ['id' => $_SESSION['admin_id']]);
                Security::logActivity('password_changed', 'users', $_SESSION['admin_id']);
                $success = 'تم تغيير كلمة المرور';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>الملف الشخصي</h1>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user"></i> معلومات الحساب</h3>
        </div>
        <form method="POST">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="profile">
            <div class="card-body">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="width: 80px; height: 80px; background: var(--admin-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; margin: 0 auto;">
                        <?= mb_substr($user['full_name'], 0, 1) ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> حفظ التغييرات</button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card">
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
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-key"></i> تغيير كلمة المرور</button>
            </div>
        </form>
    </div>
</div>

<!-- Account Info -->
<div class="card mt-4">
    <div class="card-header">
        <h3><i class="fas fa-info-circle"></i> معلومات الحساب</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
            <div>
                <label class="text-muted" style="font-size: 0.8rem;">الدور</label>
                <p style="margin: 0; font-weight: 500;"><?= $user['role'] === 'admin' ? 'مدير' : 'محرر' ?></p>
            </div>
            <div>
                <label class="text-muted" style="font-size: 0.8rem;">تاريخ الإنشاء</label>
                <p style="margin: 0;"><?= formatDate($user['created_at']) ?></p>
            </div>
            <div>
                <label class="text-muted" style="font-size: 0.8rem;">آخر تسجيل دخول</label>
                <p style="margin: 0;"><?= $user['last_login'] ? formatDate($user['last_login']) : '-' ?></p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

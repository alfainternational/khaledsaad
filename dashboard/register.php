<?php
/**
 * Client Registration
 * تسجيل عميل جديد
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

// Redirect if already logged in
if (isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$formData = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'company' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة. يرجى تحديث الصفحة.';
    } else {
        $formData['full_name'] = clean($_POST['full_name'] ?? '');
        $formData['email'] = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $formData['phone'] = clean($_POST['phone'] ?? '');
        $formData['company'] = clean($_POST['company'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (!$formData['full_name'] || !$formData['email'] || !$password) {
            $error = 'يرجى ملء جميع الحقول المطلوبة';
        } elseif (mb_strlen($formData['full_name']) < 3) {
            $error = 'الاسم يجب أن يكون 3 أحرف على الأقل';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'كلمة المرور يجب أن تكون ' . PASSWORD_MIN_LENGTH . ' أحرف على الأقل';
        } elseif ($password !== $confirmPassword) {
            $error = 'كلمة المرور غير متطابقة';
        } else {
            try {
                // Check if email exists
                $exists = db()->fetchOne("SELECT id FROM clients WHERE email = ?", [$formData['email']]);
                if ($exists) {
                    $error = 'البريد الإلكتروني مستخدم بالفعل';
                } else {
                    // Create client
                    $verificationToken = bin2hex(random_bytes(32));
                    $clientId = db()->insert('clients', [
                        'full_name' => $formData['full_name'],
                        'email' => $formData['email'],
                        'phone' => $formData['phone'] ?: null,
                        'company' => $formData['company'] ?: null,
                        'password' => Security::hashPassword($password),
                        'verification_token' => $verificationToken,
                        'is_verified' => 1, // Auto verify for now
                        'is_active' => 1
                    ]);

                    // Auto login
                    $_SESSION['client_id'] = $clientId;
                    $_SESSION['client_name'] = $formData['full_name'];
                    $_SESSION['client_email'] = $formData['email'];

                    header('Location: index.php?welcome=1');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - <?= SITE_NAME ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
</head>
<body class="dashboard-body">
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo" style="width: 60px; height: 60px; background: var(--dash-primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.5rem; margin: 0 auto 1rem;">خ</div>
                <h1>إنشاء حساب جديد</h1>
                <p>انضم إلينا للوصول لخدماتنا ومتابعة استشاراتك</p>
            </div>

            <div class="auth-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <?= Security::csrfField() ?>

                    <div class="form-group">
                        <label class="form-label">الاسم الكامل <span style="color: var(--dash-danger);">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($formData['full_name']) ?>" placeholder="أدخل اسمك الكامل" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني <span style="color: var(--dash-danger);">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= e($formData['email']) ?>" placeholder="example@email.com" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" name="phone" class="form-control" value="<?= e($formData['phone']) ?>" placeholder="+966...">
                        </div>

                        <div class="form-group">
                            <label class="form-label">الشركة</label>
                            <input type="text" name="company" class="form-control" value="<?= e($formData['company']) ?>" placeholder="اسم الشركة (اختياري)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">كلمة المرور <span style="color: var(--dash-danger);">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="<?= PASSWORD_MIN_LENGTH ?> أحرف على الأقل" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور <span style="color: var(--dash-danger);">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="أعد إدخال كلمة المرور" required>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="terms" required style="margin-top: 0.25rem;">
                            <span style="font-size: 0.875rem;">أوافق على <a href="<?= url('terms') ?>" target="_blank" style="color: var(--dash-primary);">شروط الاستخدام</a> و<a href="<?= url('privacy') ?>" target="_blank" style="color: var(--dash-primary);">سياسة الخصوصية</a></span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-user-plus"></i>
                        إنشاء الحساب
                    </button>
                </form>
            </div>

            <div class="auth-footer">
                <p>لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a></p>
            </div>
        </div>
    </div>
</body>
</html>

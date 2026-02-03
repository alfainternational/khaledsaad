<?php
/**
 * Client Login
 * تسجيل دخول العميل
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

// Redirect if already logged in
if (isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة. يرجى تحديث الصفحة.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!$email || !$password) {
            $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور';
        } else {
            try {
                $client = db()->fetchOne("SELECT * FROM clients WHERE email = ?", [$email]);

                if (!$client) {
                    $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
                } elseif (!$client['is_active']) {
                    $error = 'تم تعطيل هذا الحساب. تواصل معنا للمساعدة.';
                } elseif ($client['locked_until'] && strtotime($client['locked_until']) > time()) {
                    $remaining = ceil((strtotime($client['locked_until']) - time()) / 60);
                    $error = "تم تجميد الحساب مؤقتاً. حاول بعد {$remaining} دقيقة.";
                } elseif (!Security::verifyPassword($password, $client['password'])) {
                    // Increment login attempts
                    $attempts = $client['login_attempts'] + 1;
                    $lockedUntil = null;

                    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                        $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
                        $attempts = 0;
                    }

                    db()->update('clients', [
                        'login_attempts' => $attempts,
                        'locked_until' => $lockedUntil
                    ], 'id = ?', ['id' => $client['id']]);

                    $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
                } else {
                    // Successful login
                    $_SESSION['client_id'] = $client['id'];
                    $_SESSION['client_name'] = $client['full_name'];
                    $_SESSION['client_email'] = $client['email'];

                    // Update login info
                    db()->update('clients', [
                        'last_login' => date('Y-m-d H:i:s'),
                        'login_attempts' => 0,
                        'locked_until' => null
                    ], 'id = ?', ['id' => $client['id']]);

                    // Redirect
                    $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                }
            } catch (Exception $e) {
                $error = 'حدث خطأ في تسجيل الدخول. حاول مرة أخرى.';
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
    <title>تسجيل الدخول - <?= SITE_NAME ?></title>

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
                <h1>مرحباً بعودتك</h1>
                <p>سجّل دخولك للوصول إلى لوحة التحكم</p>
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
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="example@email.com" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">كلمة المرور</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="remember" value="1">
                            <span style="font-size: 0.875rem;">تذكرني</span>
                        </label>
                        <a href="forgot-password.php" style="font-size: 0.875rem; color: var(--dash-primary); text-decoration: none;">نسيت كلمة المرور؟</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </button>
                </form>
            </div>

            <div class="auth-footer">
                <p>ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a></p>
            </div>
        </div>
    </div>
</body>
</html>

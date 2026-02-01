<?php
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            try {
                $user = db()->fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
                
                if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $error = 'الحساب مقفل مؤقتاً. حاول لاحقاً.';
                } elseif ($user && Security::verifyPassword($password, $user['password'])) {
                    db()->update('users', [
                        'last_login' => date('Y-m-d H:i:s'),
                        'login_attempts' => 0,
                        'locked_until' => null
                    ], 'id = ?', ['id' => $user['id']]);

                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['admin_name'] = $user['full_name'];

                    Security::logActivity('admin_login', 'users', $user['id']);
                    header('Location: index.php');
                    exit;
                } else {
                    if ($user) {
                        $attempts = $user['login_attempts'] + 1;
                        $locked = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCKOUT_TIME) : null;
                        db()->update('users', ['login_attempts' => $attempts, 'locked_until' => $locked], 'id = ?', ['id' => $user['id']]);
                    }
                    $error = 'بيانات الدخول غير صحيحة';
                }
            } catch (Exception $e) {
                $error = 'حدث خطأ. يرجى المحاولة لاحقاً.';
            }
        } else {
            $error = 'يرجى إدخال البريد وكلمة المرور';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - لوحة التحكم</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <style>
        body { background: var(--bg-secondary); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: var(--bg-card); padding: var(--space-8); border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); width: 100%; max-width: 400px; }
        .login-logo { text-align: center; margin-bottom: var(--space-6); }
        .login-logo .logo-text { font-size: var(--font-size-2xl); font-weight: 800; }
        .login-logo .logo-tagline { font-size: var(--font-size-sm); color: var(--primary); }
        .error-msg { background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: var(--space-3); border-radius: var(--radius); margin-bottom: var(--space-4); text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <span class="logo-text">خالد سعد</span>
            <span class="logo-tagline">لوحة التحكم</span>
        </div>

        <?php if ($error): ?>
        <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= Security::csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </button>
        </form>

        <p style="text-align: center; margin-top: var(--space-6); color: var(--text-muted); font-size: var(--font-size-sm);">
            <a href="<?= url('') ?>"><i class="fas fa-arrow-right"></i> العودة للموقع</a>
        </p>
    </div>
</body>
</html>

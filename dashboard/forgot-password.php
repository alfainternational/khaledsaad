<?php
/**
 * Forgot Password
 * استعادة كلمة المرور
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'جلسة غير صالحة. يرجى تحديث الصفحة.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $error = 'يرجى إدخال بريد إلكتروني صالح';
        } else {
            try {
                $client = db()->fetchOne("SELECT * FROM clients WHERE email = ?", [$email]);

                if ($client) {
                    // Generate reset token
                    $resetToken = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                    db()->update('clients', [
                        'reset_token' => $resetToken,
                        'reset_token_expires' => $expires
                    ], 'id = ?', ['id' => $client['id']]);

                    // In production, send email here
                    // For now, just show success message
                }

                // Always show success to prevent email enumeration
                $success = 'إذا كان البريد الإلكتروني مسجلاً لدينا، ستصلك رسالة تحتوي على رابط إعادة تعيين كلمة المرور.';
            } catch (Exception $e) {
                $error = 'حدث خطأ. حاول مرة أخرى.';
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
    <title>استعادة كلمة المرور - <?= SITE_NAME ?></title>

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
                <h1>استعادة كلمة المرور</h1>
                <p>أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة التعيين</p>
            </div>

            <div class="auth-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= e($success) ?>
                </div>
                <?php else: ?>
                <form method="POST">
                    <?= Security::csrfField() ?>

                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" placeholder="example@email.com" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-paper-plane"></i>
                        إرسال رابط الاستعادة
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <div class="auth-footer">
                <p><a href="login.php"><i class="fas fa-arrow-right"></i> العودة لتسجيل الدخول</a></p>
            </div>
        </div>
    </div>
</body>
</html>

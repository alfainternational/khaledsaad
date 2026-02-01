<?php
/**
 * API للاشتراك في النشرة الإخبارية
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('طريقة الطلب غير مسموحة', [], 405);
}

if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    errorResponse('جلسة غير صالحة.', [], 403);
}

if (!validateHoneypot()) {
    errorResponse('تم اكتشاف نشاط مشبوه.', [], 400);
}

$ip = Security::getClientIP();
if (!Security::checkRateLimit($ip, 'newsletter', 3, 3600)) {
    errorResponse('تم تجاوز الحد المسموح.', [], 429);
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) {
    errorResponse('البريد الإلكتروني غير صالح');
}

try {
    $existing = db()->fetchOne("SELECT * FROM newsletter_subscribers WHERE email = ?", [$email]);
    
    if ($existing) {
        if ($existing['status'] === 'unsubscribed') {
            db()->update('newsletter_subscribers', [
                'status' => 'confirmed',
                'unsubscribed_at' => null
            ], 'id = ?', ['id' => $existing['id']]);
            successResponse('تم إعادة تفعيل اشتراكك بنجاح!');
        } else {
            successResponse('أنت مشترك بالفعل في نشرتنا الإخبارية.');
        }
    } else {
        $token = bin2hex(random_bytes(32));
        db()->insert('newsletter_subscribers', [
            'email' => $email,
            'status' => 'confirmed',
            'confirmation_token' => $token,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'source' => 'website',
            'ip_address' => $ip
        ]);
        successResponse('تم الاشتراك بنجاح! شكراً لانضمامك.');
    }
} catch (Exception $e) {
    error_log("Newsletter error: " . $e->getMessage());
    errorResponse('حدث خطأ. يرجى المحاولة مرة أخرى.', [], 500);
}

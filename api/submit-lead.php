<?php
/**
 * API لإرسال نموذج العملاء المحتملين
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('طريقة الطلب غير مسموحة', [], 405);
}

// التحقق من CSRF
if (!Security::validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    errorResponse('جلسة غير صالحة. يرجى تحديث الصفحة.', [], 403);
}

// التحقق من Honeypot
if (!validateHoneypot()) {
    errorResponse('تم اكتشاف نشاط مشبوه.', [], 400);
}

// التحقق من Rate Limiting
$ip = Security::getClientIP();
if (!Security::checkRateLimit($ip, 'lead_form', 5, 3600)) {
    errorResponse('تم تجاوز الحد المسموح. يرجى المحاولة لاحقاً.', [], 429);
}

// التحقق من البيانات
$errors = [];

$fullName = clean($_POST['full_name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = clean($_POST['phone'] ?? '');
$company = clean($_POST['company'] ?? '');
$companySize = clean($_POST['company_size'] ?? '');
$industry = clean($_POST['industry'] ?? '');
$serviceInterested = clean($_POST['service_interested'] ?? '');
$budget = clean($_POST['budget'] ?? '');
$message = clean($_POST['message'] ?? '');

if (empty($fullName) || mb_strlen($fullName) < 3) {
    $errors['full_name'] = 'الاسم مطلوب (3 أحرف على الأقل)';
}

if (!$email) {
    $errors['email'] = 'البريد الإلكتروني غير صالح';
}

if (empty($message) || mb_strlen($message) < 20) {
    $errors['message'] = 'الرسالة مطلوبة (20 حرف على الأقل)';
}

if (!empty($errors)) {
    errorResponse('يرجى تصحيح الأخطاء', $errors);
}

// حفظ البيانات
try {
    $leadId = db()->insert('leads', [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'company_size' => $companySize ?: null,
        'industry' => $industry,
        'service_interested' => $serviceInterested,
        'budget' => $budget ?: null,
        'message' => $message,
        'source' => 'website',
        'utm_source' => $_POST['utm_source'] ?? null,
        'utm_medium' => $_POST['utm_medium'] ?? null,
        'utm_campaign' => $_POST['utm_campaign'] ?? null,
        'status' => 'new',
        'priority' => 'medium',
        'ip_address' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    Security::logActivity('lead_created', 'leads', $leadId);
    successResponse('تم إرسال طلبك بنجاح! سنتواصل معك قريباً.', ['id' => $leadId]);

} catch (Exception $e) {
    error_log("Lead submission error: " . $e->getMessage());
    errorResponse('حدث خطأ. يرجى المحاولة مرة أخرى.', [], 500);
}

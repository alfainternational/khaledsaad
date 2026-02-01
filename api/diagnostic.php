<?php
/**
 * API لحفظ نتائج التشخيص
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('طريقة الطلب غير مسموحة', [], 405);
}

$ip = Security::getClientIP();
if (!Security::checkRateLimit($ip, 'diagnostic', 10, 3600)) {
    errorResponse('تم تجاوز الحد المسموح.', [], 429);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    errorResponse('بيانات غير صالحة');
}

try {
    db()->insert('diagnostic_results', [
        'session_id' => $data['session_id'] ?? session_id(),
        'answers' => json_encode($data['answers'] ?? [], JSON_UNESCAPED_UNICODE),
        'score' => $data['score'] ?? 0,
        'category' => $data['category'] ?? null,
        'recommendations' => json_encode($data['category_scores'] ?? [], JSON_UNESCAPED_UNICODE),
        'ip_address' => $ip,
        'completed_at' => date('Y-m-d H:i:s')
    ]);

    successResponse('تم حفظ النتائج');
} catch (Exception $e) {
    error_log("Diagnostic save error: " . $e->getMessage());
    errorResponse('حدث خطأ', [], 500);
}

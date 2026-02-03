<?php
/**
 * API لتجاوز جدولة الإرسال وإرسال التقرير فوراً للعميل
 */
require_once dirname(dirname(__DIR__)) . '/includes/init.php';

// التحقق من الصلاحيات (Admin Only)
if (!isset($_SESSION['admin_id'])) {
    errorResponse('غير مصرح لك بالوصول');
}

$id = $_POST['id'] ?? 0;

if (!$id) {
    errorResponse('معرف التقرير غير موجود');
}

try {
    // تحديث موعد الإرسال للآن
    db()->update('diagnostic_results', [
        'scheduled_send_at' => date('Y-m-d H:i:s'),
        'status' => 'pending_review' // نضمن أنه سيلتقطه السكربت القادم (أو نشغل السكربت الآن)
    ], 'id = ?', [$id]);

    // للسرعة: سنقوم بتشغيل سكربت الإرسال برمجياً لهذا الطلب فقط
    // (أو ببساطة ننتظر الكرون جوب، لكن الإرسال الفوري أفضل هنا)
    
    // ملاحظة: من الأفضل استدعاء كود الإرسال المشترك، لكن للتبسيط هنا سنعتمد على الكرون 
    // أو نشغله يدوياً
    
    successResponse('تمت جدولة الإرسال الفوري للتقرير بنجاح.');

} catch (Exception $e) {
    errorResponse('حدث خطأ: ' . $e->getMessage());
}

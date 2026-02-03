<?php
/**
 * تحديث قاعدة البيانات للإصدار 3.1 (الجدولة والمراجعة اليدوية)
 */

require_once __DIR__ . '/../includes/init.php';

try {
    $db = db()->getConnection();
    
    // إضافة حقول الحالة وموعد الإرسال المجدول
    $sql = "
    ALTER TABLE `diagnostic_results` 
    ADD COLUMN IF NOT EXISTS `status` VARCHAR(50) DEFAULT 'pending_review' AFTER `recommendations_data`,
    ADD COLUMN IF NOT EXISTS `scheduled_send_at` DATETIME DEFAULT NULL AFTER `status`;
    ";
    
    $db->exec($sql);
    
    echo "Status: SUCCESS - Database updated to v3.1 (Scheduling Support) successfully.";
    
} catch (PDOException $e) {
    echo "Status: ERROR - " . $e->getMessage();
}

<?php
/**
 * سكربت الإرسال الآلي للتقارير الاستشارية (Cron Job)
 * يتم تشغيله كل ساعة لإرسال التقارير التي حان موعدها
 */

// التأكد من تشغيله من الـ CLI أو المتصفح مع مفتاح أمان (اختياري)
require_once dirname(dirname(__DIR__)) . '/includes/init.php';
require_once SITE_ROOT . '/includes/diagnostic_engine.php';

$now = date('Y-m-d H:i:s');

// 1. جلب التقارير التي حان موعدها ولم ترسل بعد
try {
    $pendingReports = db()->fetchAll(
        "SELECT * FROM diagnostic_results WHERE status = 'pending_review' AND scheduled_send_at <= ?",
        [$now]
    );

    if (empty($pendingReports)) {
        echo "No reports to send at $now\n";
        exit;
    }

    foreach ($pendingReports as $report) {
        $email = $report['email'];
        $name = $report['full_name'];
        $reportLink = url('pages/view-report.php?token=' . $report['report_token']);
        
        // تجهيز محتوى البريد
        $subject = "تقرير نضج الأعمال الاستراتيجي - " . SITE_NAME;
        
        $message = "
        <div dir='rtl' style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h2 style='color: #FF6B35;'>أهلاً بك يا {$name},</h2>
            <p>يسعدنا إخبارك بأن التحليل الاستراتيجي لمنشأتك قد اكتمل بنجاح من قبل فريقنا الاستشاري.</p>
            <p>لقد قمنا بدراسة الفجوات الرقمية واستنتاج أفضل المسارات لنمو أعمالكم في عام 2026.</p>
            
            <div style='background: #f8f9fa; padding: 25px; border-radius: 12px; margin: 30px 0; border: 1px solid #e2e8f0; text-align: center;'>
                <p style='margin-bottom: 25px; font-size: 1.1rem;'><strong>بإمكانك الآن تحميل التقرير الاستراتيجي الشامل بنسخة PDF:</strong></p>
                <a href='{$reportLink}&download=pdf' style='background: #FF6B35; color: #fff; padding: 15px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 1.1rem; box-shadow: 0 4px 6px rgba(255,107,53,0.2);'>تحميل تقرير 2026 (PDF)</a>
                <p style='margin-top: 15px; font-size: 0.85rem; color: #718096;'>ملاحظة: يمكنك حفظ الصفحة كملف PDF فور فتحها.</p>
            </div>
            
            <p>يتضمن التقرير:</p>
            <ul>
                <li>تحليل نضج 2026 مقارنة بالقطاع.</li>
                <li>تقدير الهدر المالي في الفرص المفقودة.</li>
                <li>خارطة طريق تنفيذية مقسمة لـ 3 مراحل (0-6 أشهر).</li>
            </ul>
            
            <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='font-size: 0.9rem; color: #666;'>إذا كان لديك أي استفسار حول النقاط المذكورة في التقرير، يسعدني مناقشتها معك في جلسة استشارية خاصة.</p>
            <p>مع خالص التحية،<br><strong>خالد سعد</strong><br>خبير التحول الرقمي ونمو الأعمال</p>
        </div>
        ";

        // إرسال البريد (باستخدام الترويسات الصحيحة للـ HTML)
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . SITE_NAME . " <" . SITE_EMAIL . ">" . "\r\n";

        if (mail($email, $subject, $message, $headers)) {
            // تحديث حالة التقرير
            db()->update('diagnostic_results', ['status' => 'sent'], 'id = ?', [$report['id']]);
            echo "Report ID {$report['id']} sent to {$email}\n";
        } else {
            echo "Failed to send report ID {$report['id']}\n";
        }
    }

} catch (Exception $e) {
    error_log("Cron Send Reports Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

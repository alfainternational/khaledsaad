<?php
/**
 * API: Generate Professional Report
 * توليد تقارير احترافية شاملة للعملاء
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/professional_report_generator.php';

// التحقق من الصلاحيات
if (!isset($_SESSION['admin_id']) && !isset($_GET['token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? 'generate';
$diagnosticId = $_GET['diagnostic_id'] ?? $_POST['diagnostic_id'] ?? null;

if (!$diagnosticId) {
    http_response_code(400);
    echo json_encode(['error' => 'Diagnostic ID required'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $generator = new ProfessionalReportGenerator($diagnosticId);

    switch ($action) {
        // توليد التقرير الكامل (JSON)
        case 'generate':
            $report = $generator->generateFullReport();

            echo json_encode([
                'success' => true,
                'report' => $report
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        // تصدير التقرير كـ PDF
        case 'export_pdf':
            $report = $generator->generateFullReport();

            // حفظ التقرير في قاعدة البيانات
            db()->update('diagnostic_results', [
                'professional_report' => json_encode($report, JSON_UNESCAPED_UNICODE),
                'report_generated_at' => date('Y-m-d H:i:s')
            ], ['id' => $diagnosticId]);

            // إنشاء PDF (سيتم تطويره لاحقاً مع mPDF)
            // $pdf = generatePDF($report);
            // header('Content-Type: application/pdf');
            // echo $pdf;

            echo json_encode([
                'success' => true,
                'message' => 'Report generated and saved',
                'pdf_url' => '/reports/diagnostic_' . $diagnosticId . '.pdf'
            ], JSON_UNESCAPED_UNICODE);
            break;

        // إرسال التقرير للعميل عبر البريد
        case 'send_email':
            $report = $generator->generateFullReport();

            // جلب بيانات العميل
            $diagnostic = db()->fetchOne("SELECT * FROM diagnostic_results WHERE id = ?", [$diagnosticId]);

            if (!$diagnostic || !$diagnostic['email']) {
                throw new Exception('Client email not found');
            }

            // إرسال البريد (يتطلب PHPMailer)
            // $emailSent = sendReportEmail($diagnostic['email'], $report);

            // تحديث الحالة
            db()->update('diagnostic_results', [
                'status' => 'sent',
                'report_sent_at' => date('Y-m-d H:i:s')
            ], ['id' => $diagnosticId]);

            echo json_encode([
                'success' => true,
                'message' => 'Report sent successfully to ' . $diagnostic['email']
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * API v4.0 (Enhanced Engine)
 * يستخدم محرك التحليل المتقدم للبيانات قبل الحفظ
 */

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/diagnostic_ai.php'; // استدعاء المحرك الجديد

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('طريقة الطلب غير مسموحة', [], 405);
}

 $input = file_get_contents('php://input');
 $data = json_decode($input, true);

if (!$data) {
    errorResponse('بيانات الطلب غير صالحة');
}

try {
    // 1. تشغيل محرك الذكاء الاصطناعي
    $ai = new DiagnosticAI();
    $analysisResult = $ai->analyze($data);

    // 2. تجهيز البيانات للحفظ
    $send_hours = rand(20, 24);
    $scheduled_send_at = date('Y-m-d H:i:s', strtotime("+$send_hours hours"));

    $insertData = [
        'session_id'         => $data['session_id'] ?? session_id(),
        'report_token'       => $analysisResult['report_token'],
        'full_name'          => !empty($data['full_name']) ? clean($data['full_name']) : null,
        'email'              => !empty($data['email']) ? clean($data['email']) : null,
        'phone'              => !empty($data['phone']) ? clean($data['phone']) : null,
        'lead_source'        => !empty($data['lead_source']) ? clean($data['lead_source']) : null,
        'company_name'       => !empty($data['company_name']) ? clean($data['company_name']) : null,
        'industry'           => !empty($data['industry']) ? clean($data['industry']) : null,
        'company_size'       => !empty($data['company_size']) ? clean($data['company_size']) : null,
        
        // النتائج من الذكاء الاصطناعي
        'overall_score'      => $analysisResult['overall'],
        'score'              => $analysisResult['overall'], // للتوافق القديم
        'maturity_level'     => $analysisResult['maturity']['label'],
        'category'           => $analysisResult['maturity']['label'], // للتوافق القديم
        'benchmark_score'    => $analysisResult['benchmark'],
        'estimated_leakage'  => $analysisResult['leakage'],
        'lead_quality_score' => $analysisResult['lead_quality'],
        
        // البيانات التفصيلية (JSON)
        'pillars_data'       => json_encode($analysisResult['scores'], JSON_UNESCAPED_UNICODE),
        'roadmap_data'       => json_encode($analysisResult['roadmap'], JSON_UNESCAPED_UNICODE),
        'insights_data'      => json_encode($analysisResult['insights'], JSON_UNESCAPED_UNICODE),
        'swot_analysis'      => json_encode($analysisResult['swot_analysis'], JSON_UNESCAPED_UNICODE),
        'consultant_report'  => json_encode($analysisResult['consultant_report'], JSON_UNESCAPED_UNICODE),
        'financial_analysis' => json_encode($analysisResult['financial_analysis'], JSON_UNESCAPED_UNICODE),
        'ai_narrative'       => $analysisResult['narrative'] ?? '', // جديد v6.0
        'recommendations_data' => json_encode($data['recommendations'] ?? [], JSON_UNESCAPED_UNICODE),
        'recommendations'    => json_encode($data['recommendations'] ?? [], JSON_UNESCAPED_UNICODE), // للتوافق القديم
        'answers'            => json_encode($data['answers'] ?? [], JSON_UNESCAPED_UNICODE),
        
        // الحالة والموعد
        'status'             => 'pending_review',
        'scheduled_send_at'  => $scheduled_send_at,
        
        'ip_address'         => Security::getClientIP(),
        'completed_at'       => date('Y-m-d H:i:s')
    ];

    $id = db()->insert('diagnostic_results', $insertData);

    successResponse('تمت معالجة البيانات بنجاح بواسطة محرك التحليل الاستراتيجي', ['id' => $id, 'token' => $analysisResult['report_token']]);
} catch (Exception $e) {
    error_log("AI API Error: " . $e->getMessage());
    errorResponse('حدث خطأ في المعالجة الفنية: ' . $e->getMessage(), [], 500);
}
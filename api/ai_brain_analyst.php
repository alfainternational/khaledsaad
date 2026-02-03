<?php
/**
 * API المحرك الذكي (AI Brain API)
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once SITE_ROOT . '/includes/ai_brain.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$brain = new AIBrain();

if ($action === 'analyze') {
    $analysisData = $brain->analyzeGlobalActivity();
    // نرسل البيانات كـ Object مباشر بدلاً من String مشوه بـ nl2br
    echo json_encode(['success' => true, 'data' => $analysisData]);
    exit;
}

if ($action === 'chat') {
    $data = json_decode(file_get_contents('php://input'), true);
    $msg = $data['message'] ?? '';
    
    if (empty($msg)) {
        echo json_encode(['success' => false, 'message' => 'Empty message']);
        exit;
    }

    $response = $brain->chatWithAssistant($msg);
    echo json_encode(['success' => true, 'response' => nl2br($response)]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

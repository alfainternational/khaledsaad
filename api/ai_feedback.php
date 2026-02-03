<?php
/**
 * API لجلب وحفظ التغذية الراجعة لتعلم الذكاء الاصطناعي
 */
require_once dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['report_id'], $data['correlation_key'], $data['type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    db()->insert('ai_learning_feedback', [
        'report_id' => (int)$data['report_id'],
        'correlation_key' => clean($data['correlation_key']),
        'feedback_type' => $data['type'] === 'positive' ? 'positive' : 'negative',
        'comment' => clean($data['comment'] ?? '')
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

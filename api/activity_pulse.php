<?php
/**
 * API استقبال نبض النشاط (Pulse) لتتبع مدة الجلسات
 */
require_once dirname(__DIR__) . '/includes/init.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($data['duration'])) {
    logActivity('pulse', [
        'duration_seconds' => $data['duration'],
        'page' => $data['path']
    ]);
}

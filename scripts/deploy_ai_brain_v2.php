<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = db()->getConnection();
    
    // 1. جدول تتبع السلوك (Behavioral Tracking)
    $db->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100),
        user_id INT NULL,
        event_type VARCHAR(50), -- page_view, click, diagnostic_start, etc.
        page_url TEXT,
        meta_data JSON, -- لتخزين تفاصيل إضافية عن السلوك
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. جدول "دردشة المساعد الذكي" (AI Chat assistant)
    $db->exec("CREATE TABLE IF NOT EXISTS ai_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('user', 'assistant') NOT NULL,
        message TEXT,
        context_data JSON NULL, -- لتخزين حالة المحادثة أو البيانات التي رآها الذكاء الاصطناعي
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. جدول "اقتراحات تطوير القاعدة المعرفية" (KB Evolution)
    $db->exec("CREATE TABLE IF NOT EXISTS kb_ai_proposals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proposal_type VARCHAR(100),
        suggested_rule JSON,
        rational TEXT,
        status ENUM('pending', 'applied', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Infrastructure for AI Brain 2.0 (Evolutionary AI) has been deployed successfully.\n";

} catch (Exception $e) {
    echo "Error deploying AI Brain Infrastructure: " . $e->getMessage() . "\n";
}

<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = db()->getConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_learning_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        correlation_key VARCHAR(100) NOT NULL,
        feedback_type ENUM('positive', 'negative') NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $db->exec($sql);
    echo "Table 'ai_learning_feedback' created successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

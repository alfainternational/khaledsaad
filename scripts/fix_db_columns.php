<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = db()->getConnection();
    
    $columns = [
        'lead_source' => "VARCHAR(100) DEFAULT 'direct' AFTER phone",
        'roadmap_data' => "LONGTEXT DEFAULT NULL AFTER pillars_data",
        'insights_data' => "LONGTEXT DEFAULT NULL AFTER roadmap_data",
        'score' => "INT DEFAULT 0 AFTER overall_score",
        'category' => "VARCHAR(100) DEFAULT NULL AFTER maturity_level",
        'recommendations' => "JSON DEFAULT NULL AFTER recommendations_data"
    ];

    foreach ($columns as $column => $definition) {
        try {
            $db->exec("ALTER TABLE diagnostic_results ADD COLUMN $column $definition");
            echo "Column '$column' added successfully.\n";
        } catch (PDOException $e) {
            echo "Column '$column' might already exist or error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Database upgrade complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

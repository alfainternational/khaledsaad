<?php
require_once __DIR__ . '/../includes/init.php';
try {
    $results = db()->fetchAll("SELECT id, full_name, company_name, overall_score, status, created_at FROM diagnostic_results ORDER BY created_at DESC LIMIT 10");
    foreach($results as $r) {
        echo "ID: {$r['id']} | Name: {$r['full_name']} | Company: {$r['company_name']} | Score: {$r['overall_score']} | Status: {$r['status']} | Created: {$r['created_at']}\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}

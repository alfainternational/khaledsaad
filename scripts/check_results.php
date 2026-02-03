<?php
require_once __DIR__ . '/../includes/init.php';
try {
    $results = db()->fetchAll("SELECT * FROM diagnostic_results ORDER BY created_at DESC LIMIT 5");
    $out = [];
    foreach($results as $r) {
        $out[] = "ID: " . $r['id'] . " | Name: " . $r['full_name'] . " | Created: " . $r['created_at'];
    }
    file_put_contents(__DIR__ . '/results_clean.txt', implode("\n", $out));
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/results_clean.txt', $e->getMessage());
}

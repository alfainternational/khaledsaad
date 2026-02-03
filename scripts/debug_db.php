<?php
require_once __DIR__ . '/../includes/init.php';
try {
    $db = db()->getConnection();
    $stmt = $db->query("DESCRIBE diagnostic_results");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents(__DIR__ . '/db_schema.json', json_encode($columns, JSON_PRETTY_PRINT));
    echo "Done";
} catch (Exception $e) {
    echo $e->getMessage();
}

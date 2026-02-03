<?php
/**
 * TinyMCE Upload Handler
 */
session_start();
require_once dirname(__DIR__) . '/includes/init.php';

// التحقق من الجلسة
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح لك بالوصول']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'نوع الملف غير مدعوم']);
        exit;
    }

    $newName = 'editor_' . time() . '_' . uniqid() . '.' . $ext;
    $uploadDir = SITE_ROOT . '/uploads/posts/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        $location = url('uploads/posts/' . $newName);
        echo json_encode(['location' => $location]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'فشل رفع الملف']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'طلب غير صالح']);

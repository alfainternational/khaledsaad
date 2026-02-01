<?php
/**
 * تسجيل الخروج
 * موقع خالد سعد للاستشارات
 */

session_start();
require_once dirname(__DIR__) . '/includes/init.php';

// تسجيل النشاط
if (isset($_SESSION['admin_id'])) {
    Security::logActivity('admin_logout', 'users', $_SESSION['admin_id']);
}

// حذف الجلسة
$_SESSION = [];

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

// إعادة التوجيه لصفحة الدخول
header('Location: login.php');
exit;

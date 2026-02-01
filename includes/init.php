<?php
/**
 * ملف التهيئة الرئيسي
 * موقع خالد سعد للاستشارات
 */

// تعريف المسار الجذري
define('SITE_ROOT', dirname(__DIR__));

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل ملفات التكوين
require_once SITE_ROOT . '/config/config.php';
require_once SITE_ROOT . '/config/database.php';
require_once SITE_ROOT . '/includes/security.php';
require_once SITE_ROOT . '/includes/functions.php';

// تعيين الترميز
mb_internal_encoding('UTF-8');

// إنشاء مجلد السجلات إذا لم يكن موجوداً
$logsDir = SITE_ROOT . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// إنشاء مجلد الرفع إذا لم يكن موجوداً
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

<?php
/**
 * ملف التكوين الرئيسي
 * موقع خالد سعد للاستشارات
 */

// منع الوصول المباشر
if (!defined('SITE_ROOT')) {
    define('SITE_ROOT', dirname(__DIR__));
}

// إعدادات الموقع
define('SITE_NAME', 'خالد سعد للاستشارات');
define('SITE_TAGLINE', 'شريكك في التحول الرقمي');
define('SITE_EMAIL', 'info@khaledsaad.com');
define('SITE_PHONE', '+966 50 000 0000');
define('SITE_ADDRESS', 'الرياض، المملكة العربية السعودية');
define('SITE_URL', 'https://khaledsaad.com');

// إعدادات التطوير
define('DEBUG_MODE', true);
define('ENVIRONMENT', 'development');

// إعدادات المسارات
define('UPLOAD_PATH', SITE_ROOT . '/uploads');
define('UPLOAD_URL', '/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// إعدادات الجلسة
define('SESSION_LIFETIME', 7200);
define('SESSION_NAME', 'khaledsaad_session');

// إعدادات الأمان
define('CSRF_TOKEN_NAME', '_csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);

// إعدادات Rate Limiting
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 3600);

// المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// إعدادات الأخطاء
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

ini_set('log_errors', 1);
ini_set('error_log', SITE_ROOT . '/logs/error.log');

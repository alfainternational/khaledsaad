<?php
/**
 * دوال مساعدة
 */

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function clean($string) {
    return trim(strip_tags($string ?? ''));
}

function url($path = '') {
    $base = rtrim(SITE_URL ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * دالة ترجمة المصطلحات الخاصة بالتشخيص الاستراتيجي
 */
function translate($str) {
    if (empty($str)) return '-';
    $dict = [
        'ecommerce' => 'تجارة إلكترونية',
        'services' => 'خدمات',
        'retail' => 'تجزئة',
        'tech' => 'تقنية',
        'fmcg' => 'سلع استهلاكية',
        'other' => 'أخرى',
        'google' => 'جوجل',
        'social' => 'سوشيال ميديا',
        'referral' => 'توصية',
        'ads' => 'إعلانات ممولة',
        'solo' => 'فردي',
        'small' => 'صغيرة',
        'medium' => 'متوسطة',
        'large' => 'كبيرة',
        'direct' => 'مباشر'
    ];
    return $dict[$str] ?? $dict[strtolower($str)] ?? $str;
}

/**
 * تسجيل سلوك المستخدم لتحليل الذكاء الاصطناعي اللاحق
 */
function logActivity($type, $meta = []) {
    try {
        db()->insert('user_activity_logs', [
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? ($_SESSION['admin_id'] ?? null),
            'event_type' => $type,
            'page_url' => $_SERVER['REQUEST_URI'],
            'meta_data' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Activity Logging Error: " . $e->getMessage());
    }
}

function asset($path) {
    return url('assets/' . ltrim($path, '/'));
}

function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

function formatDate($date, $format = 'full') {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    
    $formats = [
        'full' => 'j F Y',
        'short' => 'j M Y',
        'time' => 'H:i',
        'datetime' => 'j F Y - H:i'
    ];
    
    $arabicMonths = [
        'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
        'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
        'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
        'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر',
        'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس',
        'Apr' => 'أبريل', 'Jun' => 'يونيو', 'Jul' => 'يوليو',
        'Aug' => 'أغسطس', 'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر',
        'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر'
    ];
    
    $result = date($formats[$format] ?? $format, $timestamp);
    return strtr($result, $arabicMonths);
}

function formatNumber($number) {
    return number_format($number, 0, '.', ',');
}

function truncate($string, $length = 100, $suffix = '...') {
    $string = strip_tags($string);
    if (mb_strlen($string) <= $length) return $string;
    return mb_substr($string, 0, $length) . $suffix;
}

function readingTime($content, $wpm = 200) {
    $wordCount = str_word_count(strip_tags($content));
    return max(1, ceil($wordCount / $wpm));
}

function successResponse($message = '', $data = []) {
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse($message, $errors = [], $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

function getSetting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

function honeypotField() {
    return '<div style="position:absolute;left:-9999px;"><input type="text" name="website_url" value="" tabindex="-1" autocomplete="off"></div>';
}

function validateHoneypot() {
    return empty($_POST['website_url']);
}

function trackPageView($url, $title = '') {
    try {
        db()->insert('analytics', [
            'session_id' => session_id(),
            'page_url' => $url,
            'page_title' => $title,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
            'utm_source' => $_GET['utm_source'] ?? null,
            'utm_medium' => $_GET['utm_medium'] ?? null,
            'utm_campaign' => $_GET['utm_campaign'] ?? null,
            'ip_address' => Security::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {}
}

/**
 * توليد الرابط المختصر (Slug) من النص
 * يدعم اللغة العربية والإنجليزية
 */
function generateSlug($text) {
    // استبدال المسافات والعلامات بـ -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // التحويل لـ lowercase
    $text = mb_strtolower($text, 'UTF-8');
    // إزالة العلامات غير المرغوبة (مع الحفاظ على الحروف العربية)
    $text = preg_replace('~[^-\w\x{0600}-\x{06FF}]+~u', '', $text);
    // إزالة الشرطات المتكررة
    $text = preg_replace('~-+~', '-', $text);
    // إزالة الشرطات من البداية والنهاية
    $text = trim($text, '-');
    
    return empty($text) ? 'post-' . time() : $text;
}


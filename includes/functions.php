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

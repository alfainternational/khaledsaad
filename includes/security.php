<?php
/**
 * دوال الأمان
 */

class Security {
    public static function generateCSRFToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function validateCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    public static function csrfField() {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::generateCSRFToken() . '">';
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function getClientIP() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function checkRateLimit($identifier, $endpoint, $limit = 100, $window = 3600) {
        try {
            $db = db();
            $key = md5($identifier . $endpoint);
            $now = time();
            
            $record = $db->fetchOne(
                "SELECT * FROM rate_limits WHERE identifier = ? AND endpoint = ?",
                [$key, $endpoint]
            );

            if ($record) {
                $windowStart = strtotime($record['window_start']);
                if ($now - $windowStart > $window) {
                    $db->update('rate_limits', [
                        'requests_count' => 1,
                        'window_start' => date('Y-m-d H:i:s')
                    ], 'id = ?', ['id' => $record['id']]);
                    return true;
                }
                
                if ($record['requests_count'] >= $limit) {
                    return false;
                }
                
                $db->query(
                    "UPDATE rate_limits SET requests_count = requests_count + 1 WHERE id = ?",
                    [$record['id']]
                );
            } else {
                $db->insert('rate_limits', [
                    'identifier' => $key,
                    'endpoint' => $endpoint,
                    'requests_count' => 1,
                    'window_start' => date('Y-m-d H:i:s')
                ]);
            }
            return true;
        } catch (Exception $e) {
            return true;
        }
    }

    public static function logActivity($action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
        try {
            db()->insert('activity_logs', [
                'user_id' => $_SESSION['admin_id'] ?? null,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
}

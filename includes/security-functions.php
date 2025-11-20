<?php
/**
 * セキュリティ関数ライブラリ
 */

/**
 * 実IPアドレスの取得
 */
function getRealIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : '0.0.0.0';
}

/**
 * CSRFトークンの生成
 */
function generateCSRFToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

/**
 * CSRFトークンの検証
 */
function validateCSRFToken($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    $lifetime = defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 3600;
    if (time() - $_SESSION['csrf_token_time'] > $lifetime) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 二重送信防止トークンの生成
 */
function generateDoubleSubmitToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['double_submit_token'] = $token;
    $_SESSION['double_submit_token_time'] = time();
    
    return $token;
}

/**
 * 二重送信防止トークンの検証と削除
 */
function validateDoubleSubmitToken($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    if (!isset($_SESSION['double_submit_token'])) {
        return false;
    }
    
    $valid = hash_equals($_SESSION['double_submit_token'], $token);
    
    // トークンを削除（一度しか使えない）
    unset($_SESSION['double_submit_token']);
    unset($_SESSION['double_submit_token_time']);
    
    return $valid;
}

/**
 * reCAPTCHA v3の検証
 */
function verifyRecaptcha($token) {
    if (!defined('RECAPTCHA_SECRET_KEY') || empty(RECAPTCHA_SECRET_KEY)) {
        return true;
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => getRealIP()
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return true; // APIエラー時は通す
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['success']) || !$result['success']) {
        return false;
    }
    
    $threshold = defined('RECAPTCHA_THRESHOLD') ? RECAPTCHA_THRESHOLD : 0.5;
    if (isset($result['score']) && $result['score'] < $threshold) {
        return false;
    }
    
    return true;
}

/**
 * レート制限チェック
 */
function checkRateLimit($email = null) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $ip = getRealIP();
    $currentTime = time();
    
    $count = defined('RATE_LIMIT_COUNT') ? RATE_LIMIT_COUNT : 3;
    $period = defined('RATE_LIMIT_PERIOD') ? RATE_LIMIT_PERIOD : 3600;
    $strictMode = defined('RATE_LIMIT_STRICT_MODE') ? RATE_LIMIT_STRICT_MODE : true;
    
    // IPベースのレート制限
    if (!isset($_SESSION['rate_limit_ip'])) {
        $_SESSION['rate_limit_ip'] = [];
    }
    
    $_SESSION['rate_limit_ip'] = array_filter(
        $_SESSION['rate_limit_ip'],
        function($timestamp) use ($currentTime, $period) {
            return $currentTime - $timestamp < $period;
        }
    );
    
    if (count($_SESSION['rate_limit_ip']) >= $count) {
        return false;
    }
    
    // メールアドレスベースのレート制限（厳格モード）
    if ($strictMode && $email) {
        if (!isset($_SESSION['rate_limit_email'])) {
            $_SESSION['rate_limit_email'] = [];
        }
        
        $_SESSION['rate_limit_email'] = array_filter(
            $_SESSION['rate_limit_email'],
            function($data) use ($currentTime, $period) {
                return $currentTime - $data['time'] < $period;
            }
        );
        
        $emailCount = 0;
        foreach ($_SESSION['rate_limit_email'] as $data) {
            if ($data['email'] === $email) {
                $emailCount++;
            }
        }
        
        if ($emailCount >= $count) {
            return false;
        }
    }
    
    // 記録
    $_SESSION['rate_limit_ip'][] = $currentTime;
    if ($strictMode && $email) {
        $_SESSION['rate_limit_email'][] = [
            'email' => $email,
            'time' => $currentTime
        ];
    }
    
    return true;
}

/**
 * ハニーポットチェック
 */
function checkHoneypot($data) {
    if (!defined('ENABLE_HONEYPOT') || !ENABLE_HONEYPOT) {
        return true;
    }
    
    // websiteフィールドは空であるべき
    if (isset($data['website']) && !empty($data['website'])) {
        return false;
    }
    
    return true;
}

/**
 * タイムスタンプチェック
 */
function checkTimestamp($data) {
    if (!defined('ENABLE_TIMESTAMP_CHECK') || !ENABLE_TIMESTAMP_CHECK) {
        return true;
    }
    
    if (!isset($data['timestamp'])) {
        return false;
    }
    
    $timestamp = intval($data['timestamp']);
    $currentTime = time();
    $elapsed = $currentTime - $timestamp;
    
    $minTime = defined('MIN_FORM_FILL_TIME') ? MIN_FORM_FILL_TIME : 3;
    $maxTime = defined('MAX_FORM_FILL_TIME') ? MAX_FORM_FILL_TIME : 3600;
    
    if ($elapsed < $minTime || $elapsed > $maxTime) {
        return false;
    }
    
    return true;
}

/**
 * IP制限チェック
 */
function checkIPRestriction() {
    $ip = getRealIP();
    
    // ホワイトリストチェック
    if (defined('IP_WHITELIST') && is_array(IP_WHITELIST) && in_array($ip, IP_WHITELIST)) {
        return true;
    }
    
    // ブラックリストチェック
    if (defined('IP_BLACKLIST') && is_array(IP_BLACKLIST) && in_array($ip, IP_BLACKLIST)) {
        return false;
    }
    
    // 国別制限（簡易版 - 実際にはGeoIPライブラリが必要）
    if (defined('ALLOWED_COUNTRIES') && is_array(ALLOWED_COUNTRIES)) {
        // お名前.comではGeoIP機能が制限される可能性があるため、スキップ
        return true;
    }
    
    return true;
}

/**
 * 入力値のサニタイズ
 */
function sanitizeInput($input) {
    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * SQL用サニタイズ
 */
function sanitizeSQL($input) {
    // 将来のDB対応用
    return str_replace(["'", '"', ';', '--'], '', $input);
}

/**
 * メールアドレスの検証
 */
function validateEmail($email) {
    $email = strtolower(trim($email));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $maxLength = defined('MAX_EMAIL_LENGTH') ? MAX_EMAIL_LENGTH : 254;
    if (strlen($email) > $maxLength) {
        return false;
    }
    
    // 使い捨てメールドメインチェック
    if (defined('DISPOSABLE_EMAIL_DOMAINS') && is_array(DISPOSABLE_EMAIL_DOMAINS)) {
        $domain = substr(strrchr($email, '@'), 1);
        if (in_array($domain, DISPOSABLE_EMAIL_DOMAINS)) {
            return false;
        }
    }
    
    // MXレコード検証（オプション）
    if (defined('ENABLE_MX_VALIDATION') && ENABLE_MX_VALIDATION) {
        $domain = substr(strrchr($email, '@'), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            return false;
        }
    }
    
    return $email;
}

/**
 * 電話番号の検証
 */
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    $maxLength = defined('MAX_PHONE_LENGTH') ? MAX_PHONE_LENGTH : 15;
    if (strlen($phone) > $maxLength) {
        return false;
    }
    
    // 日本の電話番号形式
    if (!preg_match('/^0\d{9,10}$/', $phone)) {
        return false;
    }
    
    return true;
}

/**
 * 禁止ワードチェック
 */
function containsProhibitedWords($text) {
    if (!defined('PROHIBITED_WORDS') || !is_array(PROHIBITED_WORDS)) {
        return false;
    }
    
    $text = strtolower($text);
    foreach (PROHIBITED_WORDS as $word) {
        if (stripos($text, strtolower($word)) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * セキュリティログ記録
 */
function logSecurity($message) {
    $logDir = defined('LOG_SAVE_PATH') ? LOG_SAVE_PATH : __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/security_' . date('Y-m') . '.log';
    $logData = date('Y-m-d H:i:s') . " | " . getRealIP() . " | " . $message . "\n";
    @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
}

/**
 * 監査ログ記録
 */
function logAudit($action, $details = []) {
    if (!defined('ENABLE_AUDIT_LOG') || !ENABLE_AUDIT_LOG) {
        return;
    }
    
    $logDir = defined('LOG_SAVE_PATH') ? LOG_SAVE_PATH : __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/audit_' . date('Y-m') . '.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'ip' => getRealIP(),
        'details' => $details
    ];
    
    if (defined('LOG_USER_AGENT') && LOG_USER_AGENT) {
        $logEntry['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    $logData = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
}

/**
 * エラー通知送信
 */
function sendErrorNotification($subject, $message) {
    if (!defined('ENABLE_ERROR_NOTIFICATION') || !ENABLE_ERROR_NOTIFICATION) {
        return;
    }
    
    $adminEmail = defined('ERROR_NOTIFICATION_EMAIL') ? ERROR_NOTIFICATION_EMAIL : ADMIN_EMAIL;
    $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@sept3.co.jp';
    
    $body = "エラーが発生しました:\n\n";
    $body .= $message . "\n\n";
    $body .= "時刻: " . date('Y-m-d H:i:s') . "\n";
    $body .= "IP: " . getRealIP() . "\n";
    
    $headers = "From: {$fromEmail}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    @mb_send_mail($adminEmail, $subject, $body, $headers);
}

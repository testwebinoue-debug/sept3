<?php
/**
 * お問い合わせフォーム処理（エンタープライズレベル・お名前.com SD対応版）
 */

// エラー表示を完全にオフ（本番環境）
error_reporting(0);
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

// 設定ファイルの読み込み
if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration error']);
    exit;
}
require_once __DIR__ . '/config.php';

// セキュリティ関数の読み込み
if (!file_exists(__DIR__ . '/includes/security-functions.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Security library not found']);
    exit;
}
require_once __DIR__ . '/includes/security-functions.php';

// セッション保存先の設定
$session_path = defined('SESSION_SAVE_PATH') ? SESSION_SAVE_PATH : __DIR__ . '/tmp/sessions';
if (!is_dir($session_path)) {
    @mkdir($session_path, 0700, true);
}
if (is_dir($session_path) && is_writable($session_path)) {
    @ini_set('session.save_path', $session_path);
}

// セッション設定
@ini_set('session.cookie_httponly', '1');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');

// HTTPS使用時のみ有効化
if (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    @ini_set('session.cookie_secure', '1');
}

// PHP 7.3以上の場合
if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
    @ini_set('session.cookie_samesite', 'Strict');
}

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// セッション開始失敗時の対応
if (session_status() !== PHP_SESSION_ACTIVE) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Session error']);
    exit;
}

// セッションハイジャック対策
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// セキュリティヘッダーの設定
if (defined('SECURITY_HEADERS') && is_array(SECURITY_HEADERS)) {
    foreach (SECURITY_HEADERS as $header => $value) {
        header($header . ': ' . $value);
    }
}

header('Content-Type: application/json; charset=utf-8');

// CORS設定（同一オリジンのみ許可）
$allowedOrigins = [
    'https://' . $_SERVER['HTTP_HOST'],
    'http://' . $_SERVER['HTTP_HOST']
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// IP制限チェック
if (defined('ENABLE_IP_RESTRICTION') && ENABLE_IP_RESTRICTION) {
    if (!checkIPRestriction()) {
        logSecurity('IP restriction: Access denied');
        logAudit('ACCESS_DENIED_IP', ['reason' => 'IP restriction']);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

try {
    // JSONデータの取得
    $json = file_get_contents('php://input');
    
    // JSONのサイズチェック（DoS対策）
    if (strlen($json) > 1048576) { // 1MB
        throw new Exception('Request too large');
    }
    
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logSecurity('Invalid JSON: ' . json_last_error_msg());
        throw new Exception('Invalid data format');
    }
    
    // CSRFトークンの検証
    if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
        logSecurity('CSRF token validation failed');
        logAudit('FORM_SUBMIT_FAILED', ['reason' => 'CSRF validation failed']);
        throw new Exception('Security validation failed');
    }
    
    // 二重送信防止トークンの検証
    if (defined('DOUBLE_SUBMIT_PREVENTION') && DOUBLE_SUBMIT_PREVENTION) {
        if (!isset($data['double_submit_token']) || !validateDoubleSubmitToken($data['double_submit_token'])) {
            logSecurity('Double submit token validation failed');
            throw new Exception('この送信は既に処理されているか、無効です。ページを再読み込みしてください。');
        }
    }
    
    // reCAPTCHA v3の検証
    if (defined('RECAPTCHA_SECRET_KEY') && !empty(RECAPTCHA_SECRET_KEY)) {
        if (!isset($data['recaptcha_token']) || !verifyRecaptcha($data['recaptcha_token'])) {
            logSecurity('reCAPTCHA validation failed');
            logAudit('FORM_SUBMIT_FAILED', ['reason' => 'reCAPTCHA failed']);
            throw new Exception('ボット対策の検証に失敗しました。ページを再読み込みしてください。');
        }
    }
    
    // ハニーポットチェック
    if (!checkHoneypot($data)) {
        logSecurity('Honeypot triggered');
        logAudit('FORM_SUBMIT_BLOCKED', ['reason' => 'Honeypot']);
        throw new Exception('Invalid request');
    }
    
    // タイムスタンプチェック
    if (!checkTimestamp($data)) {
        logSecurity('Timestamp check failed');
        logAudit('FORM_SUBMIT_FAILED', ['reason' => 'Timestamp check']);
        throw new Exception('Invalid request timing');
    }
    
    // レート制限チェック
    $emailForRateLimit = isset($data['email']) ? $data['email'] : null;
    if (!checkRateLimit($emailForRateLimit)) {
        http_response_code(429);
        logAudit('FORM_SUBMIT_BLOCKED', ['reason' => 'Rate limit']);
        throw new Exception('送信回数の上限に達しました。しばらく時間をおいてから再度お試しください。');
    }
    
    // 必須項目のチェック
    $requiredFields = ['inquiryType', 'lastName', 'firstName', 'lastNameKana', 'firstNameKana', 'phone', 'email', 'content'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            throw new Exception('必須項目が入力されていません: ' . $field);
        }
    }
    
    // データのサニタイズ
    $inquiryType = sanitizeInput($data['inquiryType']);
    $company = isset($data['company']) ? sanitizeInput($data['company']) : '';
    $lastName = sanitizeInput($data['lastName']);
    $firstName = sanitizeInput($data['firstName']);
    $lastNameKana = sanitizeInput($data['lastNameKana']);
    $firstNameKana = sanitizeInput($data['firstNameKana']);
    $phone = sanitizeInput($data['phone']);
    $email = sanitizeInput($data['email']);
    $content = sanitizeInput($data['content']);
    
    // 文字数制限チェック
    $maxNameLength = defined('MAX_NAME_LENGTH') ? MAX_NAME_LENGTH : 50;
    $maxCompanyLength = defined('MAX_COMPANY_LENGTH') ? MAX_COMPANY_LENGTH : 100;
    $maxContentLength = defined('MAX_CONTENT_LENGTH') ? MAX_CONTENT_LENGTH : 5000;
    
    if (mb_strlen($lastName, 'UTF-8') > $maxNameLength || mb_strlen($firstName, 'UTF-8') > $maxNameLength) {
        throw new Exception('お名前が長すぎます（' . $maxNameLength . '文字以内）');
    }
    if (mb_strlen($company, 'UTF-8') > $maxCompanyLength) {
        throw new Exception('会社名が長すぎます（' . $maxCompanyLength . '文字以内）');
    }
    if (mb_strlen($content, 'UTF-8') > $maxContentLength) {
        throw new Exception('お問い合わせ内容が長すぎます（' . $maxContentLength . '文字以内）');
    }
    
    // バリデーション
    if (!in_array($inquiryType, ['consultation', 'other'], true)) {
        throw new Exception('お問い合わせの種類が不正です');
    }
    
    // メールアドレスの検証（MXレコード検証含む）
    $email = validateEmail($email);
    if (!$email) {
        throw new Exception('メールアドレスの形式が正しくないか、存在しないドメインです');
    }
    
    // 電話番号の検証
    if (!validatePhone($phone)) {
        throw new Exception('電話番号の形式が正しくありません');
    }
    
    // カタカナチェック
    if (!preg_match('/^[ァ-ヶー\s]+$/u', $lastNameKana) || !preg_match('/^[ァ-ヶー\s]+$/u', $firstNameKana)) {
        throw new Exception('フリガナはカタカナで入力してください');
    }
    
    // 禁止ワードチェック
    $allText = $company . $lastName . $firstName . $content;
    if (containsProhibitedWords($allText)) {
        logSecurity('Prohibited word detected');
        logAudit('FORM_SUBMIT_BLOCKED', ['reason' => 'Prohibited word']);
        throw new Exception('不正な文字列が含まれています');
    }
    
    // SQLインジェクション対策（将来のDB対応）
    $company = sanitizeSQL($company);
    $lastName = sanitizeSQL($lastName);
    $firstName = sanitizeSQL($firstName);
    $content = sanitizeSQL($content);
    
    // お問い合わせ種類の日本語変換
    $inquiryTypeText = $inquiryType === 'consultation' ? '新規お取引のご相談' : 'その他';

    
// ========================================
// メール送信処理（完全版）
// ========================================

// メール送信設定の初期化
mb_language("Japanese");
mb_internal_encoding("UTF-8");

// お問い合わせ種類の日本語変換
$inquiryTypeText = $inquiryType === 'consultation' ? '新規お取引のご相談' : 'その他';

// メール本文の作成（管理者宛）
$mailBody = "【お問い合わせ内容】\n\n";
$mailBody .= "お問い合わせの種類: " . $inquiryTypeText . "\n";
if (!empty($company)) {
    $mailBody .= "会社名: " . $company . "\n";
}
$mailBody .= "お名前: " . $lastName . " " . $firstName . "\n";
$mailBody .= "フリガナ: " . $lastNameKana . " " . $firstNameKana . "\n";
$mailBody .= "電話番号: " . $phone . "\n";
$mailBody .= "メールアドレス: " . $email . "\n";
$mailBody .= "\n【お問い合わせ内容】\n";
$mailBody .= $content . "\n\n";
$mailBody .= "---\n";
$mailBody .= "送信日時: " . date('Y年m月d日 H:i:s') . "\n";
$mailBody .= "送信元IP: " . getRealIP() . "\n";
if (defined('LOG_USER_AGENT') && LOG_USER_AGENT) {
    $mailBody .= "User-Agent: " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown') . "\n";
}

// メール送信（管理者宛）
$subject = '【sept.3】お問い合わせがありました';
$fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@sept3.co.jp';
$adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'design@sept3.co.jp';

// ヘッダーの設定
$headers = "From: sept.3 <{$fromEmail}>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// メール送信実行
$success = @mb_send_mail($adminEmail, $subject, $mailBody, $headers);

if (!$success) {
    // mail()関数でリトライ
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fallback_headers = "From: =?UTF-8?B?" . base64_encode('sept.3') . "?= <{$fromEmail}>\r\n";
    $fallback_headers .= "Reply-To: {$email}\r\n";
    $fallback_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $fallback_headers .= "Content-Transfer-Encoding: 8bit\r\n";
    
    $success = @mail($adminEmail, $encoded_subject, $mailBody, $fallback_headers);
    
    if (!$success) {
        logSecurity('Mail send failed to admin (both methods)');
        if (defined('ENABLE_ERROR_NOTIFICATION') && ENABLE_ERROR_NOTIFICATION) {
            sendErrorNotification('メール送信失敗', 'お問い合わせフォームからのメール送信に失敗しました。');
        }
        throw new Exception('メール送信に失敗しました。時間をおいて再度お試しください。');
    }
}

// ========================================
// 自動返信メール（お客様宛）
// ========================================

// メール送信設定を再度初期化
mb_language("Japanese");
mb_internal_encoding("UTF-8");

// 自動返信メール本文
$autoReplyBody = $lastName . " " . $firstName . " 様\n\n";
$autoReplyBody .= "この度は、sept.3へお問い合わせいただきありがとうございます。\n";
$autoReplyBody .= "以下の内容でお問い合わせを受け付けました。\n\n";
$autoReplyBody .= "---\n\n";
$autoReplyBody .= "お問い合わせの種類: " . $inquiryTypeText . "\n";
if (!empty($company)) {
    $autoReplyBody .= "会社名: " . $company . "\n";
}
$autoReplyBody .= "お名前: " . $lastName . " " . $firstName . "\n";
$autoReplyBody .= "電話番号: " . $phone . "\n";
$autoReplyBody .= "メールアドレス: " . $email . "\n";
$autoReplyBody .= "\n【お問い合わせ内容】\n";
$autoReplyBody .= $content . "\n\n";
$autoReplyBody .= "---\n\n";
$autoReplyBody .= "内容を確認の上、担当者より改めてご連絡させていただきます。\n";
$autoReplyBody .= "今しばらくお待ちくださいますようお願いいたします。\n\n";
$autoReplyBody .= "※このメールは自動送信されています。\n";
$autoReplyBody .= "※このメールに返信されても対応できませんのでご了承ください。\n\n";
$autoReplyBody .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$autoReplyBody .= "sept.3 Inc.\n";
$autoReplyBody .= "〒530-0012 大阪市北区芝田1-12-7 大栄ビル新館N1003\n";
$autoReplyBody .= "TEL: 06-6376-0903\n";
$autoReplyBody .= "FAX: 06-6376-0913\n";
$autoReplyBody .= "━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$autoReplySubject = '【sept.3】お問い合わせを受け付けました';
$autoReplyHeaders = "From: sept.3 <{$fromEmail}>\r\n";
$autoReplyHeaders .= "Reply-To: {$fromEmail}\r\n";
$autoReplyHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
$autoReplyHeaders .= "Content-Transfer-Encoding: 8bit\r\n";
$autoReplyHeaders .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// 自動返信メール送信実行
$autoReplySuccess = @mb_send_mail($email, $autoReplySubject, $autoReplyBody, $autoReplyHeaders);

if (!$autoReplySuccess) {
    // mail()関数でリトライ
    $encoded_auto_subject = '=?UTF-8?B?' . base64_encode($autoReplySubject) . '?=';
    $fallback_auto_headers = "From: =?UTF-8?B?" . base64_encode('sept.3') . "?= <{$fromEmail}>\r\n";
    $fallback_auto_headers .= "Reply-To: {$fromEmail}\r\n";
    $fallback_auto_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $fallback_auto_headers .= "Content-Transfer-Encoding: 8bit\r\n";
    
    $autoReplySuccess = @mail($email, $encoded_auto_subject, $autoReplyBody, $fallback_auto_headers);
    
    if (!$autoReplySuccess) {
        logSecurity('Auto-reply mail send failed to: ' . $email);
        // 自動返信の失敗は処理を継続（管理者宛が送信されていれば問題なし）
    }
}
    
    // 監査ログ記録（成功）
    logAudit('FORM_SUBMIT_SUCCESS', [
        'inquiry_type' => $inquiryTypeText,
        'email' => $email,
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
    ]);
    
    // 通常のログ記録
    $logDir = defined('LOG_SAVE_PATH') ? LOG_SAVE_PATH : __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/contact_' . date('Y-m') . '.log';
    $logData = date('Y-m-d H:i:s') . " | SUCCESS | " . getRealIP() . " | " . $email . " | " . $inquiryTypeText . "\n";
    @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    
    // セッションIDの再生成（セキュリティ強化）
    session_regenerate_id(true);

    // ★重要：二重送信トークンは使い切ったので、ここでは再生成しない
    // フロント側で新しいトークンを取得する

    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'お問い合わせを受け付けました。ご入力いただいたメールアドレスに確認メールをお送りしました。'
    ]);
    
} catch (Exception $e) {
    // エラーログ記録
    $logDir = defined('LOG_SAVE_PATH') ? LOG_SAVE_PATH : __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/contact_' . date('Y-m') . '.log';
    $logData = date('Y-m-d H:i:s') . " | ERROR | " . getRealIP() . " | " . $e->getMessage() . "\n";
    @file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    
    // 監査ログ記録（エラー）
    logAudit('FORM_SUBMIT_ERROR', [
        'error' => $e->getMessage(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
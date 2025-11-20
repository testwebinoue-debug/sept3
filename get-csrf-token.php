<?php
/**
 * CSRFトークン取得API
 */

// エラー表示を完全にオフ
error_reporting(0);
@ini_set('display_errors', '0');

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

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');

// CORS設定
$allowedOrigins = [
    'https://' . $_SERVER['HTTP_HOST'],
    'http://' . $_SERVER['HTTP_HOST']
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
}

// GETリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // CSRFトークンの生成
    $csrfToken = generateCSRFToken();
    
    // 二重送信防止トークンの生成
    $doubleSubmitToken = null;
    if (defined('DOUBLE_SUBMIT_PREVENTION') && DOUBLE_SUBMIT_PREVENTION) {
        $doubleSubmitToken = generateDoubleSubmitToken();
    }
    
    // タイムスタンプ
    $timestamp = time();
    
    echo json_encode([
        'success' => true,
        'csrf_token' => $csrfToken,
        'double_submit_token' => $doubleSubmitToken,
        'timestamp' => $timestamp
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Token generation failed'
    ]);
}

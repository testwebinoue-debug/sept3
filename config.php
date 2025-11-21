<?php
/**
 * メール設定ファイル（エンタープライズレベル・お名前.com SD対応版）
 * 
 * このファイルには機密情報が含まれます。
 * public_htmlの外に配置するか、アクセス制限で保護してください。
 */

// ========================================
// メール設定
// ========================================

// 管理者のメールアドレス（お問い合わせを受信するアドレス）
define('ADMIN_EMAIL', 'design@sept3.co.jp');

// 送信元メールアドレス（実在するドメインのメールアドレスを指定）
define('MAIL_FROM', 'noreply@sept3.co.jp');

// メール送信方式の設定
define('MAIL_METHOD', 'mb_send_mail'); // 'mb_send_mail' or 'mail'

// ========================================
// セキュリティ設定
// ========================================

// Google reCAPTCHA v3の設定（推奨）
// https://www.google.com/recaptcha/admin から取得
define('RECAPTCHA_SITE_KEY', ''); // サイトキー（空の場合は無効）
define('RECAPTCHA_SECRET_KEY', ''); // シークレットキー
define('RECAPTCHA_THRESHOLD', 0.5); // スコア閾値（0.0-1.0、0.5推奨）

// IP制限設定
define('ENABLE_IP_RESTRICTION', false); // IPアドレス制限を有効化
define('ALLOWED_COUNTRIES', ['JP']); // 許可する国コード（ISO 3166-1 alpha-2）
define('IP_WHITELIST', []); // 常に許可するIPアドレス（例: ['192.168.1.1']）
define('IP_BLACKLIST', []); // 常にブロックするIPアドレス

// レート制限設定
define('RATE_LIMIT_COUNT', 3); // 制限回数
define('RATE_LIMIT_PERIOD', 3600); // 制限期間（秒）
define('RATE_LIMIT_STRICT_MODE', true); // 厳格モード（同一メールアドレスも制限）

// CSRF設定
define('CSRF_TOKEN_LIFETIME', 3600); // CSRFトークンの有効期限（秒）
define('DOUBLE_SUBMIT_PREVENTION', true); // 二重送信防止を有効化

// スパム対策
define('ENABLE_HONEYPOT', true); // ハニーポット有効化
define('ENABLE_TIMESTAMP_CHECK', true); // タイムスタンプチェック有効化
define('MIN_FORM_FILL_TIME', 3); // 最小入力時間（秒）
define('MAX_FORM_FILL_TIME', 3600); // 最大入力時間（秒）

// 入力検証設定
define('ENABLE_MX_VALIDATION', true); // MXレコード検証を有効化
define('MAX_EMAIL_LENGTH', 254); // メールアドレスの最大長
define('MAX_PHONE_LENGTH', 15); // 電話番号の最大長
define('MAX_NAME_LENGTH', 50); // 氏名の最大長
define('MAX_COMPANY_LENGTH', 100); // 会社名の最大長
define('MAX_CONTENT_LENGTH', 5000); // 内容の最大長

// 禁止ワードリスト
define('PROHIBITED_WORDS', [
    '<script', '</script>', 'javascript:', 'onclick', 'onerror', 
    '<iframe', '</iframe>', 'eval(', 'base64_decode', 'shell_exec',
    'system(', 'exec(', 'passthru(', 'popen(', '<embed', '<object'
]);

// 使い捨てメールドメインブラックリスト
define('DISPOSABLE_EMAIL_DOMAINS', [
    '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
    'tempmail.com', 'throwaway.email', 'temp-mail.org',
    'yopmail.com', 'sharklasers.com', 'guerrillamail.info'
]);

// ========================================
// ファイルパス設定
// ========================================

// セッション保存ディレクトリ
define('SESSION_SAVE_PATH', __DIR__ . '/tmp/sessions');

// ログ保存ディレクトリ
define('LOG_SAVE_PATH', __DIR__ . '/logs');

// 一時ファイル保存ディレクトリ
define('TMP_SAVE_PATH', __DIR__ . '/tmp');

// ========================================
// 監査ログ設定
// ========================================

define('ENABLE_AUDIT_LOG', true); // 監査ログを有効化
define('AUDIT_LOG_RETENTION_DAYS', 90); // 監査ログ保存日数

// ログに記録する情報
define('LOG_IP_ADDRESS', true); // IPアドレスを記録
define('LOG_USER_AGENT', true); // User-Agentを記録
define('LOG_REFERER', true); // Refererを記録
define('LOG_FORM_DATA', false); // フォームデータを記録（個人情報保護のため通常はfalse）

// ========================================
// システム設定
// ========================================

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 文字エンコーディング設定
mb_internal_encoding('UTF-8');
mb_language('Japanese');

// デバッグモード（本番環境では必ずfalseに設定）
define('DEBUG_MODE', false);

// エラー通知設定
define('ENABLE_ERROR_NOTIFICATION', false); // エラー発生時に管理者へ通知
define('ERROR_NOTIFICATION_EMAIL', ADMIN_EMAIL); // エラー通知先

// ========================================
// データベース設定（将来の拡張用）
// ========================================

/*
define('DB_HOST', 'localhost');
define('DB_NAME', 'database_name');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');
*/

// ========================================
// セキュリティヘッダー設定
// ========================================

define('SECURITY_HEADERS', [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com; frame-src https://www.google.com; connect-src 'self';",
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
]);

// ========================================
// セッション設定適用（本番環境対応版）
// ========================================

// セッション保存ディレクトリ
$sessionDir = __DIR__ . '/tmp/sessions';

// ディレクトリが存在しない場合は作成
if (!is_dir($sessionDir)) {
    if (!@mkdir($sessionDir, 0755, true)) {
        // 作成に失敗した場合は /tmp にフォールバック
        $sessionDir = sys_get_temp_dir() . '/my_app_sessions';
        if (!is_dir($sessionDir)) {
            @mkdir($sessionDir, 0755, true);
        }
    }
}

// ディレクトリが書き込み可能か確認
if (!is_writable($sessionDir)) {
    @chmod($sessionDir, 0755); // 0777から0755に変更（セキュリティ向上）
}

// セッション保存先を設定
@session_save_path($sessionDir);

// ★重要: デバッグモードによってエラー表示を制御
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
    // 開発環境: エラーを表示
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    // 本番環境: エラーを非表示
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ★重要: config.phpではセッションを開始しない
// セッションは各エンドポイント（get-csrf-token.php, contact-handler.php）で
// 必要に応じて開始する

?>
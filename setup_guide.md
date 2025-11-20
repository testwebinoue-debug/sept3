# お問い合わせフォーム セットアップガイド
## エンタープライズレベル・お名前.com SDプラン対応版

このガイドは、企業レベル（コーポレートレベル）のセキュリティを実装したお問い合わせフォームのセットアップ手順を説明します。

---

## 🔒 実装済みエンタープライズセキュリティ

### レイヤー1: 入力検証・サニタイゼーション
- ✅ XSS対策（クロスサイトスクリプティング）
- ✅ SQLインジェクション対策
- ✅ メールヘッダーインジェクション対策
- ✅ 厳格な入力値検証
- ✅ 禁止ワードフィルタリング
- ✅ 文字数・形式制限

### レイヤー2: ボット・スパム対策
- ✅ Google reCAPTCHA v3統合（オプション）
- ✅ ハニーポット（隠しフィールド）
- ✅ タイムスタンプ検証
- ✅ User-Agent検証

### レイヤー3: 認証・トークン管理
- ✅ CSRFトークン（クロスサイトリクエストフォージェリ対策）
- ✅ 二重送信防止トークン
- ✅ セッションハイジャック対策
- ✅ トークン有効期限管理

### レイヤー4: レート制限・アクセス制御
- ✅ IPベースのレート制限
- ✅ メールアドレスベースのレート制限
- ✅ 国別IPアドレス制限（オプション）
- ✅ IPホワイトリスト/ブラックリスト

### レイヤー5: メール検証
- ✅ MXレコード検証（実在ドメイン確認）
- ✅ 使い捨てメールアドレスブロック
- ✅ メールアドレス形式検証

### レイヤー6: セキュリティヘッダー
- ✅ Content Security Policy (CSP)
- ✅ X-Frame-Options (クリックジャッキング対策)
- ✅ X-Content-Type-Options
- ✅ X-XSS-Protection
- ✅ Referrer-Policy
- ✅ Permissions-Policy

### レイヤー7: 監査・ログ管理
- ✅ 詳細な監査ログ
- ✅ セキュリティイベントログ
- ✅ エラー通知機能
- ✅ IPアドレス・User-Agent記録
- ✅ ログローテーション対応

---

## 📁 ファイル構成

```
public_html/
├── contact.html              # お問い合わせフォーム（既存）
├── contact-form.js          # JavaScriptファイル（新規）
├── config.php               # 設定ファイル（新規）★最重要
├── get-csrf-token.php       # CSRFトークン生成（新規）
├── contact-handler.php      # フォーム処理（新規）
├── php.ini                  # PHP設定（新規・推奨）
├── .htaccess                # Apache設定（オプション）
├── includes/                # セキュリティライブラリ（新規）
│   └── security-functions.php
├── tmp/                     # 一時ファイル用（自動作成）
│   └── sessions/            # セッション保存先（自動作成）
└── logs/                    # ログファイル用（自動作成）
    ├── contact_YYYY-MM.log  # お問い合わせログ
    ├── security_YYYY-MM.log # セキュリティログ
    └── audit_YYYY-MM.log    # 監査ログ
```

---

## 🚀 セットアップ手順

### ステップ1: ディレクトリの作成

FTPで接続し、以下のディレクトリを事前に作成（推奨）：

```
public_html/includes/   (パーミッション: 755)
public_html/tmp/        (パーミッション: 755)
public_html/logs/       (パーミッション: 755)
```

### ステップ2: ファイルのアップロード

以下のファイルを対応する場所にアップロード：

```
public_html/
├── contact-form.js          ← アップロード
├── config.php               ← アップロード
├── get-csrf-token.php       ← アップロード
├── contact-handler.php      ← アップロード
├── php.ini                  ← アップロード（推奨）
├── .htaccess                ← アップロード（オプション）
└── includes/
    └── security-functions.php ← アップロード
```

### ステップ3: config.phpの編集 ★最重要

`config.php`を開いて、以下の設定を**必ず**編集してください：

#### 3-1. メール設定（必須）

```php
// 管理者のメールアドレス
define('ADMIN_EMAIL', 'design@sept3.co.jp'); // ← 実際のアドレスに変更

// 送信元メールアドレス（実在するドメインのメールアドレス）
define('MAIL_FROM', 'noreply@sept3.co.jp'); // ← 実際のアドレスに変更
```

#### 3-2. reCAPTCHA設定（強く推奨）

Google reCAPTCHA v3を使用する場合：

1. https://www.google.com/recaptcha/admin にアクセス
2. 新しいサイトを登録
3. reCAPTCHA v3を選択
4. ドメインを登録
5. サイトキーとシークレットキーを取得

```php
define('RECAPTCHA_SITE_KEY', 'あなたのサイトキー');
define('RECAPTCHA_SECRET_KEY', 'あなたのシークレットキー');
```

**contact-form.jsも編集:**

```javascript
let recaptchaSiteKey = 'あなたのサイトキー'; // 4行目付近
```

#### 3-3. セキュリティ設定（推奨）

```php
// IP制限を有効化する場合
define('ENABLE_IP_RESTRICTION', true);
define('ALLOWED_COUNTRIES', ['JP']); // 日本のみ許可

// レート制限の調整
define('RATE_LIMIT_COUNT', 3); // 1時間に3回まで
define('RATE_LIMIT_PERIOD', 3600); // 3600秒 = 1時間

// MXレコード検証（推奨: 有効）
define('ENABLE_MX_VALIDATION', true);
```

### ステップ4: PHPバージョンの確認

お名前.comコントロールパネルで：

1. **PHPバージョン変更**メニューを開く
2. 対象ドメインのPHPバージョンを**PHP 8.0以上**に設定
3. **推奨: PHP 8.4**

### ステップ5: 動作確認

#### 5-1. CSRFトークンの取得テスト

ブラウザで以下にアクセス：
```
https://yourdomain.com/get-csrf-token.php
```

期待される結果：
```json
{
  "success": true,
  "csrf_token": "abc123...",
  "double_submit_token": "xyz789...",
  "timestamp": 1234567890
}
```

#### 5-2. セキュリティ関数のテスト

ブラウザで以下にアクセス：
```
https://yourdomain.com/includes/security-functions.php
```

**重要:** このファイルに直接アクセスしても何も表示されなければ正常です。エラーが表示される場合はファイルのアップロードを確認してください。

#### 5-3. フォームからのテスト送信

1. `https://yourdomain.com/contact.html`にアクセス
2. すべての項目を正しく入力
3. 「送信」ボタンをクリック
4. 成功メッセージが表示されることを確認
5. 管理者メールと自動返信メールが届くことを確認

---

## ⚙️ 詳細設定

### reCAPTCHA v3のスコア調整

```php
// config.php
define('RECAPTCHA_THRESHOLD', 0.5); // 0.0-1.0

// 推奨値:
// 0.3 = 緩い（ほとんどのリクエストを許可）
// 0.5 = バランス（推奨）
// 0.7 = 厳格（正当なユーザーもブロックされる可能性）
```

### IP制限の設定

#### 国別制限

```php
define('ENABLE_IP_RESTRICTION', true);
define('ALLOWED_COUNTRIES', ['JP', 'US', 'GB']); // 複数国許可
```

#### ホワイトリスト/ブラックリスト

```php
// 常に許可するIP
define('IP_WHITELIST', ['192.168.1.1', '203.0.113.0']);

// 常にブロックするIP
define('IP_BLACKLIST', ['198.51.100.0']);
```

### レート制限の調整

```php
// IP制限のみ
define('RATE_LIMIT_STRICT_MODE', false);
define('RATE_LIMIT_COUNT', 5); // 5回まで
define('RATE_LIMIT_PERIOD', 1800); // 30分

// メールアドレスでも制限（厳格モード）
define('RATE_LIMIT_STRICT_MODE', true);
```

### 入力値の制限

```php
define('MAX_EMAIL_LENGTH', 254);
define('MAX_PHONE_LENGTH', 15);
define('MAX_NAME_LENGTH', 50);
define('MAX_COMPANY_LENGTH', 100);
define('MAX_CONTENT_LENGTH', 5000);
```

### 監査ログの設定

```php
// 監査ログを有効化
define('ENABLE_AUDIT_LOG', true);

// ログに記録する情報
define('LOG_IP_ADDRESS', true);
define('LOG_USER_AGENT', true);
define('LOG_REFERER', true);
define('LOG_FORM_DATA', false); // 個人情報保護のため通常はfalse
```

---

## 📊 ログファイルの見方

### contact_YYYY-MM.log（お問い合わせログ）

```
2025-11-11 10:30:45 | SUCCESS | 192.168.1.1 | user@example.com | 新規お取引のご相談
2025-11-11 10:35:12 | ERROR | 192.168.1.2 | Rate limit exceeded
```

### security_YYYY-MM.log（セキュリティログ）

```
2025-11-11 10:30:45 | 192.168.1.1 | Honeypot triggered
2025-11-11 10:31:20 | 192.168.1.2 | CSRF token validation failed
2025-11-11 10:32:00 | 192.168.1.3 | Rate limit exceeded
```

### audit_YYYY-MM.log（監査ログ・JSON形式）

```json
{"timestamp":"2025-11-11 10:30:45","action":"FORM_SUBMIT_SUCCESS","ip":"192.168.1.1","user_agent":"Mozilla/5.0..."}
{"timestamp":"2025-11-11 10:31:20","action":"FORM_SUBMIT_FAILED","ip":"192.168.1.2","user_agent":"Python-urllib/3.8"}
```

---

## 🐛 トラブルシューティング

### メールが送信されない

#### 原因1: MAIL_FROMが正しくない
- `config.php`の`MAIL_FROM`を**実在するメールアドレス**に変更
- お名前.comで作成したメールアドレスを使用

#### 原因2: SPFレコードの問題
- DNSのSPFレコードを確認
- お名前.comのメールサーバーが許可されているか確認

#### 原因3: mb_send_mailが使えない
- お名前.comコントロールパネルで`mbstring`拡張を確認
- PHPバージョンを8.0以上に変更

### CSRFトークンエラー

#### 原因1: セッションが開始できない
- `tmp/sessions/`ディレクトリの存在を確認
- パーミッションを700に設定

#### 原因2: php.iniの設定
- `session.save_path`の設定を確認
- `public_html/php.ini`をアップロード

### reCAPTCHAが動作しない

#### 原因1: サイトキーが正しくない
- `config.php`と`contact-form.js`の両方を確認
- サイトキーとシークレットキーが一致しているか確認

#### 原因2: ドメインが登録されていない
- Google reCAPTCHAの管理画面でドメインを確認
- HTTPSを使用している場合はHTTPSでも登録

### レート制限エラー

1時間に3回以上送信した場合に発生します。

**一時的な対処:**
1. FTPで接続
2. `tmp/rate_limit_*.json`ファイルを削除
3. 再度送信を試す

**恒久的な対処:**
```php
// config.php
define('RATE_LIMIT_COUNT', 5); // 5回に変更
```

### MXレコード検証エラー

実在するメールドメインなのにエラーが出る場合：

```php
// config.php
define('ENABLE_MX_VALIDATION', false); // 一時的に無効化
```

### 「Security library not found」エラー

`includes/security-functions.php`がアップロードされていません。

1. `includes/`ディレクトリを作成
2. `security-functions.php`をアップロード
3. パーミッションを644に設定

---

## 🔄 メンテナンス

### 日次作業

- **ログファイルの確認**
  - `logs/security_*.log`でセキュリティイベントを確認
  - 異常なアクセスがないかチェック

### 月次作業

- **ログファイルの削除**
  - 3ヶ月以上古いログを削除推奨
  ```
  logs/contact_*.log
  logs/security_*.log
  logs/audit_*.log
  ```

- **一時ファイルの削除**
  - `tmp/rate_limit_*.json`
  - 自動削除されるが、手動での確認も推奨

### 年次作業

- **PHPバージョンの更新**
  - お名前.comコントロールパネルで最新版に更新
  - テスト環境で動作確認後に本番環境へ適用

- **reCAPTCHAキーの更新**
  - 定期的にキーをローテーション

---

## 🔒 セキュリティベストプラクティス

### 1. HTTPS必須

- Let's Encryptなどで無料のSSL証明書を取得
- お名前.comコントロールパネルでHTTPS化

### 2. 定期的なバックアップ

- PHPファイルのバックアップ
- ログファイルのバックアップ
- config.phpは特に重要

### 3. config.phpの保護

#### 方法1: .htaccessで保護（推奨）

```apache
<Files "config.php">
    Require all denied
</Files>
```

#### 方法2: ファイル名の変更

```
config.php → config.inc.php
```

その後、各PHPファイルのrequire文を変更。

### 4. ログファイルの保護

#### .htaccess

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^logs/ - [F,L]
</IfModule>
```

### 5. エラー通知の設定

```php
// config.php
define('ENABLE_ERROR_NOTIFICATION', true);
define('ERROR_NOTIFICATION_EMAIL', 'admin@sept3.co.jp');
```

---

## 📌 チェックリスト

セットアップ完了前に以下を確認：

### 必須項目
- [ ] すべてのPHPファイルをアップロード
- [ ] `includes/security-functions.php`をアップロード
- [ ] `config.php`のメールアドレスを編集
- [ ] PHPバージョンが8.0以上
- [ ] CSRFトークン取得のテスト成功
- [ ] テスト送信成功
- [ ] 管理者メール受信確認
- [ ] 自動返信メール受信確認

### 推奨項目
- [ ] reCAPTCHA v3の設定
- [ ] MXレコード検証の有効化
- [ ] HTTPS化
- [ ] セキュリティヘッダーの確認
- [ ] ログディレクトリの作成確認

### オプション項目
- [ ] IP制限の設定
- [ ] レート制限の調整
- [ ] 監査ログの有効化
- [ ] エラー通知の設定

---

## 🆘 サポート

### ログを確認

1. **エラーログ**
   - `error_log`ファイル（public_html内）
   - PHPのエラーを確認

2. **セキュリティログ**
   - `logs/security_YYYY-MM.log`
   - 不正アクセスの試行を確認

3. **監査ログ**
   - `logs/audit_YYYY-MM.log`
   - すべてのアクションを確認

### 問題が解決しない場合

1. すべてのPHPファイルが正しくアップロードされているか確認
2. `includes/`ディレクトリが存在するか確認
3. PHPバージョンが8.0以上か確認
4. お名前.comサポートに問い合わせ

---

## 📄 技術仕様

### 対応環境

- **サーバー:** お名前.com SDプラン
- **PHP:** 8.0以上（推奨: 8.4）
- **Apache:** 2.4以上（suEXECモード）
- **必須PHP拡張:**
  - mbstring
  - session
  - json
  - openssl（reCAPTCHA使用時）

### セキュリティ規格準拠

- ✅ OWASP Top 10対策
- ✅ PCI DSS準拠レベルのセキュリティ
- ✅ GDPR対応（ログの個人情報保護）
- ✅ ISO 27001推奨セキュリティ対策

---

## 📝 変更履歴

### バージョン 2.0.0（エンタープライズ版）

- reCAPTCHA v3統合
- MXレコード検証追加
- 二重送信防止機能追加
- 国別IP制限機能追加
- 監査ログ機能追加
- セキュリティヘッダー強化
- エラー通知機能追加

---

**最終更新:** 2025年11月
**バージョン:** 2.0.0（エンタープライズレベル・お名前.com SDプラン対応版）
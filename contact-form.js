/**
 * お問い合わせフォーム用JavaScript（エンタープライズレベル）
 */

// グローバル変数
let selectedInquiryType = 'consultation';
let csrfToken = null;
let doubleSubmitToken = null;
let formTimestamp = null;
let recaptchaSiteKey = ''; // config.phpのRECAPTCHA_SITE_KEYと同じ値を設定

/**
 * reCAPTCHA v3の読み込み
 */
function loadRecaptcha() {
    if (!recaptchaSiteKey) return;
    
    const script = document.createElement('script');
    script.src = `https://www.google.com/recaptcha/api.js?render=${recaptchaSiteKey}`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

/**
 * reCAPTCHAトークンの取得
 */
async function getRecaptchaToken() {
    if (!recaptchaSiteKey || !window.grecaptcha) {
        return null;
    }
    
    try {
        await window.grecaptcha.ready();
        const token = await window.grecaptcha.execute(recaptchaSiteKey, { action: 'submit' });
        return token;
    } catch (error) {
        console.error('reCAPTCHA error:', error);
        return null;
    }
}

/**
 * CSRFトークンの取得
 */
async function fetchCSRFToken() {
    try {
        const response = await fetch('/get-csrf-token.php', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            console.error('CSRF token fetch failed:', response.status);
            return false;
        }

        const data = await response.json();
        if (data.success && data.csrf_token) {
            csrfToken = data.csrf_token;
            // ここで二重送信トークンを取得
            doubleSubmitToken = data.double_submit_token || null;
            formTimestamp = data.timestamp;
            return true;
        } else {
            console.error('Invalid CSRF token response');
            return false;
        }
    } catch (error) {
        console.error('CSRF token fetch error:', error);
        return false;
    }
}

/**
 * お問い合わせタイプの選択
 */
function selectInquiryType(type) {
    selectedInquiryType = type;
    
    const consultationButton = document.getElementById('radioConsultation');
    const otherButton = document.getElementById('radioOther');
    
    if (!consultationButton || !otherButton) return;
    
    const consultationSvg = consultationButton.querySelector('.radio-svg');
    const otherSvg = otherButton.querySelector('.radio-svg');
    
    if (type === 'consultation') {
        consultationSvg.innerHTML = `
            <circle cx="9" cy="9" fill="#3f3d3d" r="9" />
            <circle cx="9" cy="9" fill="white" r="4" />
        `;
        otherSvg.innerHTML = `
            <circle cx="9" cy="9" fill="#D9D9D9" r="9" />
        `;
    } else {
        consultationSvg.innerHTML = `
            <circle cx="9" cy="9" fill="#D9D9D9" r="9" />
        `;
        otherSvg.innerHTML = `
            <circle cx="9" cy="9" fill="#3f3d3d" r="9" />
            <circle cx="9" cy="9" fill="white" r="4" />
        `;
    }
}

/**
 * 入力値のバリデーション（強化版）
 */
function validateForm(formData) {
    const errors = [];
    
    // 姓名チェック
    if (!formData.lastName || formData.lastName.trim() === '') {
        errors.push('姓を入力してください');
    } else if (formData.lastName.length > 50) {
        errors.push('姓は50文字以内で入力してください');
    }
    
    if (!formData.firstName || formData.firstName.trim() === '') {
        errors.push('名を入力してください');
    } else if (formData.firstName.length > 50) {
        errors.push('名は50文字以内で入力してください');
    }
    
    // フリガナチェック
    if (!formData.lastNameKana || formData.lastNameKana.trim() === '') {
        errors.push('フリガナ（姓）を入力してください');
    }
    if (!formData.firstNameKana || formData.firstNameKana.trim() === '') {
        errors.push('フリガナ（名）を入力してください');
    }
    
    // フリガナがカタカナかチェック
    const kanaRegex = /^[ァ-ヶー\s]+$/;
    if (formData.lastNameKana && !kanaRegex.test(formData.lastNameKana)) {
        errors.push('フリガナ（姓）はカタカナで入力してください');
    }
    if (formData.firstNameKana && !kanaRegex.test(formData.firstNameKana)) {
        errors.push('フリガナ（名）はカタカナで入力してください');
    }
    
    // 会社名の長さチェック
    if (formData.company && formData.company.length > 100) {
        errors.push('会社名は100文字以内で入力してください');
    }
    
    // 電話番号チェック
    if (!formData.phone || formData.phone.trim() === '') {
        errors.push('電話番号を入力してください');
    } else {
        const phoneRegex = /^0\d{9,10}$/;
        const phoneClean = formData.phone.replace(/[-\s]/g, '');
        if (!phoneRegex.test(phoneClean)) {
            errors.push('電話番号の形式が正しくありません（例: 06-1234-5678）');
        }
        if (phoneClean.length > 15) {
            errors.push('電話番号が長すぎます');
        }
    }
    
    // メールアドレスチェック
    if (!formData.email || formData.email.trim() === '') {
        errors.push('メールアドレスを入力してください');
    } else {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email)) {
            errors.push('メールアドレスの形式が正しくありません');
        }
        if (formData.email.length > 254) {
            errors.push('メールアドレスが長すぎます');
        }
        
        // 使い捨てメールのチェック
        const disposableDomains = [
            '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
            'tempmail.com', 'throwaway.email', 'temp-mail.org',
            'yopmail.com', 'sharklasers.com'
        ];
        const domain = formData.email.split('@')[1]?.toLowerCase();
        if (domain && disposableDomains.includes(domain)) {
            errors.push('使い捨てメールアドレスは使用できません');
        }
    }
    
    // お問い合わせ内容チェック
    if (!formData.content || formData.content.trim() === '') {
        errors.push('お問い合わせ内容を入力してください');
    } else if (formData.content.trim().length < 10) {
        errors.push('お問い合わせ内容は10文字以上入力してください');
    } else if (formData.content.length > 5000) {
        errors.push('お問い合わせ内容は5000文字以内で入力してください');
    }
    
    // 禁止ワードのチェック
    const prohibitedWords = [
        '<script', '</script>', 'javascript:', 'onclick', 'onerror',
        '<iframe', '</iframe>', 'eval(', 'base64_decode', '<embed'
    ];
    const allText = [formData.company, formData.lastName, formData.firstName, formData.content].join(' ').toLowerCase();
    for (const word of prohibitedWords) {
        if (allText.includes(word.toLowerCase())) {
            errors.push('不正な文字列が含まれています');
            break;
        }
    }
    
    return errors;
}

/**
 * 通知メッセージの表示
 */
function showNotification(message, type = 'success') {
    // 既存の通知があれば削除
    const existingNotification = document.getElementById('formNotification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // 通知要素の作成
    const notification = document.createElement('div');
    notification.id = 'formNotification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        padding: 16px 24px;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        max-width: 90%;
        font-size: 16px;
        font-family: 'Zen Kaku Gothic Antique', sans-serif;
        animation: slideDown 0.3s ease-out;
        white-space: pre-line;
        line-height: 1.5;
    `;
    
    // アニメーションのスタイルを追加
    if (!document.getElementById('notificationStyles')) {
        const style = document.createElement('style');
        style.id = 'notificationStyles';
        style.textContent = `
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // 5秒後に自動で削除
    setTimeout(() => {
        notification.style.animation = 'slideDown 0.3s ease-out reverse';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

/**
 * ローディング状態の切り替え
 */
function setLoadingState(isLoading, button) {
    if (isLoading) {
        button.disabled = true;
        button.style.opacity = '0.6';
        button.style.cursor = 'not-allowed';
        const submitText = button.querySelector('.submit-text');
        if (submitText) {
            submitText.textContent = '送信中...';
        }
    } else {
        button.disabled = false;
        button.style.opacity = '1';
        button.style.cursor = 'pointer';
        const submitText = button.querySelector('.submit-text');
        if (submitText) {
            submitText.textContent = '送信';
        }
    }
}

/**
 * フォーム送信処理（エンタープライズレベル）- 修正版
 */
async function handleFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    
    // CSRFトークンのチェック
    if (!csrfToken || !formTimestamp) {
        const tokenObtained = await fetchCSRFToken();
        if (!tokenObtained) {
            showNotification('セキュリティトークンの取得に失敗しました。ページを再読み込みしてください。', 'error');
            return;
        }
    }
    
    // フォームデータの取得
    const formData = {
        csrf_token: csrfToken,
        timestamp: formTimestamp,
        inquiryType: selectedInquiryType,
        company: form.company.value.trim(),
        lastName: form.lastName.value.trim(),
        firstName: form.firstName.value.trim(),
        lastNameKana: form.lastNameKana.value.trim(),
        firstNameKana: form.firstNameKana.value.trim(),
        phone: form.phone.value.trim().replace(/[-\s]/g, ''),
        email: form.email.value.trim().toLowerCase(),
        content: form.content.value.trim(),
        website: '' // ハニーポット
    };
    
    // 二重送信防止トークンを付与（存在する場合のみ）
    if (doubleSubmitToken) {
        formData.double_submit_token = doubleSubmitToken;
    }
    
    // バリデーション
    const errors = validateForm({
        lastName: form.lastName.value.trim(),
        firstName: form.firstName.value.trim(),
        lastNameKana: form.lastNameKana.value.trim(),
        firstNameKana: form.firstNameKana.value.trim(),
        company: form.company.value.trim(),
        phone: form.phone.value.trim(),
        email: form.email.value.trim(),
        content: form.content.value.trim()
    });
    
    if (errors.length > 0) {
        showNotification(errors.join('\n'), 'error');
        return;
    }
    
    // ローディング状態に設定
    setLoadingState(true, submitButton);
    
    try {
        // reCAPTCHAトークンの取得
        if (recaptchaSiteKey) {
            const recaptchaToken = await getRecaptchaToken();
            if (recaptchaToken) {
                formData.recaptcha_token = recaptchaToken;
            }
        }
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000);
        
        const response = await fetch('/contact-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(formData),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            showNotification(data.message, 'success');
            form.reset();
            selectInquiryType('consultation');
            
            // ★重要：新しいトークンを取得してから次の送信を許可
            // トークンをクリアして、次回送信時に再取得させる
            csrfToken = null;
            doubleSubmitToken = null;
            formTimestamp = null;
            
            // すぐに新しいトークンを取得
            await fetchCSRFToken();
            
            // スクロールをトップに
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            if (response.status === 429) {
                showNotification('送信回数の上限に達しました。1時間後に再度お試しください。', 'error');
            } else if (response.status === 403) {
                showNotification('アクセスが拒否されました。', 'error');
            } else {
                showNotification(data.message || '送信に失敗しました', 'error');
                
                // エラー時もトークンを再取得
                csrfToken = null;
                doubleSubmitToken = null;
                formTimestamp = null;
                await fetchCSRFToken();
            }
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            showNotification('送信がタイムアウトしました。時間をおいて再度お試しください。', 'error');
        } else {
            console.error('Form submission error:', error);
            showNotification('送信中にエラーが発生しました。時間をおいて再度お試しください。', 'error');
        }
        
        // エラー時もトークンを再取得
        csrfToken = null;
        doubleSubmitToken = null;
        formTimestamp = null;
        await fetchCSRFToken();
    } finally {
        setLoadingState(false, submitButton);
    }
}

/**
 * 初期化処理
 */
document.addEventListener('DOMContentLoaded', async function() {
    // reCAPTCHAの読み込み
    loadRecaptcha();
    
    // CSRFトークンの取得
    await fetchCSRFToken();
    
    // フォームの送信イベント
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', handleFormSubmit);
    }
    
    // ラジオボタンのイベント
    const radioConsultation = document.getElementById('radioConsultation');
    const radioOther = document.getElementById('radioOther');
    
    if (radioConsultation) {
        radioConsultation.addEventListener('click', () => selectInquiryType('consultation'));
    }
    
    if (radioOther) {
        radioOther.addEventListener('click', () => selectInquiryType('other'));
    }
});

/**
 * グローバルスコープに関数を公開
 */
window.selectInquiryType = selectInquiryType;
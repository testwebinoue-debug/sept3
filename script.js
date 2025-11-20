// ハンバーガーメニューの開閉
function toggleMobileMenu() {
  const menu = document.getElementById('mobileMenu');
  const hamburgerIcon = document.getElementById('hamburgerIcon');
  const closeIcon = document.getElementById('closeIcon');
  const headerMobile = document.getElementById('headerMobile');
  
  if (menu && hamburgerIcon && closeIcon) {
    const isOpen = menu.style.display === 'block';
    
    if (isOpen) {
      // メニューを閉じる
      menu.style.display = 'none';
      hamburgerIcon.style.display = 'block';
      closeIcon.style.display = 'none';
      if (headerMobile) headerMobile.classList.remove('menu-open');
      document.body.style.overflow = '';
    } else {
      // メニューを開く
      menu.style.display = 'block';
      hamburgerIcon.style.display = 'none';
      closeIcon.style.display = 'block';
      if (headerMobile) headerMobile.classList.add('menu-open');
      document.body.style.overflow = 'hidden';
    }
  }
}

// ページ遷移とスクロール
function navigateTo(page, section) {
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  
  if (page === currentPage) {
    // 同じページ内でスクロール
    const element = document.getElementById(section);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
      // スクロール後にURLからハッシュを削除
      removeHashFromUrl();
    }
  } else {
    // 別ページに遷移
    window.location.href = page + '#' + section;
  }
}

// メニューを閉じてからページ遷移
function navigateToAndClose(page, section) {
  toggleMobileMenu();
  setTimeout(() => navigateTo(page, section), 300);
}

// URLからハッシュを削除する関数
function removeHashFromUrl() {
  if (window.history && window.history.replaceState) {
    const cleanUrl = window.location.pathname + window.location.search;
    window.history.replaceState(null, '', cleanUrl);
  }
}

// ページ読み込み時にハッシュがあればスクロール
window.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash.substring(1);
  if (hash) {
    setTimeout(() => {
      const element = document.getElementById(hash);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        // スクロール後にハッシュを削除
        removeHashFromUrl();
      }
    }, 100);
  }
});

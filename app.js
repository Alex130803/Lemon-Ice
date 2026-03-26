/**
 * Panifit — Shared Application JavaScript
 * Cart system, navigation, dark mode, and utilities
 */

// ===== DARK MODE =====
function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('panifit_theme', next);
}

// Apply saved theme on load
(function initTheme() {
  const saved = localStorage.getItem('panifit_theme');
  if (saved === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
  }
})();

// ===== NAVBAR =====
window.addEventListener('scroll', () => {
  const nav = document.getElementById('navbar');
  if (nav) nav.classList.toggle('scrolled', window.scrollY > 50);
});

function toggleMenu() {
  const links = document.getElementById('navLinks');
  if (links) links.classList.toggle('open');
}
// Close mobile menu on link click
document.querySelectorAll('.nav-links a').forEach(a => {
  a.addEventListener('click', () => {
    const links = document.getElementById('navLinks');
    if (links) links.classList.remove('open');
  });
});

// ===== SCROLL REVEAL =====
(function initScrollReveal() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1';
        e.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.benefit-card, .step, .testimonial, .audience-card, .explain-features li, .faq-item').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
})();


// ===== CART SYSTEM (localStorage) =====
const CART_KEY = 'panifit_cart';

function getCart() {
  try {
    return JSON.parse(localStorage.getItem(CART_KEY)) || [];
  } catch {
    return [];
  }
}

function saveCart(cart) {
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  updateCartCount();
  renderCartDrawer();
}

function addToCart(item) {
  const cart = getCart();
  const existing = cart.find(i => i.id === item.id);
  if (existing) {
    existing.qty += item.qty;
  } else {
    cart.push(item);
  }
  saveCart(cart);
  showToast(item.name + ' added to cart');
}

function removeFromCart(index) {
  const cart = getCart();
  cart.splice(index, 1);
  saveCart(cart);
}

function updateCartCount() {
  const cart = getCart();
  const count = cart.reduce((sum, i) => sum + i.qty, 0);
  const el = document.getElementById('cartCount');
  if (el) {
    el.textContent = count;
    el.classList.toggle('visible', count > 0);
  }
}

// ===== CART DRAWER =====
function toggleCart() {
  const overlay = document.getElementById('cartOverlay');
  if (overlay) overlay.classList.toggle('open');
}

document.addEventListener('click', (e) => {
  if (e.target && e.target.id === 'cartOverlay') {
    toggleCart();
  }
});

function renderCartDrawer() {
  const itemsEl = document.getElementById('cartItems');
  const footerEl = document.getElementById('cartFooter');
  if (!itemsEl) return;

  const cart = getCart();

  if (cart.length === 0) {
    itemsEl.innerHTML = '<div class="cart-empty"><div class="cart-empty-icon"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="24" cy="54" r="3"/><circle cx="48" cy="54" r="3"/><path d="M4 4h8l6 32h32l6-24H18"/></svg></div><p>Your cart is empty.<br><a href="products.html" style="color:var(--yellow-dark);">Add something refreshing</a></p></div>';
    if (footerEl) footerEl.innerHTML = '';
    return;
  }

  const cubeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M4 12h16" opacity="0.3"/><path d="M12 4v16" opacity="0.3"/></svg>';
  const removeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';

  itemsEl.innerHTML = '<div class="cart-items-list">' + cart.map((item, idx) => `
    <div class="cart-item">
      <div class="cart-item-img">${cubeIcon}</div>
      <div class="cart-item-info">
        <div class="cart-item-name">${item.name}</div>
        <div class="cart-item-detail">${item.detail} x${item.qty}</div>
      </div>
      <div class="cart-item-right">
        <div class="cart-item-price">$${(item.price * item.qty).toFixed(2)}</div>
        <button class="remove-btn" onclick="removeFromCart(${idx})" aria-label="Remove">${removeIcon}</button>
      </div>
    </div>
  `).join('') + '</div>';

  const subtotal = cart.reduce((sum, i) => sum + i.price * i.qty, 0);
  const lockIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>';

  if (footerEl) {
    footerEl.innerHTML = `
      <div class="cart-subtotal">
        <span class="cart-subtotal-label">Subtotal</span>
        <span class="cart-subtotal-price">$${subtotal.toFixed(2)}</span>
      </div>
      ${subtotal >= 30 ? '<div style="text-align:center;color:var(--green);font-size:12px;font-weight:600;margin-top:8px;">You qualify for FREE shipping!</div>' : '<div style="text-align:center;color:var(--text-muted);font-size:12px;margin-top:8px;">Add $' + (30 - subtotal).toFixed(2) + ' more for free shipping</div>'}
      <a href="checkout.html" class="cart-checkout-btn">
        Checkout <span style="font-family:'DM Sans',sans-serif;font-size:14px;letter-spacing:0;">&rarr;</span>
      </a>
      <div class="cart-secure-note">
        ${lockIcon}
        Secure checkout &middot; Free shipping $30+ &middot; Ships in 24h
      </div>
    `;
  }
}


// ===== TOAST =====
function showToast(msg) {
  const toast = document.getElementById('toast');
  const msgEl = document.getElementById('toastMsg');
  if (!toast || !msgEl) return;
  msgEl.textContent = msg;
  toast.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}


// ===== INIT =====
updateCartCount();
renderCartDrawer();

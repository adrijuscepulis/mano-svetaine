<?php
// =========================================================================
// ThrowHouse Šiauliai - Baras & Užkandžiai (Kliento Meniu)
// =========================================================================

date_default_timezone_set('Europe/Vilnius');
require_once __DIR__ . '/baras_helper.php';

$products = loadBarProducts();

// Filtruojame tik aktyvias prekes klientams
$activeProducts = array_filter($products, function($p) {
    return !empty($p['active']);
});

$categories = ['Visi', 'Gėrimai', 'Užkandžiai'];
?>
<!DOCTYPE html>
<html lang="lt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Baras & Užkandžiai | ThrowHouse Šiauliai</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #0c0a09;
      --card-bg: #14110e;
      --card-border: rgba(229, 142, 38, 0.25);
      --accent: #e58e26;
      --accent-hover: #f59e0b;
      --text: #fdfaf6;
      --muted: #94a3b8;
      --surface: #1a1612;
      --border: rgba(255, 255, 255, 0.08);
      --radius: 14px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
      min-height: 100vh;
      padding-bottom: 110px;
      background-image: 
        radial-gradient(ellipse 90% 40% at 50% -10%, rgba(229, 142, 38, 0.15), transparent),
        radial-gradient(circle at 80% 90%, rgba(194, 65, 12, 0.06), transparent);
      background-attachment: fixed;
    }
    
    /* Header */
    .bar-header {
      background: rgba(12, 10, 9, 0.88);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      position: sticky;
      top: 0;
      z-index: 50;
      border-bottom: 1px solid var(--border);
      padding: 0.85rem 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .brand-wrap {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: inherit;
    }
    .brand-icon {
      font-size: 1.8rem;
      line-height: 1;
      filter: drop-shadow(0 2px 6px rgba(229,142,38,0.4));
    }
    .brand-title {
      font-family: 'Oswald', sans-serif;
      font-size: 1.2rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      line-height: 1.1;
      text-transform: uppercase;
    }
    .brand-title span {
      color: var(--accent);
    }
    .brand-sub {
      font-size: 0.72rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 600;
    }
    .header-badge {
      background: rgba(229, 142, 38, 0.12);
      border: 1px solid rgba(229, 142, 38, 0.3);
      color: var(--accent);
      font-size: 0.75rem;
      font-weight: 700;
      padding: 0.35rem 0.65rem;
      border-radius: 20px;
      display: flex;
      align-items: center;
      gap: 0.35rem;
    }
    .header-badge .dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 8px #22c55e;
    }

    /* Hero Banner */
    .hero-banner {
      max-width: 680px;
      margin: 1rem auto 0 auto;
      padding: 0 1rem;
    }
    .banner-card {
      background: linear-gradient(135deg, rgba(229, 142, 38, 0.12) 0%, rgba(26, 22, 18, 0.85) 100%);
      border: 1px solid var(--card-border);
      border-radius: var(--radius);
      padding: 1.15rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    }
    .banner-emoji {
      font-size: 2.2rem;
      flex-shrink: 0;
    }
    .banner-text h1 {
      font-family: 'Oswald', sans-serif;
      font-size: 1.35rem;
      font-weight: 700;
      text-transform: uppercase;
      color: #fff;
      line-height: 1.2;
      margin-bottom: 0.2rem;
    }
    .banner-text p {
      font-size: 0.82rem;
      color: #cbd5e1;
      line-height: 1.4;
    }

    /* Category Filter Tabs */
    .category-nav {
      max-width: 680px;
      margin: 1.25rem auto 0 auto;
      padding: 0 1rem;
      display: flex;
      gap: 0.5rem;
      overflow-x: auto;
      scrollbar-width: none;
    }
    .category-nav::-webkit-scrollbar { display: none; }
    .cat-pill {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--border);
      color: #cbd5e1;
      padding: 0.55rem 1.1rem;
      border-radius: 25px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }
    .cat-pill.active {
      background: var(--accent);
      color: #0c0a09;
      border-color: var(--accent);
      font-weight: 800;
      box-shadow: 0 4px 14px rgba(229, 142, 38, 0.35);
    }

    /* Products Grid */
    .products-container {
      max-width: 680px;
      margin: 1.25rem auto 0 auto;
      padding: 0 1rem;
    }
    .section-title {
      font-family: 'Oswald', sans-serif;
      font-size: 1.15rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #cbd5e1;
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .products-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.85rem;
    }
    @media (max-width: 380px) {
      .products-grid {
        grid-template-columns: 1fr;
      }
    }
    .product-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.15s, border-color 0.15s;
      position: relative;
    }
    .product-card:hover {
      border-color: rgba(229, 142, 38, 0.45);
    }
    .product-badge-wrap {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 0.5rem;
    }
    .product-icon {
      font-size: 2.2rem;
      line-height: 1;
      margin-bottom: 0.35rem;
    }
    .product-category-tag {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--muted);
      background: rgba(255, 255, 255, 0.04);
      padding: 0.2rem 0.5rem;
      border-radius: 6px;
    }
    .product-name {
      font-size: 0.95rem;
      font-weight: 700;
      color: #fff;
      line-height: 1.3;
      margin-bottom: 0.4rem;
    }
    .product-price {
      font-family: 'Oswald', sans-serif;
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 0.75rem;
    }
    
    /* Quantity Controls */
    .btn-add-item {
      background: rgba(229, 142, 38, 0.12);
      border: 1.5px solid rgba(229, 142, 38, 0.4);
      color: var(--accent);
      width: 100%;
      padding: 0.55rem;
      border-radius: 10px;
      font-size: 0.88rem;
      font-weight: 800;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      transition: all 0.2s;
    }
    .btn-add-item:active {
      background: var(--accent);
      color: #0c0a09;
      transform: scale(0.97);
    }
    .qty-controls {
      display: none;
      align-items: center;
      justify-content: space-between;
      background: var(--surface);
      border: 1.5px solid var(--accent);
      border-radius: 10px;
      padding: 0.25rem;
    }
    .qty-btn {
      background: rgba(255, 255, 255, 0.08);
      border: none;
      color: #fff;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      font-size: 1.15rem;
      font-weight: 800;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.15s;
    }
    .qty-btn:active {
      background: var(--accent);
      color: #0c0a09;
    }
    .qty-val {
      font-family: 'Oswald', sans-serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: #fff;
      min-width: 28px;
      text-align: center;
    }

    /* Sticky Bottom Bar */
    .bottom-bar {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(18, 15, 12, 0.95);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-top: 1px solid rgba(229, 142, 38, 0.35);
      padding: 0.85rem 1rem;
      z-index: 100;
      box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.7);
      transform: translateY(120%);
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .bottom-bar.visible {
      transform: translateY(0);
    }
    .bottom-bar-inner {
      max-width: 680px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    .bar-total-info {
      display: flex;
      flex-direction: column;
    }
    .bar-items-count {
      font-size: 0.78rem;
      color: var(--muted);
      font-weight: 600;
    }
    .bar-total-price {
      font-family: 'Oswald', sans-serif;
      font-size: 1.45rem;
      font-weight: 800;
      color: var(--accent);
      line-height: 1;
    }
    .btn-checkout {
      background: linear-gradient(135deg, #e58e26 0%, #d97706 100%);
      border: none;
      color: #0c0a09;
      padding: 0.85rem 1.5rem;
      border-radius: 12px;
      font-size: 0.98rem;
      font-weight: 800;
      letter-spacing: 0.3px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 16px rgba(229, 142, 38, 0.4);
      transition: all 0.15s;
    }
    .btn-checkout:active {
      transform: scale(0.97);
    }

    /* Modal / Drawer */
    .modal-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      z-index: 200;
    }
    .modal-backdrop.open {
      display: block;
    }
    .drawer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      max-height: 92vh;
      background: var(--card-bg);
      border-top: 2px solid var(--accent);
      border-radius: 20px 20px 0 0;
      z-index: 210;
      display: flex;
      flex-direction: column;
      transform: translateY(100%);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 -20px 40px rgba(0, 0, 0, 0.85);
    }
    .drawer.open {
      transform: translateY(0);
    }
    .drawer-header {
      padding: 1.25rem 1.25rem 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .drawer-title {
      font-family: 'Oswald', sans-serif;
      font-size: 1.45rem;
      font-weight: 700;
      text-transform: uppercase;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .btn-close-drawer {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid var(--border);
      color: #fff;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      font-size: 1.1rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .drawer-body {
      padding: 1.25rem;
      overflow-y: auto;
      max-height: calc(92vh - 170px);
    }
    .drawer-body::-webkit-scrollbar { width: 4px; }
    .drawer-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }

    /* Cart Items list */
    .cart-items-list {
      margin-bottom: 1.5rem;
    }
    .cart-item-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--border);
    }
    .c-item-left {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .c-item-icon {
      font-size: 1.5rem;
    }
    .c-item-info h4 {
      font-size: 0.95rem;
      font-weight: 700;
      color: #fff;
    }
    .c-item-info span {
      font-size: 0.8rem;
      color: var(--muted);
    }
    .c-item-right {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .c-item-price {
      font-family: 'Oswald', sans-serif;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--accent);
      min-width: 50px;
      text-align: right;
    }
    .mini-qty {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.15rem 0.35rem;
    }
    .mini-qty button {
      background: transparent;
      border: none;
      color: #cbd5e1;
      width: 22px;
      height: 22px;
      font-weight: 800;
      font-size: 1rem;
      cursor: pointer;
    }
    .mini-qty span {
      font-family: 'Oswald', sans-serif;
      font-weight: 700;
      font-size: 0.95rem;
      min-width: 16px;
      text-align: center;
    }

    /* Checkout Form Elements */
    .form-group {
      margin-bottom: 1.15rem;
    }
    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      color: #cbd5e1;
      margin-bottom: 0.45rem;
    }
    .form-label span.req {
      color: #ef4444;
    }
    .form-input {
      width: 100%;
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 0.75rem 1rem;
      color: #fff;
      font-family: inherit;
      font-size: 0.95rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .form-input:focus {
      border-color: var(--accent);
    }
    .pills-group {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
      margin-bottom: 0.6rem;
    }
    .location-pill {
      background: var(--surface);
      border: 1px solid var(--border);
      color: #cbd5e1;
      font-size: 0.8rem;
      font-weight: 600;
      padding: 0.45rem 0.85rem;
      border-radius: 20px;
      cursor: pointer;
      transition: all 0.15s;
    }
    .location-pill.selected {
      background: rgba(229, 142, 38, 0.15);
      border-color: var(--accent);
      color: var(--accent);
      font-weight: 700;
    }

    /* Payment channels */
    .bank-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.45rem;
      margin-top: 0.35rem;
    }
    .bank-radio {
      display: none;
    }
    .bank-btn {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.55rem 0.35rem;
      text-align: center;
      font-size: 0.78rem;
      font-weight: 600;
      color: #cbd5e1;
      cursor: pointer;
      transition: all 0.15s;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.2rem;
    }
    .bank-radio:checked + .bank-btn {
      border-color: var(--accent);
      background: rgba(229, 142, 38, 0.12);
      color: var(--accent);
      font-weight: 700;
    }

    /* Drawer Footer */
    .drawer-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid var(--border);
      background: rgba(20, 17, 14, 0.98);
    }
    .drawer-total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.85rem;
    }
    .drawer-total-label {
      font-size: 0.92rem;
      color: #cbd5e1;
      font-weight: 600;
    }
    .drawer-total-sum {
      font-family: 'Oswald', sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--accent);
    }
    .btn-pay-now {
      width: 100%;
      background: linear-gradient(135deg, #e58e26 0%, #d97706 100%);
      border: none;
      color: #0c0a09;
      padding: 0.95rem;
      border-radius: 12px;
      font-size: 1.05rem;
      font-weight: 800;
      letter-spacing: 0.5px;
      cursor: pointer;
      box-shadow: 0 4px 18px rgba(229, 142, 38, 0.45);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: opacity 0.2s;
    }
    .btn-pay-now:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .legal-notice {
      margin-top: 0.65rem;
      font-size: 0.72rem;
      color: var(--muted);
      text-align: center;
      line-height: 1.4;
    }

    /* Toast Alert */
    .toast-alert {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%) translateY(-100px);
      background: #ef4444;
      color: #fff;
      padding: 0.75rem 1.25rem;
      border-radius: 10px;
      font-size: 0.88rem;
      font-weight: 600;
      z-index: 300;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      max-width: 90%;
    }
    .toast-alert.show {
      transform: translateX(-50%) translateY(0);
    }
  </style>
</head>
<body>

  <!-- Toast klaidos pranešimui -->
  <div id="toastAlert" class="toast-alert">
    <span>⚠️</span>
    <span id="toastMsg">Įvyko klaida</span>
  </div>

  <!-- Header -->
  <header class="bar-header">
    <a href="index.html" class="brand-wrap">
      <div class="brand-icon">🎯</div>
      <div>
        <div class="brand-title">Throw<span>House</span></div>
        <div class="brand-sub">Baras & Užkandžiai</div>
      </div>
    </a>
    <div class="header-badge">
      <span class="dot"></span>
      <span>Dirba dabar</span>
    </div>
  </header>

  <!-- Hero info -->
  <div class="hero-banner">
    <div class="banner-card">
      <div class="banner-emoji">🥤</div>
      <div class="banner-text">
        <h1>Gėrimai ir Užkandžiai</h1>
        <p>Išsirinkite, apmokėkite internetu ir instruktorius tuoj pat pristatys užsakymą tiesiai prie Jūsų takelio ar poilsio sofos!</p>
      </div>
    </div>
  </div>

  <!-- Category Filter Navigation -->
  <div class="category-nav">
    <?php foreach ($categories as $index => $cat): ?>
      <button type="button" class="cat-pill <?= $index === 0 ? 'active' : '' ?>" onclick="filterCategory('<?= htmlspecialchars($cat) ?>', this)">
        <?= $cat === 'Gėrimai' ? '🥤 ' : ($cat === 'Užkandžiai' ? '🍿 ' : '⚡ ') ?><?= htmlspecialchars($cat) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Products Section -->
  <div class="products-container">
    <div class="section-title">
      <span>Meniu asortimentas</span>
    </div>
    
    <div class="products-grid" id="productsGrid">
      <?php foreach ($activeProducts as $p): ?>
        <div class="product-card" data-category="<?= htmlspecialchars($p['category']) ?>" id="card_<?= htmlspecialchars($p['id']) ?>">
          <div>
            <div class="product-badge-wrap">
              <div class="product-icon"><?= htmlspecialchars($p['icon'] ?? '🥤') ?></div>
              <span class="product-category-tag"><?= htmlspecialchars($p['category']) ?></span>
            </div>
            <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
          </div>
          
          <div>
            <div class="product-price"><?= number_format($p['price'], 2) ?> €</div>
            
            <button type="button" class="btn-add-item" id="btn_add_<?= htmlspecialchars($p['id']) ?>" onclick="addToCart('<?= htmlspecialchars($p['id']) ?>')">
              + Pridėti
            </button>

            <div class="qty-controls" id="qty_wrap_<?= htmlspecialchars($p['id']) ?>">
              <button type="button" class="qty-btn" onclick="updateQty('<?= htmlspecialchars($p['id']) ?>', -1)">−</button>
              <span class="qty-val" id="qty_val_<?= htmlspecialchars($p['id']) ?>">0</span>
              <button type="button" class="qty-btn" onclick="updateQty('<?= htmlspecialchars($p['id']) ?>', 1)">+</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Sticky Bottom Checkout Bar -->
  <div class="bottom-bar" id="bottomBar">
    <div class="bottom-bar-inner">
      <div class="bar-total-info">
        <span class="bar-items-count" id="barCount">0 vnt.</span>
        <span class="bar-total-price" id="barPrice">0.00 €</span>
      </div>
      <button type="button" class="btn-checkout" onclick="openCartDrawer()">
        Krepšelis ➔
      </button>
    </div>
  </div>

  <!-- Modal Backdrop -->
  <div class="modal-backdrop" id="modalBackdrop" onclick="closeCartDrawer()"></div>

  <!-- Slide-up Cart / Checkout Drawer -->
  <div class="drawer" id="cartDrawer">
    <div class="drawer-header">
      <div class="drawer-title">
        <span>🛒</span> Jūsų krepšelis
      </div>
      <button type="button" class="btn-close-drawer" onclick="closeCartDrawer()">✕</button>
    </div>

    <div class="drawer-body">
      <!-- Krepšelio prekių sąrašas -->
      <div class="cart-items-list" id="drawerCartList">
        <!-- Generuojama per JS -->
      </div>

      <!-- Užsakovo duomenys -->
      <form id="barCheckoutForm" onsubmit="submitBarOrder(event)">
        <div class="form-group">
          <label class="form-label" for="clientName">Jūsų vardas <span class="req">*</span></label>
          <input type="text" id="clientName" class="form-input" placeholder="Vardas (pvz. Mantas)" required autocomplete="name">
        </div>

        <div class="form-group">
          <label class="form-label">Kurioje erdvės vietoje esate? <span class="req">*</span></label>
          <div class="pills-group" id="locationPills">
            <button type="button" class="location-pill" onclick="selectLocation('🔥 Pykčio kambarys', this)">🔥 Pykčio kambarys</button>
            <button type="button" class="location-pill" onclick="selectLocation('🎯 Kirvių 1 takelis', this)">🎯 Kirvių 1 takelis</button>
            <button type="button" class="location-pill" onclick="selectLocation('🎯 Kirvių 2 takelis', this)">🎯 Kirvių 2 takelis</button>
            <button type="button" class="location-pill" onclick="selectLocation('🛋️ Poilsio erdvė (sofos)', this)">🛋️ Poilsio erdvė</button>
            <button type="button" class="location-pill" onclick="selectLocation('', this)">➕ Kita vieta</button>
          </div>
          <input type="text" id="clientLocation" class="form-input" placeholder="Nurodykite vietą (pvz., Poilsio zona ar 1 takelis)" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="clientPhone">Telefono numeris (nebūtina)</label>
          <input type="tel" id="clientPhone" class="form-input" placeholder="+370 600 00000" autocomplete="tel">
        </div>

        <div class="form-group">
          <label class="form-label" for="clientNotes">Pastaba instruktoriui (nebūtina)</label>
          <input type="text" id="clientNotes" class="form-input" placeholder="Pvz., prašome šaltesnio gėrimo ar stiklinių">
        </div>

        <div class="form-group">
          <label class="form-label">Mokėjimo būdas (OPAY el. bankininkystė):</label>
          <div class="bank-grid">
            <label>
              <input type="radio" name="bank_channel" value="" class="bank-radio" checked>
              <span class="bank-btn">🏛️ Visi bankai</span>
            </label>
            <label>
              <input type="radio" name="bank_channel" value="swedbank" class="bank-radio">
              <span class="bank-btn">🟡 Swedbank</span>
            </label>
            <label>
              <input type="radio" name="bank_channel" value="seb" class="bank-radio">
              <span class="bank-btn">🟢 SEB</span>
            </label>
            <label>
              <input type="radio" name="bank_channel" value="revolut" class="bank-radio">
              <span class="bank-btn">🟣 Revolut</span>
            </label>
            <label>
              <input type="radio" name="bank_channel" value="luminor" class="bank-radio">
              <span class="bank-btn">🔵 Luminor</span>
            </label>
            <label>
              <input type="radio" name="bank_channel" value="card" class="bank-radio">
              <span class="bank-btn">💳 Kortelė / Pay</span>
            </label>
          </div>
        </div>
      </form>
    </div>

    <!-- Drawer Footer -->
    <div class="drawer-footer">
      <div class="drawer-total-row">
        <span class="drawer-total-label">Mokėtina suma:</span>
        <span class="drawer-total-sum" id="drawerTotalPrice">0.00 €</span>
      </div>
      <button type="button" class="btn-pay-now" id="btnSubmitOrder" onclick="document.getElementById('barCheckoutForm').requestSubmit()">
        <span>Mokėti per el. banką</span> ➔
      </button>
      <div class="legal-notice">
        🔒 Saugus momentinis atsiskaitymas per OPAY. Užsakymas vykdomas kaip nuotolinė e-prekyba erdvėje.
      </div>
    </div>
  </div>

  <script>
    // Duomenų bazė JS pusėje
    const PRODUCTS = <?= json_encode($activeProducts, JSON_UNESCAPED_UNICODE) ?>;
    const productMap = {};
    PRODUCTS.forEach(p => productMap[p.id] = p);

    let cart = {}; // { 'p_coca_cola': 2 }

    // Užkrauname išsaugotą krepšelį jei yra
    try {
      const saved = localStorage.getItem('th_bar_cart');
      if (saved) {
        cart = JSON.parse(saved);
        // Valome negaliojančias prekes
        Object.keys(cart).forEach(id => {
          if (!productMap[id] || cart[id] <= 0) delete cart[id];
        });
      }
    } catch(e) {}

    function saveCart() {
      try {
        localStorage.setItem('th_bar_cart', JSON.stringify(cart));
      } catch(e) {}
    }

    function showToast(msg) {
      const el = document.getElementById('toastAlert');
      const msgEl = document.getElementById('toastMsg');
      msgEl.textContent = msg;
      el.classList.add('show');
      setTimeout(() => el.classList.remove('show'), 3800);
    }

    function addToCart(pId) {
      if (!cart[pId]) cart[pId] = 0;
      cart[pId]++;
      saveCart();
      updateUI();
    }

    function updateQty(pId, change) {
      if (!cart[pId]) cart[pId] = 0;
      cart[pId] += change;
      if (cart[pId] <= 0) {
        delete cart[pId];
      }
      saveCart();
      updateUI();
      renderDrawerCart();
    }

    function filterCategory(catName, btn) {
      document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const cards = document.querySelectorAll('.product-card');
      cards.forEach(card => {
        if (catName === 'Visi' || card.getAttribute('data-category') === catName) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    }

    function updateUI() {
      let totalQty = 0;
      let totalPrice = 0;

      PRODUCTS.forEach(p => {
        const qty = cart[p.id] || 0;
        const btnAdd = document.getElementById('btn_add_' + p.id);
        const qtyWrap = document.getElementById('qty_wrap_' + p.id);
        const qtyVal = document.getElementById('qty_val_' + p.id);

        if (btnAdd && qtyWrap && qtyVal) {
          if (qty > 0) {
            btnAdd.style.display = 'none';
            qtyWrap.style.display = 'flex';
            qtyVal.textContent = qty;
          } else {
            btnAdd.style.display = 'flex';
            qtyWrap.style.display = 'none';
          }
        }

        if (qty > 0) {
          totalQty += qty;
          totalPrice += (p.price * qty);
        }
      });

      const bottomBar = document.getElementById('bottomBar');
      const barCount = document.getElementById('barCount');
      const barPrice = document.getElementById('barPrice');
      const drawerTotalPrice = document.getElementById('drawerTotalPrice');

      if (totalQty > 0) {
        bottomBar.classList.add('visible');
        barCount.textContent = totalQty + ' ' + (totalQty === 1 ? 'prekė' : (totalQty % 10 === 0 || (totalQty % 100 >= 11 && totalQty % 100 <= 19) ? 'prekių' : 'prekės'));
        barPrice.textContent = totalPrice.toFixed(2) + ' €';
        drawerTotalPrice.textContent = totalPrice.toFixed(2) + ' €';
      } else {
        bottomBar.classList.remove('visible');
        closeCartDrawer();
      }
    }

    function openCartDrawer() {
      renderDrawerCart();
      document.getElementById('modalBackdrop').classList.add('open');
      document.getElementById('cartDrawer').classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeCartDrawer() {
      document.getElementById('modalBackdrop').classList.remove('open');
      document.getElementById('cartDrawer').classList.remove('open');
      document.body.style.overflow = '';
    }

    function selectLocation(locText, pillEl) {
      document.querySelectorAll('.location-pill').forEach(p => p.classList.remove('selected'));
      pillEl.classList.add('selected');
      const input = document.getElementById('clientLocation');
      if (locText) {
        input.value = locText;
      } else {
        input.value = '';
        input.focus();
      }
    }

    function renderDrawerCart() {
      const container = document.getElementById('drawerCartList');
      const keys = Object.keys(cart).filter(id => cart[id] > 0);

      if (keys.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:1.5rem; color:#94a3b8;">Krepšelis tuščias.</div>';
        return;
      }

      let html = '';
      keys.forEach(id => {
        const p = productMap[id];
        if (!p) return;
        const qty = cart[id];
        const lineTotal = (p.price * qty).toFixed(2);

        html += `
          <div class="cart-item-row">
            <div class="c-item-left">
              <span class="c-item-icon">${p.icon || '🥤'}</span>
              <div class="c-item-info">
                <h4>${p.name}</h4>
                <span>${p.price.toFixed(2)} € / vnt.</span>
              </div>
            </div>
            <div class="c-item-right">
              <div class="mini-qty">
                <button type="button" onclick="updateQty('${p.id}', -1)">−</button>
                <span>${qty}</span>
                <button type="button" onclick="updateQty('${p.id}', 1)">+</button>
              </div>
              <span class="c-item-price">${lineTotal} €</span>
            </div>
          </div>
        `;
      });

      container.innerHTML = html;
    }

    async function submitBarOrder(e) {
      e.preventDefault();

      const name = document.getElementById('clientName').value.trim();
      const location = document.getElementById('clientLocation').value.trim();
      const phone = document.getElementById('clientPhone').value.trim();
      const notes = document.getElementById('clientNotes').value.trim();

      const selectedBank = document.querySelector('input[name="bank_channel"]:checked')?.value || '';

      const cartArray = [];
      Object.keys(cart).forEach(id => {
        if (cart[id] > 0) {
          cartArray.push({ id: id, qty: cart[id] });
        }
      });

      if (!name) {
        showToast('Prašome įvesti savo vardą.');
        return;
      }
      if (!location) {
        showToast('Prašome nurodyti, kurioje erdvės vietoje esate.');
        return;
      }
      if (cartArray.length === 0) {
        showToast('Krepšelis yra tuščias.');
        return;
      }

      const btn = document.getElementById('btnSubmitOrder');
      btn.disabled = true;
      btn.innerHTML = '<span>Kraunamas bankas...</span> ⏳';

      try {
        const resp = await fetch('baras_uzsakyti.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            client_name: name,
            client_location: location,
            client_phone: phone,
            client_notes: notes,
            opay_channel: selectedBank,
            cart: cartArray
          })
        });

        const data = await resp.json();

        if (data.success && data.redirect_url) {
          // Išvalome krepšelį
          cart = {};
          saveCart();
          // Nukreipiame į OPAY apmokėjimą
          window.location.href = data.redirect_url;
        } else {
          showToast(data.error || 'Nepavyko sukurti užsakymo.');
          btn.disabled = false;
          btn.innerHTML = '<span>Mokėti per el. banką</span> ➔';
        }
      } catch (err) {
        console.error(err);
        showToast('Ryšio klaida. Patikrinkite interneto ryšį ir bandykite dar kartą.');
        btn.disabled = false;
        btn.innerHTML = '<span>Mokėti per el. banką</span> ➔';
      }
    }

    // Inicializavimas
    document.addEventListener('DOMContentLoaded', () => {
      updateUI();
    });
  </script>
</body>
</html>
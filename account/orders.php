<?php
require_once __DIR__ . '/../admin/config.php';
requireCustomerAuth();

$db = getDB();
$customer = currentCustomer();
$orders = fetchOrdersByEmail($db, $customer['email']);
$highlight = $_GET['highlight'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Orders - Panifit</title>
  <link rel="stylesheet" href="../style.css" />
</head>
<body>
  <nav id="navbar" class="scrolled">
    <a class="nav-logo" href="../index.html">PANI<span>FIT</span></a>
    <ul class="nav-links" id="navLinks">
      <li><a href="../products.html">Shop</a></li>
      <li><a href="../index.html#faq">FAQ</a></li>
    </ul>
    <div class="nav-right">
      <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle dark mode">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
      </button>
      <a href="logout.php" class="nav-cta" style="padding:10px 18px !important;">Logout</a>
    </div>
  </nav>

  <main class="tracking-page">
    <h1>My Orders</h1>
    <p class="section-sub" style="max-width:720px;margin-bottom:28px;">Signed in as <strong><?= e($customer['email']) ?></strong>. Orders placed with this email appear here automatically.</p>

    <?php if (!$orders): ?>
      <div class="tracking-card">
        <div class="tracking-card-header">
          <div>
            <div class="tracking-order-id">No orders yet</div>
            <p style="margin-top:10px;color:var(--text-muted);line-height:1.7;">Use the same email at checkout and your delivery status will show up here.</p>
          </div>
        </div>
        <a href="../products.html" class="btn-primary">Shop Panifit</a>
      </div>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
      <?php $items = json_decode($order['cart_json'], true) ?: []; ?>
      <article class="tracking-card" style="<?= $highlight === $order['order_id'] ? 'border-color:rgba(255,216,77,0.4);box-shadow:0 8px 24px rgba(255,216,77,0.08);' : '' ?>">
        <div class="tracking-card-header">
          <div>
            <div class="tracking-order-id"><?= e($order['order_id']) ?></div>
            <div class="tracking-details" style="margin-top:10px;">
              <span>Placed <?= e(formatDate($order['created_at'])) ?></span>
              <span>Total <?= e(formatMoney($order['total'])) ?></span>
              <span>Updated <?= e(formatDate($order['updated_at'])) ?></span>
            </div>
          </div>
          <span class="tracking-status <?= e($order['status']) ?>"><?= e(statusLabel($order['status'])) ?></span>
        </div>

        <p style="color:var(--text-secondary);line-height:1.7;margin-bottom:16px;"><?= e(statusDescription($order['status'])) ?></p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;">
          <div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:8px;">Shipping</div>
            <div style="line-height:1.7;"><?= e($order['address']) ?><br><?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['zip']) ?></div>
          </div>
          <div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:8px;">Items</div>
            <?php foreach ($items as $item): ?>
              <div style="line-height:1.8;"><?= e($item['name'] ?? 'Panifit Pack') ?> x<?= (int) ($item['qty'] ?? 1) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </main>

  <script src="../app.js"></script>
</body>
</html>

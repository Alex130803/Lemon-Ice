<?php
require_once __DIR__ . '/config.php';
requireAuth();

$db = getDB();
$orderId = $_GET['id'] ?? '';

if (!$orderId) {
    header('Location: index.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $allowed = ['pending','paid','shipped','delivered','canceled'];
        if (in_array($newStatus, $allowed)) {
            $stmt = $db->prepare("UPDATE orders SET status = :status, updated_at = datetime('now') WHERE order_id = :oid");
            $stmt->bindValue(':status', $newStatus);
            $stmt->bindValue(':oid', $orderId);
            $stmt->execute();
        }
    }
    if ($_POST['action'] === 'update_notes') {
        $notes = $_POST['notes'] ?? '';
        $stmt = $db->prepare("UPDATE orders SET notes = :notes, updated_at = datetime('now') WHERE order_id = :oid");
        $stmt->bindValue(':notes', $notes);
        $stmt->bindValue(':oid', $orderId);
        $stmt->execute();
    }
    if ($_POST['action'] === 'delete') {
        $stmt = $db->prepare("DELETE FROM orders WHERE order_id = :oid");
        $stmt->bindValue(':oid', $orderId);
        $stmt->execute();
        header('Location: index.php');
        exit;
    }
    header('Location: order.php?id=' . urlencode($orderId) . '&updated=1');
    exit;
}

// Fetch order
$stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :oid");
$stmt->bindValue(':oid', $orderId);
$order = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$order) {
    header('Location: index.php');
    exit;
}

$cart = json_decode($order['cart_json'], true) ?: [];
$updated = isset($_GET['updated']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Order <?= e($order['order_id']) ?> — Panifit Admin</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect x='15' y='15' width='70' height='70' rx='18' fill='%23FFD84D' opacity='0.9'/></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root { --yellow:#FFD84D; --black:#0D0D0D; --dark:#141414; --dark2:#1c1c1c; --white:#FAFAF8; --green:#7CEB6F; --gray:#888; --border:rgba(255,255,255,0.08); }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--black); color:var(--white); min-height:100vh; }
    .topbar { display:flex; align-items:center; justify-content:space-between; padding:16px 32px; background:var(--dark); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:100; }
    .topbar-logo { font-family:'Bebas Neue',sans-serif; font-size:22px; letter-spacing:3px; display:flex; gap:2px; text-decoration:none; }
    .topbar-logo .y { color:var(--yellow); }
    .topbar-logo .w { color:var(--white); }
    .topbar-badge { font-family:'Space Mono',monospace; font-size:10px; color:var(--gray); letter-spacing:1px; background:var(--dark2); border:1px solid var(--border); padding:4px 10px; border-radius:100px; margin-left:12px; }
    .topbar-right { display:flex; align-items:center; gap:16px; }
    .topbar-link { color:var(--gray); text-decoration:none; font-size:13px; transition:color 0.2s; }
    .topbar-link:hover { color:var(--yellow); }

    .container { max-width:900px; margin:0 auto; padding:32px; }
    .back-link { color:var(--gray); text-decoration:none; font-size:13px; display:inline-flex; align-items:center; gap:6px; margin-bottom:24px; transition:color 0.2s; }
    .back-link:hover { color:var(--yellow); }

    .order-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:32px; flex-wrap:wrap; gap:16px; }
    .order-id { font-family:'Bebas Neue',sans-serif; font-size:40px; letter-spacing:2px; }
    .order-date { font-size:13px; color:var(--gray); margin-top:4px; }

    .card { background:var(--dark); border:1px solid var(--border); border-radius:16px; padding:28px; margin-bottom:20px; }
    .card-title { font-family:'Space Mono',monospace; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:var(--yellow); margin-bottom:20px; }

    .detail-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); font-size:14px; }
    .detail-row:last-child { border-bottom:none; }
    .detail-label { color:var(--gray); }
    .detail-value { font-weight:600; text-align:right; }

    .cart-item { display:flex; align-items:center; gap:16px; padding:14px 0; border-bottom:1px solid var(--border); }
    .cart-item:last-child { border-bottom:none; }
    .cart-item-icon { width:44px; height:44px; border-radius:10px; background:var(--dark2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .cart-item-icon svg { width:22px; height:22px; color:var(--yellow); }
    .cart-item-name { font-weight:600; font-size:14px; }
    .cart-item-detail { font-size:12px; color:var(--gray); }
    .cart-item-price { margin-left:auto; font-family:'Bebas Neue',sans-serif; font-size:20px; color:var(--yellow); }

    .status-form { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .status-select { padding:10px 16px; background:var(--dark2); border:1px solid var(--border); border-radius:10px; color:var(--white); font-family:'DM Sans',sans-serif; font-size:14px; outline:none; cursor:pointer; }
    .status-select:focus { border-color:var(--yellow); }
    .save-btn { padding:10px 24px; background:var(--yellow); color:var(--black); border:none; border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; transition:all 0.2s; }
    .save-btn:hover { transform:translateY(-1px); box-shadow:0 4px 20px rgba(255,216,77,0.3); }
    .delete-btn { padding:10px 24px; background:transparent; color:#ff5555; border:1px solid rgba(255,85,85,0.3); border-radius:10px; font-weight:700; font-size:13px; cursor:pointer; transition:all 0.2s; }
    .delete-btn:hover { background:rgba(255,85,85,0.1); }

    .notes-area { width:100%; padding:12px 16px; background:var(--dark2); border:1px solid var(--border); border-radius:10px; color:var(--white); font-family:'DM Sans',sans-serif; font-size:14px; outline:none; resize:vertical; min-height:80px; transition:border-color 0.2s; }
    .notes-area:focus { border-color:var(--yellow); }

    .toast { position:fixed; top:80px; right:32px; background:var(--dark2); border:1px solid rgba(124,235,111,0.3); color:var(--green); padding:14px 20px; border-radius:12px; font-size:13px; font-weight:600; animation:slideIn 0.3s ease; z-index:200; }
    @keyframes slideIn { from{transform:translateX(100px);opacity:0;} to{transform:translateX(0);opacity:1;} }

    .total-row .detail-value { font-family:'Bebas Neue',sans-serif; font-size:28px; color:var(--yellow); }

    @media (max-width:768px) {
      .container { padding:16px; }
      .order-header { flex-direction:column; }
      .order-id { font-size:28px; }
    }
  </style>
</head>
<body>

  <div class="topbar">
    <div style="display:flex;align-items:center;">
      <a class="topbar-logo" href="index.php"><span class="y">PANI</span><span class="w">FIT</span></a>
      <span class="topbar-badge">ADMIN</span>
    </div>
    <div class="topbar-right">
      <a href="../index.html" class="topbar-link">View Site</a>
      <a href="logout.php" class="topbar-link" style="color:#ff8888;">Logout</a>
    </div>
  </div>

  <?php if ($updated): ?>
    <div class="toast">Order updated successfully</div>
    <script>setTimeout(()=>document.querySelector('.toast').style.display='none',3000);</script>
  <?php endif; ?>

  <div class="container">
    <a href="index.php" class="back-link">&larr; All Orders</a>

    <div class="order-header">
      <div>
        <div class="order-id"><?= e($order['order_id']) ?></div>
        <div class="order-date">Created <?= formatDate($order['created_at']) ?></div>
        <?php if ($order['updated_at'] !== $order['created_at']): ?>
          <div class="order-date">Updated <?= formatDate($order['updated_at']) ?></div>
        <?php endif; ?>
      </div>
      <div><?= statusBadge($order['status']) ?></div>
    </div>

    <!-- STATUS UPDATE -->
    <div class="card">
      <div class="card-title">Update Status</div>
      <form method="POST" class="status-form">
        <input type="hidden" name="action" value="update_status" />
        <select name="status" class="status-select">
          <?php foreach (['pending','paid','shipped','delivered','canceled'] as $s): ?>
            <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="save-btn">Update Status</button>
      </form>
    </div>

    <!-- CUSTOMER INFO -->
    <div class="card">
      <div class="card-title">Customer</div>
      <div class="detail-row">
        <span class="detail-label">Name</span>
        <span class="detail-value"><?= e($order['first_name'] . ' ' . $order['last_name']) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value"><a href="mailto:<?= e($order['email']) ?>" style="color:var(--yellow);text-decoration:none;"><?= e($order['email']) ?></a></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Phone</span>
        <span class="detail-value"><?= e($order['phone'] ?: '—') ?></span>
      </div>
    </div>

    <!-- SHIPPING -->
    <div class="card">
      <div class="card-title">Shipping Address</div>
      <div class="detail-row">
        <span class="detail-label">Address</span>
        <span class="detail-value"><?= e($order['address']) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">City</span>
        <span class="detail-value"><?= e($order['city']) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">State / ZIP</span>
        <span class="detail-value"><?= e($order['state'] . ' ' . $order['zip']) ?></span>
      </div>
    </div>

    <!-- ORDER ITEMS -->
    <div class="card">
      <div class="card-title">Items</div>
      <?php
        $cubeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M4 12h16" opacity="0.3"/><path d="M12 4v16" opacity="0.3"/></svg>';
        foreach ($cart as $item):
          $name = e($item['name'] ?? 'Item');
          $detail = e($item['detail'] ?? '');
          $qty = intval($item['qty'] ?? 1);
          $price = floatval($item['price'] ?? 0);
      ?>
        <div class="cart-item">
          <div class="cart-item-icon"><?= $cubeIcon ?></div>
          <div>
            <div class="cart-item-name"><?= $name ?></div>
            <div class="cart-item-detail"><?= $detail ?> x<?= $qty ?></div>
          </div>
          <div class="cart-item-price">$<?= number_format($price * $qty, 2) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- TOTALS -->
    <div class="card">
      <div class="card-title">Payment</div>
      <div class="detail-row">
        <span class="detail-label">Subtotal</span>
        <span class="detail-value">$<?= number_format($order['subtotal'], 2) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Shipping</span>
        <span class="detail-value"><?= $order['shipping'] > 0 ? '$'.number_format($order['shipping'], 2) : 'FREE' ?></span>
      </div>
      <div class="detail-row total-row">
        <span class="detail-label">Total</span>
        <span class="detail-value">$<?= number_format($order['total'], 2) ?></span>
      </div>
      <?php if ($order['stripe_session_id']): ?>
        <div class="detail-row">
          <span class="detail-label">Stripe Session</span>
          <span class="detail-value" style="font-family:'Space Mono',monospace;font-size:11px;color:var(--gray);word-break:break-all;"><?= e($order['stripe_session_id']) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <!-- NOTES -->
    <div class="card">
      <div class="card-title">Internal Notes</div>
      <form method="POST">
        <input type="hidden" name="action" value="update_notes" />
        <textarea name="notes" class="notes-area" placeholder="Add notes about this order..."><?= e($order['notes']) ?></textarea>
        <button type="submit" class="save-btn" style="margin-top:12px;">Save Notes</button>
      </form>
    </div>

    <!-- DELETE -->
    <div class="card" style="border-color:rgba(255,85,85,0.15);">
      <div class="card-title" style="color:#ff5555;">Danger Zone</div>
      <form method="POST" onsubmit="return confirm('Are you sure you want to delete this order? This cannot be undone.');">
        <input type="hidden" name="action" value="delete" />
        <button type="submit" class="delete-btn">Delete This Order</button>
      </form>
    </div>
  </div>

</body>
</html>

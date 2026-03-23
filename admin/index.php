<?php
require_once __DIR__ . '/config.php';
requireAuth();

$db = getDB();

// Handle filters
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['q'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = 'status = :status';
    $params[':status'] = $statusFilter;
}
if ($search) {
    $where[] = '(order_id LIKE :q OR email LIKE :q OR first_name LIKE :q OR last_name LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders $whereSQL");
foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
$totalOrders = $countStmt->execute()->fetchArray()[0];
$totalPages = max(1, ceil($totalOrders / $perPage));

// Fetch orders
$stmt = $db->prepare("SELECT * FROM orders $whereSQL ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$orders = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $orders[] = $row;
}

// Stats
$stats = [];
foreach (['pending','paid','shipped','delivered','canceled'] as $s) {
    $r = $db->querySingle("SELECT COUNT(*) FROM orders WHERE status='$s'");
    $stats[$s] = $r ?: 0;
}
$stats['total'] = $db->querySingle("SELECT COUNT(*) FROM orders") ?: 0;
$stats['revenue'] = $db->querySingle("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','shipped','delivered')") ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Orders — Panifit Admin</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect x='15' y='15' width='70' height='70' rx='18' fill='%23FFD84D' opacity='0.9'/></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root { --yellow:#FFD84D; --yellow-dark:#e6c030; --black:#0D0D0D; --dark:#141414; --dark2:#1c1c1c; --white:#FAFAF8; --green:#7CEB6F; --gray:#888; --border:rgba(255,255,255,0.08); }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--black); color:var(--white); min-height:100vh; }

    /* Top bar */
    .topbar { display:flex; align-items:center; justify-content:space-between; padding:16px 32px; background:var(--dark); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:100; }
    .topbar-logo { font-family:'Bebas Neue',sans-serif; font-size:22px; letter-spacing:3px; display:flex; gap:2px; text-decoration:none; }
    .topbar-logo .y { color:var(--yellow); }
    .topbar-logo .w { color:var(--white); }
    .topbar-badge { font-family:'Space Mono',monospace; font-size:10px; color:var(--gray); letter-spacing:1px; background:var(--dark2); border:1px solid var(--border); padding:4px 10px; border-radius:100px; margin-left:12px; }
    .topbar-right { display:flex; align-items:center; gap:16px; }
    .topbar-user { font-size:13px; color:var(--gray); }
    .topbar-link { color:var(--gray); text-decoration:none; font-size:13px; transition:color 0.2s; }
    .topbar-link:hover { color:var(--yellow); }

    /* Layout */
    .container { max-width:1200px; margin:0 auto; padding:32px; }

    /* Stats */
    .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:16px; margin-bottom:32px; }
    .stat-card { padding:24px; background:var(--dark); border:1px solid var(--border); border-radius:14px; }
    .stat-card-label { font-size:12px; color:var(--gray); letter-spacing:1px; text-transform:uppercase; margin-bottom:8px; }
    .stat-card-value { font-family:'Bebas Neue',sans-serif; font-size:36px; letter-spacing:2px; }
    .stat-card-value.revenue { color:var(--green); }
    .stat-card-value.yellow { color:var(--yellow); }

    /* Filters */
    .filters { display:flex; align-items:center; gap:12px; margin-bottom:24px; flex-wrap:wrap; }
    .filter-btn { padding:8px 18px; border:1px solid var(--border); border-radius:100px; background:transparent; color:var(--gray); font-family:'DM Sans',sans-serif; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; cursor:pointer; text-decoration:none; transition:all 0.2s; }
    .filter-btn:hover { border-color:rgba(255,216,77,0.3); color:var(--white); }
    .filter-btn.active { background:var(--yellow); color:var(--black); border-color:var(--yellow); }
    .search-box { margin-left:auto; position:relative; }
    .search-box input { padding:8px 16px 8px 36px; background:var(--dark2); border:1px solid var(--border); border-radius:100px; color:var(--white); font-family:'DM Sans',sans-serif; font-size:13px; outline:none; width:240px; transition:border-color 0.2s; }
    .search-box input:focus { border-color:var(--yellow); }
    .search-box input::placeholder { color:rgba(255,255,255,0.2); }
    .search-box svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--gray); pointer-events:none; }

    /* Table */
    .orders-table { width:100%; border-collapse:collapse; }
    .orders-table th { text-align:left; padding:12px 16px; font-size:11px; color:var(--gray); letter-spacing:1px; text-transform:uppercase; font-weight:600; border-bottom:1px solid var(--border); background:var(--dark); }
    .orders-table td { padding:16px; border-bottom:1px solid var(--border); font-size:14px; vertical-align:middle; }
    .orders-table tr:hover td { background:rgba(255,255,255,0.02); }
    .order-id-link { font-family:'Space Mono',monospace; font-size:13px; color:var(--yellow); text-decoration:none; transition:opacity 0.2s; }
    .order-id-link:hover { opacity:0.7; }
    .order-email { color:var(--gray); font-size:13px; }
    .order-total { font-family:'Bebas Neue',sans-serif; font-size:20px; color:var(--yellow); }

    /* Pagination */
    .pagination { display:flex; align-items:center; justify-content:center; gap:8px; margin-top:32px; }
    .page-btn { padding:8px 14px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--gray); font-size:13px; text-decoration:none; transition:all 0.2s; }
    .page-btn:hover { border-color:var(--yellow); color:var(--white); }
    .page-btn.active { background:var(--yellow); color:var(--black); border-color:var(--yellow); }
    .page-btn.disabled { opacity:0.3; pointer-events:none; }

    /* Empty */
    .empty-state { text-align:center; padding:80px 20px; color:var(--gray); }
    .empty-state svg { width:48px; height:48px; margin-bottom:16px; opacity:0.3; }

    /* Responsive */
    @media (max-width:768px) {
      .container { padding:16px; }
      .topbar { padding:12px 16px; }
      .stats-grid { grid-template-columns:repeat(2,1fr); }
      .search-box { margin-left:0; width:100%; }
      .search-box input { width:100%; }
      .orders-table { display:block; overflow-x:auto; }
    }
  </style>
</head>
<body>

  <!-- TOP BAR -->
  <div class="topbar">
    <div style="display:flex;align-items:center;">
      <a class="topbar-logo" href="index.php"><span class="y">PANI</span><span class="w">FIT</span></a>
      <span class="topbar-badge">ADMIN</span>
    </div>
    <div class="topbar-right">
      <a href="../index.html" class="topbar-link">View Site</a>
      <span class="topbar-user">Logged in as <strong style="color:var(--white);"><?= e(ADMIN_USER) ?></strong></span>
      <a href="logout.php" class="topbar-link" style="color:#ff8888;">Logout</a>
    </div>
  </div>

  <div class="container">
    <!-- STATS -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-label">Total Orders</div>
        <div class="stat-card-value yellow"><?= $stats['total'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Revenue</div>
        <div class="stat-card-value revenue">$<?= number_format($stats['revenue'], 2) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Pending</div>
        <div class="stat-card-value"><?= $stats['pending'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Paid</div>
        <div class="stat-card-value"><?= $stats['paid'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Shipped</div>
        <div class="stat-card-value"><?= $stats['shipped'] ?></div>
      </div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
      <a href="?status=all" class="filter-btn <?= $statusFilter==='all'?'active':'' ?>">All</a>
      <a href="?status=pending" class="filter-btn <?= $statusFilter==='pending'?'active':'' ?>">Pending</a>
      <a href="?status=paid" class="filter-btn <?= $statusFilter==='paid'?'active':'' ?>">Paid</a>
      <a href="?status=shipped" class="filter-btn <?= $statusFilter==='shipped'?'active':'' ?>">Shipped</a>
      <a href="?status=delivered" class="filter-btn <?= $statusFilter==='delivered'?'active':'' ?>">Delivered</a>
      <a href="?status=canceled" class="filter-btn <?= $statusFilter==='canceled'?'active':'' ?>">Canceled</a>
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <form method="GET" style="display:contents;">
          <input type="hidden" name="status" value="<?= e($statusFilter) ?>" />
          <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search orders, emails..." />
        </form>
      </div>
    </div>

    <!-- ORDERS TABLE -->
    <?php if (empty($orders)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="18" rx="2"/><path d="M2 9h20M10 3v6"/></svg>
        <p>No orders found<?= $search ? ' for "'.e($search).'"' : '' ?>.</p>
      </div>
    <?php else: ?>
      <table class="orders-table">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <?php $cart = json_decode($o['cart_json'], true) ?: []; ?>
            <tr>
              <td><a href="order.php?id=<?= e($o['order_id']) ?>" class="order-id-link"><?= e($o['order_id']) ?></a></td>
              <td>
                <div style="font-weight:600;font-size:14px;"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></div>
                <div class="order-email"><?= e($o['email']) ?></div>
              </td>
              <td>
                <?php foreach (array_slice($cart, 0, 2) as $item): ?>
                  <div style="font-size:12px;color:var(--gray);"><?= e($item['name'] ?? 'Item') ?> x<?= intval($item['qty'] ?? 1) ?></div>
                <?php endforeach; ?>
                <?php if (count($cart) > 2): ?>
                  <div style="font-size:11px;color:var(--gray);">+<?= count($cart) - 2 ?> more</div>
                <?php endif; ?>
              </td>
              <td><span class="order-total">$<?= number_format($o['total'], 2) ?></span></td>
              <td><?= statusBadge($o['status']) ?></td>
              <td style="color:var(--gray);font-size:13px;white-space:nowrap;"><?= formatDate($o['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- PAGINATION -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <a href="?status=<?= e($statusFilter) ?>&q=<?= e($search) ?>&page=<?= $page-1 ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">&larr; Prev</a>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?status=<?= e($statusFilter) ?>&q=<?= e($search) ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a href="?status=<?= e($statusFilter) ?>&q=<?= e($search) ?>&page=<?= $page+1 ?>" class="page-btn <?= $page>=$totalPages?'disabled':'' ?>">Next &rarr;</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</body>
</html>

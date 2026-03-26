<?php
/**
 * Panifit shared configuration
 * Admin auth, customer auth, SQLite storage, and email helpers.
 */

session_start();

define('ADMIN_USER', 'panifit');
define('ADMIN_PASS_HASH', password_hash('LemonCube$2026', PASSWORD_DEFAULT));

define('DB_PATH', __DIR__ . '/panifit_orders.db');
define('STORE_NAME', 'Panifit');
define('STORE_EMAIL', 'hello@panifit.com');
define('FREE_SHIPPING_THRESHOLD', 30);
define('FLAT_SHIPPING_RATE', 5.99);
define('STRIPE_SECRET_KEY', 'sk_test_51LD8Z1ArYlvjGATtmGXjFX4oSEYWKkpFMng7HFZUi6NeK0KYUIVUbs7TOREJAKwYVWa19xhfPQgBVDYpEMVsYuYu00YxRDVBKy');

function getDB() {
    $db = new SQLite3(DB_PATH);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');

    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT UNIQUE NOT NULL,
            email TEXT NOT NULL,
            first_name TEXT DEFAULT '',
            last_name TEXT DEFAULT '',
            address TEXT DEFAULT '',
            city TEXT DEFAULT '',
            state TEXT DEFAULT '',
            zip TEXT DEFAULT '',
            phone TEXT DEFAULT '',
            cart_json TEXT DEFAULT '[]',
            subtotal REAL DEFAULT 0,
            shipping REAL DEFAULT 0,
            total REAL DEFAULT 0,
            stripe_session_id TEXT DEFAULT '',
            status TEXT DEFAULT 'pending',
            notes TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            first_name TEXT DEFAULT '',
            last_name TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_orders_email ON orders(email);
        CREATE INDEX IF NOT EXISTS idx_customers_email ON customers(email);
    ");

    ensureColumn($db, 'orders', 'last_email_type', "TEXT DEFAULT ''");
    ensureColumn($db, 'orders', 'last_email_sent_at', "DATETIME DEFAULT NULL");

    return $db;
}

function ensureColumn(SQLite3 $db, $table, $column, $definition) {
    $result = $db->query("PRAGMA table_info($table)");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (($row['name'] ?? '') === $column) {
            return;
        }
    }
    $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}

function requireAuth() {
    if (!isset($_SESSION['panifit_admin']) || $_SESSION['panifit_admin'] !== true) {
        header('Location: login.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['panifit_admin']) && $_SESSION['panifit_admin'] === true;
}

function verifyPassword($input) {
    return password_verify($input, ADMIN_PASS_HASH) || $input === 'LemonCube$2026';
}

function customerLogin(array $customer) {
    $_SESSION['panifit_customer'] = [
        'id' => (int) $customer['id'],
        'email' => $customer['email'],
        'first_name' => $customer['first_name'] ?? '',
        'last_name' => $customer['last_name'] ?? '',
    ];
}

function customerLogout() {
    unset($_SESSION['panifit_customer']);
}

function currentCustomer() {
    return $_SESSION['panifit_customer'] ?? null;
}

function requireCustomerAuth() {
    if (!currentCustomer()) {
        header('Location: login.php');
        exit;
    }
}

function findCustomerByEmail(SQLite3 $db, $email) {
    $stmt = $db->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
    $stmt->bindValue(':email', strtolower(trim($email)), SQLITE3_TEXT);
    $result = $stmt->execute();
    return $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
}

function createCustomer(SQLite3 $db, $email, $password, $firstName = '', $lastName = '') {
    $stmt = $db->prepare('INSERT INTO customers (email, password_hash, first_name, last_name, updated_at) VALUES (:email, :hash, :first_name, :last_name, CURRENT_TIMESTAMP)');
    $stmt->bindValue(':email', strtolower(trim($email)), SQLITE3_TEXT);
    $stmt->bindValue(':hash', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
    $stmt->bindValue(':first_name', trim($firstName), SQLITE3_TEXT);
    $stmt->bindValue(':last_name', trim($lastName), SQLITE3_TEXT);
    return $stmt->execute();
}

function authenticateCustomer(SQLite3 $db, $email, $password) {
    $customer = findCustomerByEmail($db, $email);
    if (!$customer) {
        return false;
    }
    return password_verify($password, $customer['password_hash']) ? $customer : false;
}

function fetchOrderById(SQLite3 $db, $orderId) {
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_id = :oid LIMIT 1');
    $stmt->bindValue(':oid', $orderId, SQLITE3_TEXT);
    $result = $stmt->execute();
    return $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
}

function fetchOrdersByEmail(SQLite3 $db, $email) {
    $stmt = $db->prepare('SELECT * FROM orders WHERE email = :email ORDER BY created_at DESC');
    $stmt->bindValue(':email', strtolower(trim($email)), SQLITE3_TEXT);
    $result = $stmt->execute();
    $orders = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $orders[] = $row;
    }
    return $orders;
}

function allowedOrderStatuses() {
    return ['pending', 'paid', 'shipped', 'delivered', 'canceled'];
}

function shippingAmount($subtotal) {
    return $subtotal >= FREE_SHIPPING_THRESHOLD ? 0.0 : FLAT_SHIPPING_RATE;
}

function getSiteBaseUrl() {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $protocol = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if (preg_match('~/api$~', $basePath)) {
        $basePath = dirname($basePath);
    }
    if (preg_match('~/admin$~', $basePath)) {
        $basePath = dirname($basePath);
    }
    if (preg_match('~/account$~', $basePath)) {
        $basePath = dirname($basePath);
    }

    $basePath = $basePath === DIRECTORY_SEPARATOR || $basePath === '.' ? '' : rtrim($basePath, '/');
    return $protocol . '://' . $host . $basePath;
}

function orderTrackingUrl($orderId = '') {
    $url = getSiteBaseUrl() . '/account/orders.php';
    return $orderId ? $url . '?highlight=' . rawurlencode($orderId) : $url;
}

function e($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('M j, Y \a\t g:i A', strtotime($date));
}

function formatMoney($amount) {
    return '$' . number_format((float) $amount, 2);
}

function statusLabel($status) {
    return ucfirst($status);
}

function statusDescription($status) {
    $map = [
        'pending' => 'We received the order and are preparing payment confirmation.',
        'paid' => 'Payment is confirmed and your order is being packed.',
        'shipped' => 'Your package is on the way.',
        'delivered' => 'The shipment was marked as delivered.',
        'canceled' => 'This order has been canceled.',
    ];
    return $map[$status] ?? $map['pending'];
}

function statusBadge($status) {
    $colors = [
        'pending' => 'rgba(255,216,77,0.15);color:#FFD84D;border-color:rgba(255,216,77,0.3)',
        'paid' => 'rgba(124,235,111,0.15);color:#7CEB6F;border-color:rgba(124,235,111,0.3)',
        'shipped' => 'rgba(100,180,255,0.15);color:#64b4ff;border-color:rgba(100,180,255,0.3)',
        'delivered' => 'rgba(124,235,111,0.15);color:#7CEB6F;border-color:rgba(124,235,111,0.3)',
        'canceled' => 'rgba(255,85,85,0.15);color:#ff5555;border-color:rgba(255,85,85,0.3)',
    ];
    $color = $colors[$status] ?? $colors['pending'];
    return '<span style="display:inline-block;padding:4px 12px;border-radius:100px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;background:' . $color . ';border:1px solid;">' . e(statusLabel($status)) . '</span>';
}

function buildEmailLayout($title, $intro, $bodyHtml, $ctaText = '', $ctaUrl = '') {
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $cta = '<p style="margin:32px 0 0;"><a href="' . e($ctaUrl) . '" style="display:inline-block;background:#FFD84D;color:#111111;text-decoration:none;padding:14px 22px;border-radius:999px;font-weight:700;">' . e($ctaText) . '</a></p>';
    }

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f5f4ef;font-family:Arial,sans-serif;color:#1a1a1a;">'
        . '<div style="max-width:640px;margin:0 auto;padding:32px 16px;">'
        . '<div style="background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #ece9df;">'
        . '<div style="padding:28px 32px;background:linear-gradient(135deg,#fff7d1 0%,#ffffff 100%);border-bottom:1px solid #f0ead3;">'
        . '<div style="font-size:28px;letter-spacing:4px;font-weight:700;"><span style="color:#e6c030;">PANI</span><span style="color:#1a1a1a;">FIT</span></div>'
        . '<p style="margin:14px 0 0;color:#595959;line-height:1.6;">' . e($intro) . '</p>'
        . '</div>'
        . '<div style="padding:32px;">'
        . '<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;">' . e($title) . '</h1>'
        . $bodyHtml
        . $cta
        . '</div>'
        . '<div style="padding:20px 32px;background:#fbfaf7;border-top:1px solid #ece9df;color:#6b6b6b;font-size:13px;line-height:1.6;">'
        . 'Questions? Reply to this email or contact ' . e(STORE_EMAIL) . '.'
        . '</div>'
        . '</div></div></body></html>';
}

function sendHtmlEmail($to, $subject, $html, $text = '') {
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . STORE_NAME . ' <' . STORE_EMAIL . '>',
        'Reply-To: ' . STORE_EMAIL,
    ];

    if ($text === '') {
        $text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $html)));
    }

    return @mail($to, $subject, $html, implode("\r\n", $headers));
}

function markEmailSent(SQLite3 $db, $orderId, $type) {
    $stmt = $db->prepare("UPDATE orders SET last_email_type = :type, last_email_sent_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE order_id = :oid");
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':oid', $orderId, SQLITE3_TEXT);
    $stmt->execute();
}

function sendOrderConfirmationEmail(SQLite3 $db, array $order) {
    $items = json_decode($order['cart_json'], true) ?: [];
    $lines = '';
    foreach ($items as $item) {
        $qty = (int) ($item['qty'] ?? 1);
        $name = e($item['name'] ?? 'Panifit Pack');
        $detail = e($item['detail'] ?? '');
        $lineTotal = formatMoney(($item['price'] ?? 0) * $qty);
        $lines .= '<tr><td style="padding:10px 0;border-bottom:1px solid #ece9df;"><strong>' . $name . '</strong><div style="color:#6b6b6b;font-size:13px;">' . $detail . ' x' . $qty . '</div></td><td style="padding:10px 0;border-bottom:1px solid #ece9df;text-align:right;font-weight:700;">' . $lineTotal . '</td></tr>';
    }

    $body = '<p style="margin:0 0 16px;line-height:1.7;">Thanks for your order, <strong>' . e($order['first_name'] ?: 'there') . '</strong>. We have your Panifit order and will send another update as soon as it moves forward.</p>'
        . '<div style="background:#fbfaf7;border:1px solid #ece9df;border-radius:18px;padding:18px 20px;margin:22px 0;">'
        . '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;"><span style="color:#6b6b6b;">Order ID</span><strong style="font-family:Courier New,monospace;">' . e($order['order_id']) . '</strong></div>'
        . '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:10px;"><span style="color:#6b6b6b;">Current status</span><strong>' . e(statusLabel($order['status'])) . '</strong></div>'
        . '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:10px;"><span style="color:#6b6b6b;">Total</span><strong>' . formatMoney($order['total']) . '</strong></div>'
        . '</div>'
        . '<table style="width:100%;border-collapse:collapse;margin-top:8px;"><tbody>' . $lines . '</tbody></table>'
        . '<p style="margin:22px 0 0;line-height:1.7;color:#595959;">Free delivery applies automatically on orders above $' . number_format(FREE_SHIPPING_THRESHOLD, 0) . '.</p>';

    $html = buildEmailLayout(
        'Your order is in',
        'A confirmation for your Panifit order.',
        $body,
        'Track your order',
        orderTrackingUrl($order['order_id'])
    );

    $sent = sendHtmlEmail($order['email'], 'Panifit order confirmation - ' . $order['order_id'], $html);
    if ($sent) {
        markEmailSent($db, $order['order_id'], 'confirmation');
    }
    return $sent;
}

function sendStatusUpdateEmail(SQLite3 $db, array $order) {
    $body = '<p style="margin:0 0 16px;line-height:1.7;">Hi ' . e($order['first_name'] ?: 'there') . ', your order <strong>' . e($order['order_id']) . '</strong> is now <strong>' . e(statusLabel($order['status'])) . '</strong>.</p>'
        . '<div style="background:#fbfaf7;border:1px solid #ece9df;border-radius:18px;padding:18px 20px;margin:22px 0;">'
        . '<p style="margin:0;color:#1a1a1a;line-height:1.7;">' . e(statusDescription($order['status'])) . '</p>'
        . '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px;"><span style="color:#6b6b6b;">Delivery address</span><strong>' . e(trim($order['address'] . ', ' . $order['city'] . ', ' . $order['state'] . ' ' . $order['zip'], ', ')) . '</strong></div>'
        . '</div>';

    $html = buildEmailLayout(
        'Order status updated',
        'A fresh delivery update from Panifit.',
        $body,
        'View your orders',
        orderTrackingUrl($order['order_id'])
    );

    $sent = sendHtmlEmail($order['email'], 'Panifit delivery update - ' . $order['order_id'], $html);
    if ($sent) {
        markEmailSent($db, $order['order_id'], 'status_update');
    }
    return $sent;
}

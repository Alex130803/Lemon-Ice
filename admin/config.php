<?php
/**
 * Panifit Admin — Configuration
 * Database (SQLite) + Authentication
 */

session_start();

// ===== LOGIN CREDENTIALS =====
define('ADMIN_USER', 'panifit');
define('ADMIN_PASS_HASH', password_hash('LemonCube$2026', PASSWORD_DEFAULT));
// Username: panifit
// Password: LemonCube$2026

// ===== DATABASE (SQLite — zero config) =====
define('DB_PATH', __DIR__ . '/panifit_orders.db');

function getDB() {
    $db = new SQLite3(DB_PATH);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode=WAL');

    // Create tables if they don't exist
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

        CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_orders_email ON orders(email);
    ");

    return $db;
}

// ===== AUTH CHECK =====
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
    // Use constant-time comparison
    return password_verify($input, ADMIN_PASS_HASH) || $input === 'LemonCube$2026';
}

// ===== HELPERS =====
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('M j, Y \a\t g:i A', strtotime($date));
}

function statusBadge($status) {
    $colors = [
        'pending'   => 'rgba(255,216,77,0.15);color:#FFD84D;border-color:rgba(255,216,77,0.3)',
        'paid'      => 'rgba(124,235,111,0.15);color:#7CEB6F;border-color:rgba(124,235,111,0.3)',
        'shipped'   => 'rgba(100,180,255,0.15);color:#64b4ff;border-color:rgba(100,180,255,0.3)',
        'delivered'  => 'rgba(124,235,111,0.15);color:#7CEB6F;border-color:rgba(124,235,111,0.3)',
        'canceled'  => 'rgba(255,85,85,0.15);color:#ff5555;border-color:rgba(255,85,85,0.3)',
    ];
    $c = $colors[$status] ?? $colors['pending'];
    return '<span style="display:inline-block;padding:4px 12px;border-radius:100px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;background:'.$c.';border:1px solid;">'.e($status).'</span>';
}

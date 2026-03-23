<?php
/**
 * Panifit — Stripe Checkout Session (no SDK needed, pure cURL)
 *
 * Creates a Stripe Checkout Session and returns the URL.
 * Uses the Stripe REST API directly — no composer/vendor required.
 *
 * TEST CARD: 4242 4242 4242 4242 | any future date | any CVC
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

// ===== STRIPE SECRET KEY (test mode) =====
$stripeSecretKey = 'sk_test_51LD8Z1ArYlvjGATtmGXjFX4oSEYWKkpFMng7HFZUi6NeK0KYUIVUbs7TOREJAKwYVWa19xhfPQgBVDYpEMVsYuYu00YxRDVBKy';

// Get the base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = $protocol . '://' . $host . rtrim($basePath, '/');

// Parse request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['cart']) || empty($input['cart'])) {
    echo json_encode(['error' => 'Cart is empty']);
    http_response_code(400);
    exit;
}

// Validate email
$email = isset($input['email']) ? filter_var($input['email'], FILTER_VALIDATE_EMAIL) : false;
if (!$email) {
    echo json_encode(['error' => 'Valid email is required']);
    http_response_code(400);
    exit;
}

// Build Stripe line_items from cart
$lineItems = [];
$subtotal = 0;

foreach ($input['cart'] as $index => $item) {
    $price = floatval($item['price']);
    $qty = intval($item['qty']);
    $name = substr(strip_tags($item['name'] ?? 'Panifit Cubes'), 0, 100);
    $detail = substr(strip_tags($item['detail'] ?? ''), 0, 200);

    if ($price <= 0 || $price > 200 || $qty < 1 || $qty > 50) {
        echo json_encode(['error' => 'Invalid item in cart']);
        http_response_code(400);
        exit;
    }

    $unitAmountCents = intval(round($price * 100));
    $subtotal += $price * $qty;

    $lineItems["line_items[{$index}][price_data][currency]"] = 'usd';
    $lineItems["line_items[{$index}][price_data][product_data][name]"] = $name;
    if ($detail) {
        $lineItems["line_items[{$index}][price_data][product_data][description]"] = $detail;
    }
    $lineItems["line_items[{$index}][price_data][unit_amount]"] = $unitAmountCents;
    $lineItems["line_items[{$index}][quantity]"] = $qty;
}

// Add shipping as a line item if applicable
$shipping = $subtotal >= 30 ? 0 : 5.99;
if ($shipping > 0) {
    $si = count($input['cart']);
    $lineItems["line_items[{$si}][price_data][currency]"] = 'usd';
    $lineItems["line_items[{$si}][price_data][product_data][name]"] = 'Shipping';
    $lineItems["line_items[{$si}][price_data][unit_amount]"] = intval(round($shipping * 100));
    $lineItems["line_items[{$si}][quantity]"] = 1;
}

// Build the full POST data for Stripe
$orderId = 'PF-' . time() . '-' . rand(1000, 9999);
$total = $subtotal + $shipping;

// ===== SAVE ORDER TO DATABASE =====
require_once __DIR__ . '/../admin/config.php';
$db = getDB();
$stmt = $db->prepare("INSERT INTO orders (order_id, email, first_name, last_name, address, city, state, zip, phone, cart_json, subtotal, shipping, total, status) VALUES (:oid, :email, :fn, :ln, :addr, :city, :state, :zip, :phone, :cart, :sub, :ship, :total, 'pending')");
$stmt->bindValue(':oid', $orderId);
$stmt->bindValue(':email', $email);
$stmt->bindValue(':fn', strip_tags($input['firstName'] ?? ''));
$stmt->bindValue(':ln', strip_tags($input['lastName'] ?? ''));
$stmt->bindValue(':addr', strip_tags($input['address'] ?? ''));
$stmt->bindValue(':city', strip_tags($input['city'] ?? ''));
$stmt->bindValue(':state', strip_tags($input['state'] ?? ''));
$stmt->bindValue(':zip', strip_tags($input['zip'] ?? ''));
$stmt->bindValue(':phone', strip_tags($input['phone'] ?? ''));
$stmt->bindValue(':cart', json_encode($input['cart']));
$stmt->bindValue(':sub', $subtotal);
$stmt->bindValue(':ship', $shipping);
$stmt->bindValue(':total', $total);
$stmt->execute();

$postData = array_merge($lineItems, [
    'mode' => 'payment',
    'success_url' => $baseUrl . '/success.html?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
    'cancel_url' => $baseUrl . '/checkout.html?canceled=1',
    'customer_email' => $email,
    'metadata[order_id]' => $orderId,
    'metadata[customer_name]' => substr(strip_tags(($input['firstName'] ?? '') . ' ' . ($input['lastName'] ?? '')), 0, 100),
    'metadata[shipping_address]' => substr(strip_tags(($input['address'] ?? '') . ', ' . ($input['city'] ?? '') . ', ' . ($input['state'] ?? '') . ' ' . ($input['zip'] ?? '')), 0, 200),
]);

// Call Stripe API via cURL
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $stripeSecretKey,
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Connection error: ' . $curlError]);
    http_response_code(500);
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 400 || isset($data['error'])) {
    $msg = $data['error']['message'] ?? 'Failed to create checkout session';
    echo json_encode(['error' => $msg]);
    http_response_code(400);
    exit;
}

// Update order with Stripe session ID
$upd = $db->prepare("UPDATE orders SET stripe_session_id = :sid WHERE order_id = :oid");
$upd->bindValue(':sid', $data['id']);
$upd->bindValue(':oid', $orderId);
$upd->execute();

// Return the checkout URL
echo json_encode([
    'url' => $data['url'],
    'sessionId' => $data['id'],
    'orderId' => $orderId,
]);

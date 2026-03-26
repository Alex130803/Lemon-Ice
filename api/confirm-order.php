<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../admin/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$orderId = trim($input['orderId'] ?? '');
$sessionId = trim($input['sessionId'] ?? '');

if ($orderId === '' || $sessionId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order ID or session ID']);
    exit;
}

$db = getDB();
$order = fetchOrderById($db, $orderId);

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

if ($order['stripe_session_id'] !== $sessionId) {
    http_response_code(400);
    echo json_encode(['error' => 'Session does not match order']);
    exit;
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe verification failed: ' . $curlError]);
    exit;
}

$session = json_decode($response, true);
if ($httpCode >= 400 || isset($session['error'])) {
    http_response_code(400);
    echo json_encode(['error' => $session['error']['message'] ?? 'Unable to verify payment']);
    exit;
}

$isPaid = ($session['payment_status'] ?? '') === 'paid';
if (!$isPaid) {
    http_response_code(400);
    echo json_encode(['error' => 'Payment is not completed yet']);
    exit;
}

if ($order['status'] === 'pending') {
    $stmt = $db->prepare("UPDATE orders SET status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE order_id = :oid");
    $stmt->bindValue(':oid', $orderId, SQLITE3_TEXT);
    $stmt->execute();
    $order = fetchOrderById($db, $orderId);
}

if (($order['last_email_type'] ?? '') !== 'confirmation') {
    sendOrderConfirmationEmail($db, $order);
    $order = fetchOrderById($db, $orderId);
}

echo json_encode([
    'ok' => true,
    'order' => [
        'orderId' => $order['order_id'],
        'status' => $order['status'],
        'email' => $order['email'],
        'firstName' => $order['first_name'],
        'lastName' => $order['last_name'],
        'address' => $order['address'],
        'city' => $order['city'],
        'state' => $order['state'],
        'zip' => $order['zip'],
        'shipping' => (float) $order['shipping'],
        'total' => (float) $order['total'],
        'createdAt' => $order['created_at'],
    ],
]);

<?php
/**
 * api/initialize-payment.php
 * POST endpoint — starts a Paystack transaction for a reservation.
 *
 * Requires real keys in config/paystack.php to actually work.
 * With placeholder keys, this will return a clear error instead of crashing.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$bookingRef = $input['booking_reference'] ?? '';

if (!$bookingRef) {
    echo json_encode(['success' => false, 'message' => 'Missing booking reference']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM reservations WHERE booking_reference = ?");
$stmt->execute([$bookingRef]);
$booking = $stmt->fetch();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

if (strpos(PAYSTACK_SECRET_KEY, 'PLACEHOLDER') !== false) {
    echo json_encode([
        'success' => false,
        'message' => 'Paystack is not configured yet. Add your real secret key in config/paystack.php to enable live payments.'
    ]);
    exit;
}

$amountInKobo = (int) round($booking['total_amount'] * 100); // Paystack expects the smallest currency unit
$paymentRef = 'PSK-' . $booking['booking_reference'] . '-' . time();
$callbackUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/api/verify-payment.php?ref=' . $paymentRef . '&booking=' . $bookingRef;

$result = paystackInitialize($booking['guest_email'], $amountInKobo, $paymentRef, $callbackUrl);

if (!empty($result['status'])) {
    $stmt = $db->prepare("INSERT INTO payments (reservation_id, paystack_reference, amount, type, status) VALUES (?,?,?,?,?)");
    $stmt->execute([$booking['id'], $paymentRef, $booking['total_amount'], 'full', 'pending']);

    echo json_encode([
        'success' => true,
        'authorization_url' => $result['data']['authorization_url'] ?? null,
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Paystack initialization failed.']);
}

<?php
/**
 * api/verify-payment.php
 * GET endpoint — Paystack redirects the guest here after payment.
 * Verifies the transaction server-side, then marks the booking paid + confirmed.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/functions.php';

$paymentRef = $_GET['ref'] ?? ($_GET['reference'] ?? '');
$bookingRef = $_GET['booking'] ?? '';

$db = getDB();

if (!$paymentRef || !$bookingRef) {
    header('Location: ' . BASE_URL . '/public/booking-confirmation.php?ref=' . urlencode($bookingRef) . '&error=missing_ref');
    exit;
}

$verification = paystackVerify($paymentRef);

if (!empty($verification['status']) && ($verification['data']['status'] ?? '') === 'success') {
    $stmt = $db->prepare("SELECT * FROM reservations WHERE booking_reference = ?");
    $stmt->execute([$bookingRef]);
    $booking = $stmt->fetch();

    if ($booking) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE payments SET status = 'success', gateway_response = ? WHERE paystack_reference = ?");
            $stmt->execute([json_encode($verification['data']), $paymentRef]);

            $stmt = $db->prepare("UPDATE reservations SET payment_status = 'paid', status = 'confirmed' WHERE id = ?");
            $stmt->execute([$booking['id']]);

            $stmt = $db->prepare("INSERT INTO reservation_status_log (reservation_id, old_status, new_status, note) VALUES (?,?,?,?)");
            $stmt->execute([$booking['id'], $booking['status'], 'confirmed', 'Auto-confirmed after successful Paystack payment']);

            if ($booking['guest_id']) {
                awardLoyaltyPoints($booking['guest_id'], $booking['total_amount'], $booking['id'], 'Paid booking');
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
        }
    }

    header('Location: ' . BASE_URL . '/public/booking-confirmation.php?ref=' . urlencode($bookingRef) . '&paid=1');
    exit;
}

header('Location: ' . BASE_URL . '/public/booking-confirmation.php?ref=' . urlencode($bookingRef) . '&error=payment_failed');
exit;

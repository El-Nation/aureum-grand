<?php
/**
 * api/create-booking.php
 * POST endpoint (JSON body) — validates and creates a reservation.
 * Returns booking_reference on success so the front-end can redirect
 * to the confirmation / payment page.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$categoryId   = (int) ($input['category_id'] ?? 0);
$checkIn      = $input['check_in'] ?? '';
$checkOut     = $input['check_out'] ?? '';
$adults       = (int) ($input['adults'] ?? 1);
$children     = (int) ($input['children'] ?? 0);
$promoCode    = trim($input['promo_code'] ?? '') ?: null;
$guestName    = sanitize($input['guest_name'] ?? '');
$guestEmail   = trim($input['guest_email'] ?? '');
$guestPhone   = sanitize($input['guest_phone'] ?? '');
$specialReqs  = sanitize($input['special_requests'] ?? '');

// ---- Validate ----
$validation = validateBookingRequest($categoryId, $checkIn, $checkOut, $adults, $children);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'errors' => $validation['errors']]);
    exit;
}

if (!$guestName || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'errors' => ['A valid name and email are required.']]);
    exit;
}

$db = getDB();

// ---- Price the stay ----
$stay = calculateStayTotal($categoryId, $checkIn, $checkOut, $promoCode);

// ---- Link to guest account if logged in ----
$guestId = $_SESSION['guest_id'] ?? null;

// ---- Pick an available physical room (simple first-fit assignment) ----
$stmt = $db->prepare("SELECT id FROM rooms WHERE category_id = ? AND is_active = 1 AND status != 'out_of_order'
    AND id NOT IN (
        SELECT room_id FROM reservations WHERE room_id IS NOT NULL AND status IN ('pending','confirmed','checked_in')
        AND NOT (check_out <= ? OR check_in >= ?)
    ) LIMIT 1");
$stmt->execute([$categoryId, $checkIn, $checkOut]);
$assignedRoom = $stmt->fetch();
$roomId = $assignedRoom['id'] ?? null;

$reference = generateBookingReference();

try {
    $stmt = $db->prepare("INSERT INTO reservations
        (booking_reference, guest_id, guest_name, guest_email, guest_phone, room_id, category_id,
         check_in, check_out, adults, children, nights, rate_per_night, total_amount, promo_code,
         special_requests, status, payment_status, source)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending','unpaid','direct')");

    $stmt->execute([
        $reference, $guestId, $guestName, $guestEmail, $guestPhone, $roomId, $categoryId,
        $checkIn, $checkOut, $adults, $children, $stay['nights'], $stay['avg_rate'], $stay['total'],
        $promoCode, $specialReqs
    ]);

    $reservationId = $db->lastInsertId();
    logActivity($guestId ? 'guest' : 'system', $guestId, 'Created reservation', 'reservation', $reservationId);

    // Send booking confirmation email (no-op with a clear message if SMTP isn't configured yet)
    require_once __DIR__ . '/../config/email.php';
    $stmt = $db->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->execute([$reservationId]);
    $freshBooking = $stmt->fetch();
    [$subject, $body] = emailTemplateBookingConfirmation($freshBooking);
    sendAndLogEmail('guest', $guestId, $guestEmail, $guestName, $subject, $body, $reservationId);

    echo json_encode([
        'success' => true,
        'booking_reference' => $reference,
        'reservation_id' => $reservationId,
        'total' => $stay['total'],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Could not create booking: ' . $e->getMessage()]);
}

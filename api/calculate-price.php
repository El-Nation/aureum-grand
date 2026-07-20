<?php
/**
 * api/calculate-price.php
 * GET endpoint — returns live price + availability for a category/date range.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$categoryId = (int) ($_GET['category_id'] ?? 0);
$checkIn = $_GET['check_in'] ?? '';
$checkOut = $_GET['check_out'] ?? '';
$promoCode = trim($_GET['promo_code'] ?? '') ?: null;

if (!$categoryId || !$checkIn || !$checkOut || $checkOut <= $checkIn) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$availability = isCategoryAvailable($categoryId, $checkIn, $checkOut);
$stay = calculateStayTotal($categoryId, $checkIn, $checkOut, $promoCode);

echo json_encode([
    'success'     => true,
    'available'   => $availability['available'],
    'reason'      => $availability['reason'] ?? null,
    'rooms_left'  => $availability['rooms_left'] ?? null,
    'nights'      => $stay['nights'],
    'avg_rate'    => $stay['avg_rate'],
    'total'       => $stay['total'],
]);

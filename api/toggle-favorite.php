<?php
/**
 * api/toggle-favorite.php
 * POST endpoint — adds/removes a room category from the logged-in guest's favorites.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isGuestLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please sign in to save favorites.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$roomId = (int) ($input['room_id'] ?? 0);
$guestId = $_SESSION['guest_id'];

if (!$roomId) {
    echo json_encode(['success' => false, 'message' => 'Missing room id']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM guest_favorites WHERE guest_id = ? AND room_id = ?");
$stmt->execute([$guestId, $roomId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $db->prepare("DELETE FROM guest_favorites WHERE id = ?");
    $stmt->execute([$existing['id']]);
    echo json_encode(['success' => true, 'favorited' => false]);
} else {
    $stmt = $db->prepare("INSERT INTO guest_favorites (guest_id, room_id) VALUES (?, ?)");
    $stmt->execute([$guestId, $roomId]);
    echo json_encode(['success' => true, 'favorited' => true]);
}

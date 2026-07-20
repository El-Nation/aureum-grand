<?php
/**
 * api/ai-concierge-chat.php
 * POST endpoint — relays a guest message to the AI Concierge and returns the reply.
 * Conversation history is kept in the PHP session so context carries across turns.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai-concierge.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'Please enter a message.']);
    exit;
}

if (!isset($_SESSION['concierge_history'])) {
    $_SESSION['concierge_history'] = [];
}

// Cap history length so the session doesn't grow unbounded
if (count($_SESSION['concierge_history']) > 20) {
    $_SESSION['concierge_history'] = array_slice($_SESSION['concierge_history'], -20);
}

$result = askAIConcierge($message, $_SESSION['concierge_history']);

if ($result['success']) {
    $_SESSION['concierge_history'][] = ['role' => 'user', 'content' => $message];
    $_SESSION['concierge_history'][] = ['role' => 'assistant', 'content' => $result['reply']];

    // Log to service_requests so staff can see concierge conversations if needed
    $db = getDB();
    $guestId = $_SESSION['guest_id'] ?? null;
    if ($guestId) {
        logActivity('guest', $guestId, 'AI Concierge interaction', null, null, substr($message, 0, 200));
    }
}

echo json_encode($result);

<?php
/**
 * config/ai-concierge.php — AI Concierge Assistant powered by Claude.
 *
 * To activate:
 * 1. Get an API key from https://console.anthropic.com (Settings > API Keys)
 * 2. Paste it below
 *
 * The assistant is given context about the hotel (rooms, services,
 * policies) so it can answer guest questions and make recommendations
 * without needing a separate knowledge base or vector database.
 */

// ---- REPLACE WITH YOUR REAL ANTHROPIC API KEY --------------------
define('ANTHROPIC_API_KEY', 'PLACEHOLDER_ANTHROPIC_API_KEY');
define('ANTHROPIC_MODEL', 'claude-sonnet-4-6');
// ---------------------------------------------------------------------

define('AI_CONCIERGE_CONFIGURED', strpos(ANTHROPIC_API_KEY, 'PLACEHOLDER') === false);

/**
 * Send a guest message to the AI Concierge and get a reply.
 * $conversationHistory is an array of ['role' => 'user'|'assistant', 'content' => '...']
 * so the assistant can hold a multi-turn conversation.
 */
function askAIConcierge($guestMessage, $conversationHistory = []) {
    if (!AI_CONCIERGE_CONFIGURED) {
        return [
            'success' => false,
            'message' => 'The AI Concierge is not configured yet. Add a real Anthropic API key in config/ai-concierge.php.',
        ];
    }

    $systemPrompt = buildConciergeSystemPrompt();

    $messages = $conversationHistory;
    $messages[] = ['role' => 'user', 'content' => $guestMessage];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => ANTHROPIC_MODEL,
        'max_tokens' => 600,
        'system' => $systemPrompt,
        'messages' => $messages,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => "Connection error: $error"];
    }

    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['content'][0]['text'])) {
        return ['success' => true, 'reply' => $data['content'][0]['text']];
    }

    return ['success' => false, 'message' => $data['error']['message'] ?? 'The AI Concierge could not respond right now.'];
}

/**
 * Builds the system prompt that grounds the assistant in real hotel data
 * (rooms, pricing, services) so its answers are accurate rather than
 * generic. Pulls live data from the database each time it's called.
 */
function buildConciergeSystemPrompt() {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();

    $rooms = $db->query("SELECT name, description, base_price, max_occupancy, view_type FROM room_categories WHERE is_active = 1")->fetchAll();

    $roomSummary = '';
    foreach ($rooms as $r) {
        $roomSummary .= "- {$r['name']} ({$r['view_type']}, up to {$r['max_occupancy']} guests): ₦" . number_format($r['base_price']) . "/night. {$r['description']}\n";
    }

    return <<<PROMPT
You are the AI Concierge for Aureum Grand Hotel, a five-star hotel in Victoria Island, Lagos, Nigeria.

Your job: help guests with questions about rooms, pricing, amenities, local recommendations, and hotel services (spa, dining, airport transfer, room service). Be warm, concise, and specific. If a guest wants to book or modify a reservation, direct them to the booking page or front desk rather than claiming to complete the action yourself, since you cannot directly modify reservations.

Current room categories and rates:
{$roomSummary}

Hotel services available: airport transfers, chauffeur service, spa appointments, restaurant reservations, tour bookings, event tickets, room service.

Loyalty program tiers: Silver, Gold, Platinum, VIP — guests earn 1 point per ₦1,000 spent.

Keep replies under 100 words unless the guest asks for something detailed. Never invent room types, prices, or policies not listed above.
PROMPT;
}

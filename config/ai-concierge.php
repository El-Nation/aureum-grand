<?php
/**
 * config/ai-concierge.php — AI Concierge Assistant powered by Groq (free tier).
 *
 * To activate:
 * 1. Go to https://console.groq.com/keys
 * 2. Sign up / log in (free — no credit card needed)
 * 3. Click "Create API Key" and paste it below
 *
 * Model used: llama-3.1-8b-instant  (fast, generous free quota, very capable)
 * Free limits: ~14,400 requests/day — more than enough for a hotel concierge.
 *
 * The assistant is given context about the hotel (rooms, services, policies)
 * so it can answer guest questions and make recommendations without a
 * separate knowledge base or vector database.
 */

// ---- REPLACE WITH YOUR REAL GROQ API KEY --------------------------------
define('GROQ_API_KEY', 'PASTE_YOUR_GROQ_API_KEY_HERE');
define('GROQ_MODEL',   'llama-3.1-8b-instant');
// -------------------------------------------------------------------------

define('AI_CONCIERGE_CONFIGURED', strpos(GROQ_API_KEY, 'PASTE_YOUR') === false);

/**
 * Send a guest message to the AI Concierge and get a reply.
 *
 * $conversationHistory is an array of
 *   ['role' => 'user'|'assistant', 'content' => '...']
 * so the assistant can hold a multi-turn conversation.
 */
function askAIConcierge($guestMessage, $conversationHistory = []) {
    if (!AI_CONCIERGE_CONFIGURED) {
        return [
            'success' => false,
            'message' => 'The AI Concierge is not configured yet. '
                       . 'Add a Groq API key in config/ai-concierge.php. '
                       . 'Get one free at https://console.groq.com/keys',
        ];
    }

    $systemPrompt = buildConciergeSystemPrompt();

    // Build messages array: system prompt first, then conversation history, then new message
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];

    foreach ($conversationHistory as $turn) {
        $messages[] = [
            'role'    => $turn['role'],   // 'user' or 'assistant'
            'content' => $turn['content'],
        ];
    }

    $messages[] = ['role' => 'user', 'content' => $guestMessage];

    $payload = json_encode([
        'model'       => GROQ_MODEL,
        'messages'    => $messages,
        'max_tokens'  => 600,
        'temperature' => 0.7,
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            'https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => "Connection error: $error"];
    }

    $data = json_decode($response, true);

    // Groq uses OpenAI-compatible response format:
    // { "choices": [{ "message": { "content": "..." } }] }
    if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
        return [
            'success' => true,
            'reply'   => $data['choices'][0]['message']['content'],
        ];
    }

    $errorMsg = $data['error']['message']
             ?? 'The AI Concierge could not respond right now. Please try again.';

    return ['success' => false, 'message' => $errorMsg];
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

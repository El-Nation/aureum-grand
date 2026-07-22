<?php
/**
 * config/whatsapp.php — Guest messaging via WhatsApp Business Cloud API.
 *
 * To activate:
 * 1. Create a Meta Business account at https://business.facebook.com
 * 2. Set up WhatsApp Business Platform at https://developers.facebook.com/docs/whatsapp
 * 3. Get your Phone Number ID and a permanent access token from the
 *    Meta App Dashboard (WhatsApp > API Setup)
 * 4. Paste both values below
 *
 * Important: Meta requires pre-approved "message templates" for any
 * message that isn't a reply within a 24-hour customer-initiated window.
 * The templates referenced below (booking_confirmation, arrival_reminder)
 * must be created and approved in the Meta Business dashboard first —
 * this is a Meta requirement, not something any code can bypass.
 */

// ---- REPLACE WITH YOUR REAL META CREDENTIALS --------------------
define('WHATSAPP_PHONE_NUMBER_ID', 'PLACEHOLDER_PHONE_NUMBER_ID');
define('WHATSAPP_ACCESS_TOKEN', 'PLACEHOLDER_ACCESS_TOKEN');
define('WHATSAPP_API_VERSION', 'v21.0');
// ---------------------------------------------------------------------

define('WHATSAPP_CONFIGURED', strpos(WHATSAPP_ACCESS_TOKEN, 'PLACEHOLDER') === false);

/**
 * Send a free-form text message. Only works within 24 hours of the guest
 * messaging you first (Meta's "customer service window" rule) — otherwise
 * use sendWhatsAppTemplate() below.
 */
function sendWhatsAppMessage($toNumber, $message) {
    if (!WHATSAPP_CONFIGURED) {
        return ['success' => false, 'message' => 'WhatsApp is not configured yet. Add real credentials in config/whatsapp.php.'];
    }

    $url = "https://graph.facebook.com/" . WHATSAPP_API_VERSION . "/" . WHATSAPP_PHONE_NUMBER_ID . "/messages";

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => preg_replace('/[^0-9]/', '', $toNumber), // E.164 digits only, no '+'
        'type' => 'text',
        'text' => ['body' => $message],
    ];

    return whatsappApiCall($url, $payload);
}

/**
 * Send a pre-approved template message — required for the first message
 * to a guest, or any message sent more than 24 hours after their last
 * reply. $templateName must match a template you've created and had
 * approved in the Meta Business dashboard.
 */
function sendWhatsAppTemplate($toNumber, $templateName, $languageCode, $bodyParams = []) {
    if (!WHATSAPP_CONFIGURED) {
        return ['success' => false, 'message' => 'WhatsApp is not configured yet. Add real credentials in config/whatsapp.php.'];
    }

    $url = "https://graph.facebook.com/" . WHATSAPP_API_VERSION . "/" . WHATSAPP_PHONE_NUMBER_ID . "/messages";

    $components = [];
    if (!empty($bodyParams)) {
        $components[] = [
            'type' => 'body',
            'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string) $p], $bodyParams),
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => preg_replace('/[^0-9]/', '', $toNumber),
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
            'components' => $components,
        ],
    ];

    return whatsappApiCall($url, $payload);
}

function whatsappApiCall($url, $payload) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => "Connection error: $error"];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'WhatsApp message sent.', 'message_id' => $data['messages'][0]['id'] ?? null];
    }

    return ['success' => false, 'message' => $data['error']['message'] ?? 'Meta API rejected the request.'];
}

/**
 * Wraps the send functions with logging to the notifications table.
 */
function sendAndLogWhatsApp($recipientType, $recipientId, $toNumber, $message, $relatedReservationId = null) {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();

    $result = sendWhatsAppMessage($toNumber, $message);

    $stmt = $db->prepare("INSERT INTO notifications (recipient_type, recipient_id, channel, subject, message, status, related_reservation_id) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $recipientType, $recipientId, 'whatsapp', 'WhatsApp Notification', $message,
        $result['success'] ? 'sent' : 'failed', $relatedReservationId
    ]);

    return $result;
}

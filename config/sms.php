<?php
/**
 * config/sms.php — SMS notifications via Twilio.
 *
 * To activate:
 * 1. Create a free account at https://www.twilio.com
 * 2. Get a phone number (Twilio gives you a free trial number)
 * 3. Copy your Account SID and Auth Token from the Twilio Console dashboard
 * 4. Paste all 3 values below
 *
 * Note: Termii (https://termii.com) is a popular Nigeria-focused
 * alternative with similar REST API shape — swap the endpoint/auth in
 * sendSMS() below if you'd rather use that.
 */

// ---- REPLACE WITH YOUR REAL TWILIO CREDENTIALS --------------------
define('TWILIO_ACCOUNT_SID', 'PLACEHOLDER_ACCOUNT_SID');
define('TWILIO_AUTH_TOKEN', 'PLACEHOLDER_AUTH_TOKEN');
define('TWILIO_FROM_NUMBER', '+10000000000'); // your Twilio phone number, in E.164 format
// ----------------------------------------------------------------------

define('SMS_CONFIGURED', strpos(TWILIO_ACCOUNT_SID, 'PLACEHOLDER') === false);

/**
 * Send an SMS via Twilio's REST API.
 * $toNumber must be in E.164 format, e.g. +2348012345678
 */
function sendSMS($toNumber, $message) {
    if (!SMS_CONFIGURED) {
        return ['success' => false, 'message' => 'Twilio is not configured yet. Add real credentials in config/sms.php.'];
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_ACCOUNT_SID . "/Messages.json";

    $fields = [
        'To' => $toNumber,
        'From' => TWILIO_FROM_NUMBER,
        'Body' => $message,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => "Connection error: $error"];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'message' => 'SMS sent.', 'sid' => $data['sid'] ?? null];
    }

    return ['success' => false, 'message' => $data['message'] ?? 'Twilio rejected the request.'];
}

/**
 * Wraps sendSMS() with logging to the notifications table.
 */
function sendAndLogSMS($recipientType, $recipientId, $toNumber, $message, $relatedReservationId = null) {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();

    $result = sendSMS($toNumber, $message);

    $stmt = $db->prepare("INSERT INTO notifications (recipient_type, recipient_id, channel, subject, message, status, related_reservation_id) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $recipientType, $recipientId, 'sms', 'SMS Notification', $message,
        $result['success'] ? 'sent' : 'failed', $relatedReservationId
    ]);

    return $result;
}

/** Pre-built SMS templates */
function smsTemplateBookingConfirmation($booking) {
    return "Aureum Grand: Your booking {$booking['booking_reference']} is confirmed for {$booking['check_in']} to {$booking['check_out']}. Total: NGN " . number_format($booking['total_amount']) . ". We look forward to hosting you.";
}

function smsTemplateArrivalReminder($booking) {
    return "Aureum Grand: Reminder — your check-in is tomorrow ({$booking['check_in']}). Reference: {$booking['booking_reference']}. Standard check-in is from 2:00 PM.";
}

function smsTemplateVIPArrivalAlert($booking) {
    return "VIP ALERT: {$booking['guest_name']} (Ref: {$booking['booking_reference']}) is arriving today. Please prepare a personalized welcome.";
}

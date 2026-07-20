<?php
/**
 * config/email.php — SMTP email sending.
 *
 * Works with any SMTP provider: Gmail, SendGrid, Mailgun, Zoho, your own
 * mail server, etc. Uses PHP's built-in socket functions to speak SMTP
 * directly — no Composer/PHPMailer dependency, so it runs anywhere PHP runs.
 *
 * To activate: fill in the 5 values below with your provider's details.
 */

// ---- REPLACE WITH YOUR REAL SMTP CREDENTIALS --------------------
define('SMTP_HOST', 'smtp.PLACEHOLDER.com');       // e.g. smtp.gmail.com, smtp.sendgrid.net
define('SMTP_PORT', 587);                           // 587 (TLS) or 465 (SSL) are most common
define('SMTP_USERNAME', 'your-email@PLACEHOLDER.com');
define('SMTP_PASSWORD', 'PLACEHOLDER_APP_PASSWORD'); // for Gmail, use an "app password", not your login password
define('SMTP_FROM_NAME', 'Aureum Grand Hotel');
define('SMTP_FROM_EMAIL', 'reservations@aureumgrand.com');
// -------------------------------------------------------------------

define('SMTP_CONFIGURED', strpos(SMTP_HOST, 'PLACEHOLDER') === false);

/**
 * Send an email via SMTP using raw sockets (no external library required).
 * Returns ['success' => bool, 'message' => string].
 */
function sendEmail($toEmail, $toName, $subject, $bodyHtml) {
    if (!SMTP_CONFIGURED) {
        return ['success' => false, 'message' => 'SMTP is not configured yet. Add real credentials in config/email.php.'];
    }

    $useTLS = (SMTP_PORT == 587);
    $useSSL = (SMTP_PORT == 465);

    $transport = $useSSL ? 'ssl://' . SMTP_HOST : SMTP_HOST;
    $socket = @fsockopen($transport, SMTP_PORT, $errno, $errstr, 10);

    if (!$socket) {
        return ['success' => false, 'message' => "Could not connect to SMTP server: $errstr ($errno)"];
    }

    stream_set_timeout($socket, 10);

    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break; // SMTP multi-line response ends with "CODE "
        }
        return $data;
    };

    $write = function ($cmd) use ($socket) {
        fwrite($socket, $cmd . "\r\n");
    };

    $read(); // banner
    $write('EHLO ' . SMTP_HOST);
    $read();

    if ($useTLS) {
        $write('STARTTLS');
        $read();
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $write('EHLO ' . SMTP_HOST);
        $read();
    }

    $write('AUTH LOGIN');
    $read();
    $write(base64_encode(SMTP_USERNAME));
    $read();
    $write(base64_encode(SMTP_PASSWORD));
    $authResponse = $read();

    if (strpos($authResponse, '235') === false) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP authentication failed. Check your username/password in config/email.php.'];
    }

    $write('MAIL FROM:<' . SMTP_FROM_EMAIL . '>');
    $read();
    $write('RCPT TO:<' . $toEmail . '>');
    $read();
    $write('DATA');
    $read();

    $boundary = md5(time());
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "To: $toName <$toEmail>\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "\r\n";

    $write($headers . $bodyHtml . "\r\n.");
    $finalResponse = $read();

    $write('QUIT');
    fclose($socket);

    if (strpos($finalResponse, '250') !== false) {
        return ['success' => true, 'message' => 'Email sent.'];
    }
    return ['success' => false, 'message' => 'SMTP server rejected the message: ' . trim($finalResponse)];
}

/**
 * Wraps sendEmail() with logging to the notifications table, so every
 * attempt (success or failure) is tracked and visible in the admin console.
 */
function sendAndLogEmail($recipientType, $recipientId, $toEmail, $toName, $subject, $bodyHtml, $relatedReservationId = null) {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();

    $result = sendEmail($toEmail, $toName, $subject, $bodyHtml);

    $stmt = $db->prepare("INSERT INTO notifications (recipient_type, recipient_id, channel, subject, message, status, related_reservation_id) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $recipientType, $recipientId, 'email', $subject, $bodyHtml,
        $result['success'] ? 'sent' : 'failed', $relatedReservationId
    ]);

    return $result;
}

/**
 * Pre-built email templates for common hotel events, so you're not
 * writing HTML from scratch when you wire a trigger point.
 */
function emailTemplateBookingConfirmation($booking) {
    $subject = "Booking Confirmed — {$booking['booking_reference']}";
    $body = "
    <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;'>
      <h2 style='color:#14322a;'>Your stay is confirmed</h2>
      <p>Dear {$booking['guest_name']},</p>
      <p>Thank you for booking with Aureum Grand Hotel. Here are your reservation details:</p>
      <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
        <tr><td style='padding:8px;color:#8a6e52;'>Reference</td><td style='padding:8px;'><strong>{$booking['booking_reference']}</strong></td></tr>
        <tr><td style='padding:8px;color:#8a6e52;'>Check-in</td><td style='padding:8px;'>{$booking['check_in']}</td></tr>
        <tr><td style='padding:8px;color:#8a6e52;'>Check-out</td><td style='padding:8px;'>{$booking['check_out']}</td></tr>
        <tr><td style='padding:8px;color:#8a6e52;'>Total</td><td style='padding:8px;'><strong>₦" . number_format($booking['total_amount']) . "</strong></td></tr>
      </table>
      <p>We look forward to welcoming you.</p>
      <p style='color:#8a6e52;font-size:0.85rem;'>Aureum Grand Hotel · Victoria Island, Lagos</p>
    </div>";
    return [$subject, $body];
}

function emailTemplateArrivalReminder($booking) {
    $subject = "See you tomorrow — {$booking['booking_reference']}";
    $body = "
    <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;'>
      <h2 style='color:#14322a;'>Your stay begins tomorrow</h2>
      <p>Dear {$booking['guest_name']},</p>
      <p>This is a friendly reminder that your check-in at Aureum Grand Hotel is scheduled for tomorrow, {$booking['check_in']}.</p>
      <p>Reference: <strong>{$booking['booking_reference']}</strong></p>
      <p>Standard check-in time is from 2:00 PM. If you need an early check-in, reply to this email or call our front desk.</p>
      <p style='color:#8a6e52;font-size:0.85rem;'>Aureum Grand Hotel · Victoria Island, Lagos</p>
    </div>";
    return [$subject, $body];
}

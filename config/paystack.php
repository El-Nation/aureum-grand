<?php
/**
 * AUREUM HOTEL PLATFORM — Paystack Configuration
 * ------------------------------------------------
 * Sign up at https://paystack.com, get your API keys from
 * Settings → API Keys & Webhooks, and paste them below.
 *
 * NEVER commit real secret keys to a public git repo.
 * For production, load these from environment variables instead.
 */

// ---- REPLACE WITH YOUR REAL KEYS --------------------------------
define('PAYSTACK_PUBLIC_KEY', 'pk_test_PLACEHOLDER_PUBLIC_KEY');
define('PAYSTACK_SECRET_KEY', 'sk_test_PLACEHOLDER_SECRET_KEY');
// ------------------------------------------------------------------

define('PAYSTACK_CURRENCY', 'NGN');

/**
 * Initialize a Paystack transaction (called from api/initialize-payment.php)
 */
function paystackInitialize($email, $amountInKobo, $reference, $callbackUrl) {
    $url = "https://api.paystack.co/transaction/initialize";

    $fields = [
        'email'        => $email,
        'amount'       => $amountInKobo,
        'reference'    => $reference,
        'currency'     => PAYSTACK_CURRENCY,
        'callback_url' => $callbackUrl,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Content-Type: application/json",
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['status' => false, 'message' => $error];
    }
    return json_decode($response, true);
}

/**
 * Verify a Paystack transaction (called after redirect back from Paystack)
 */
function paystackVerify($reference) {
    $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['status' => false, 'message' => $error];
    }
    return json_decode($response, true);
}

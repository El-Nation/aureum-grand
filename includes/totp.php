<?php
/**
 * includes/totp.php — Self-contained TOTP (RFC 6238) implementation.
 *
 * Why hand-rolled instead of a Composer package? Most shared PHP hosting
 * doesn't have Composer set up, and this whole project is designed to be
 * "upload and run." TOTP itself is a short, well-defined algorithm, so a
 * dependency-free version keeps things portable. It's compatible with
 * Google Authenticator, Authy, 1Password, etc.
 */

class TOTP {

    /**
     * Generate a new random base32 secret (for a staff member setting up 2FA).
     */
    public static function generateSecret($length = 16) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // RFC 4648 base32 alphabet
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Build the otpauth:// URI used to generate a QR code for the
     * authenticator app (Google Authenticator, Authy, etc).
     */
    public static function getProvisioningUri($secret, $accountName, $issuer = 'Aureum Console') {
        $label = rawurlencode($issuer . ':' . $accountName);
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ];
        return 'otpauth://totp/' . $label . '?' . http_build_query($params);
    }

    /**
     * Generate the current 6-digit code for a given secret (used internally
     * and for testing — staff will normally get this from their own app).
     */
    public static function getCode($secret, $timestamp = null) {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, 30);
        return self::generateHotp($secret, $counter);
    }

    /**
     * Verify a 6-digit code submitted by the user, allowing ±1 time-step
     * (30 seconds) of clock drift — this is standard practice for TOTP.
     */
    public static function verify($secret, $code, $window = 1) {
        $code = preg_replace('/\s+/', '', (string) $code);
        $timestamp = time();
        $counter = intdiv($timestamp, 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (self::generateHotp($secret, $counter + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    private static function generateHotp($secret, $counter) {
        $key = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        $offset = ord($hash[19]) & 0xf;
        $truncated = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));
        $bits = '';
        foreach (str_split($input) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        return $bytes;
    }

    /**
     * Build a QR code image URL using a free public QR API, so staff can
     * scan it with their phone during setup without needing any local
     * image-generation library. (No account or key required.)
     */
    public static function getQrCodeUrl($provisioningUri, $size = 240) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&data=' . rawurlencode($provisioningUri);
    }
}

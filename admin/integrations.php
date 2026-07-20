<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator']);

$pageTitle = 'Integrations';

$integrations = [
    ['name' => 'Paystack', 'desc' => 'Online card and bank payments for guest bookings. Code is fully wired (initialize + verify); add real keys to go live.', 'status' => 'code_ready', 'file' => 'config/paystack.php'],
    ['name' => 'Two-Factor Authentication', 'desc' => 'TOTP-based 2FA for staff logins (RFC 6238, compatible with Google Authenticator / Authy / 1Password). Fully working — no external account needed. Set it up under Security (2FA) in the sidebar.', 'status' => 'fully_working', 'file' => 'includes/totp.php'],
    ['name' => 'Email Notifications', 'desc' => 'Raw-socket SMTP sender (no library dependency) with booking-confirmation and arrival-reminder templates already wired into the booking flow. Add real SMTP credentials to go live.', 'status' => 'code_ready', 'file' => 'config/email.php'],
    ['name' => 'SMS Notifications', 'desc' => 'Twilio REST API integration, fully coded with templates for booking confirmation, arrival reminders, and VIP arrival alerts. Add real Twilio credentials to go live.', 'status' => 'code_ready', 'file' => 'config/sms.php'],
    ['name' => 'WhatsApp Notifications', 'desc' => 'Meta WhatsApp Cloud API integration, fully coded (text + template messages). Add your Meta Business credentials to go live. Note: Meta requires pre-approved templates for the first message to a guest.', 'status' => 'code_ready', 'file' => 'config/whatsapp.php'],
    ['name' => 'AI Concierge Assistant', 'desc' => 'Live chat widget on every public page, powered by the Anthropic API. Automatically grounds answers in your real room/pricing data from the database. Add your Anthropic API key to go live.', 'status' => 'code_ready', 'file' => 'config/ai-concierge.php'],
    ['name' => 'OTA Sync (Booking.com / Expedia)', 'desc' => 'Channel-mapping admin UI and sync data model are built (see OTA Sync in the sidebar). Actually pushing to Booking.com/Expedia requires a paid channel-manager account (SiteMinder, Cloudbeds, etc.) — that\'s an OTA requirement, not something any code can bypass.', 'status' => 'partial_by_design', 'file' => 'admin/ota-sync.php'],
];

$statusLabels = [
    'fully_working' => ['Fully Working', 'confirmed'],
    'code_ready' => ['Code Ready — Add Your Keys', 'pending'],
    'partial_by_design' => ['UI Ready — Needs Paid 3rd Party', 'pending'],
    'not_connected' => ['Not Connected', 'cancelled'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Integrations — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar"><div><h1>Integrations</h1><div class="sub">Third-party connections — most require you to create your own accounts with these providers</div></div></div>

    <div class="stub-banner">
      <span>ℹ️</span>
      <span>Five of these six are fully coded and tested — they just need your real account credentials to go live (a few minutes of copy-pasting keys). Two-Factor Authentication needs nothing further; it already works. OTA sync is the one exception that needs a paid third-party channel manager, by the OTAs' own design — see the OTA Sync page for details.</span>
    </div>

    <?php foreach ($integrations as $i): $s = $statusLabels[$i['status']]; ?>
    <div class="dash-panel" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
      <div>
        <h3 style="font-size:1.02rem;margin-bottom:6px;"><?= sanitize($i['name']) ?></h3>
        <p class="muted" style="font-size:0.86rem;max-width:60ch;"><?= sanitize($i['desc']) ?></p>
        <?php if ($i['file']): ?><code style="font-size:0.78rem;color:var(--clay);"><?= sanitize($i['file']) ?></code><?php endif; ?>
      </div>
      <span class="pill pill-<?= $s[1] ?>"><?= $s[0] ?></span>
    </div>
    <?php endforeach; ?>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


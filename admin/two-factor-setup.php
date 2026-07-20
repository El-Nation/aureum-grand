<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';
requireStaffLogin();

$db = getDB();
$staffId = $_SESSION['staff_id'];

$stmt = $db->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

$message = '';
$error = '';

// Step 1: generate a pending secret (not yet saved as enabled)
if (!isset($_SESSION['pending_2fa_secret']) && !$staff['two_factor_enabled']) {
    $_SESSION['pending_2fa_secret'] = TOTP::generateSecret();
}

// Disable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable_2fa') {
    $stmt = $db->prepare("UPDATE staff SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
    $stmt->execute([$staffId]);
    logActivity('staff', $staffId, 'Disabled two-factor authentication');
    header('Location: ' . BASE_URL . '/admin/two-factor-setup.php?msg=disabled');
    exit;
}

// Confirm and enable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_2fa') {
    $secret = $_SESSION['pending_2fa_secret'] ?? '';
    $code = $_POST['code'] ?? '';

    if ($secret && TOTP::verify($secret, $code)) {
        $stmt = $db->prepare("UPDATE staff SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
        $stmt->execute([$secret, $staffId]);
        unset($_SESSION['pending_2fa_secret']);
        logActivity('staff', $staffId, 'Enabled two-factor authentication');
        header('Location: ' . BASE_URL . '/admin/two-factor-setup.php?msg=enabled');
        exit;
    } else {
        $error = 'That code didn\'t match. Make sure your authenticator app clock is in sync and try again.';
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'] === 'enabled' ? 'Two-factor authentication is now enabled on your account.' : 'Two-factor authentication has been disabled.';
}

$pageTitle = 'Two-Factor Authentication';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Two-Factor Authentication — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>Two-Factor Authentication</h1><div class="sub">Add an extra layer of security to your staff account</div></div>
    </div>

    <?php if ($message): ?><div class="form-success"><?= sanitize($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-error"><?= sanitize($error) ?></div><?php endif; ?>

    <div class="dash-panel" style="max-width:560px;">
      <?php if ($staff['two_factor_enabled']): ?>
        <div class="dash-panel-head"><h3>Status: Enabled</h3><span class="pill pill-confirmed">Active</span></div>
        <p class="muted" style="margin-bottom:20px;">Your account is protected with an authenticator app code at every sign-in.</p>
        <form method="POST" onsubmit="return confirm('Disable two-factor authentication on this account?');">
          <input type="hidden" name="action" value="disable_2fa">
          <button type="submit" class="btn btn-outline">Disable Two-Factor Authentication</button>
        </form>

      <?php else: ?>
        <div class="dash-panel-head"><h3>Set Up Two-Factor Authentication</h3><span class="pill pill-pending">Not Enabled</span></div>

        <ol style="font-size:0.9rem;line-height:1.8;margin-left:18px;margin-bottom:24px;">
          <li>Install an authenticator app if you don't have one — Google Authenticator, Authy, or 1Password all work.</li>
          <li>Scan the QR code below, or enter the setup key manually.</li>
          <li>Enter the 6-digit code your app shows to confirm setup.</li>
        </ol>

        <?php
          $secret = $_SESSION['pending_2fa_secret'];
          $uri = TOTP::getProvisioningUri($secret, $staff['email']);
          $qrUrl = TOTP::getQrCodeUrl($uri);
        ?>

        <div class="text-center" style="margin-bottom:20px;">
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="Scan with your authenticator app" style="border:1px solid var(--line);border-radius:8px;">
        </div>

        <div class="dash-form-group">
          <label>Can't scan? Enter this setup key manually:</label>
          <input type="text" readonly value="<?= sanitize($secret) ?>" style="font-family:var(--font-mono);letter-spacing:0.05em;" onclick="this.select()">
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="confirm_2fa">
          <div class="dash-form-group">
            <label>Enter the 6-digit code from your app</label>
            <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autocomplete="one-time-code" style="font-size:1.3rem;letter-spacing:0.3em;text-align:center;max-width:200px;">
          </div>
          <button type="submit" class="btn btn-primary">Confirm &amp; Enable</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';

if (isStaffLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$errors = [];
$awaitingTwoFactor = isset($_SESSION['2fa_pending_staff_id']);

// Step 2 of login: verify the 6-digit code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code'])) {
    $staffId = $_SESSION['2fa_pending_staff_id'] ?? null;
    if (!$staffId) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT s.*, r.name AS role_name FROM staff s JOIN roles r ON s.role_id = r.id WHERE s.id = ?");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch();

    if ($staff && TOTP::verify($staff['two_factor_secret'], $_POST['totp_code'])) {
        unset($_SESSION['2fa_pending_staff_id']);
        $_SESSION['staff_id'] = $staff['id'];
        $_SESSION['staff_name'] = $staff['full_name'];
        $_SESSION['staff_email'] = $staff['email'];
        $_SESSION['staff_role'] = $staff['role_name'];
        logActivity('staff', $staff['id'], 'Logged in (2FA verified)');
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    } else {
        $errors[] = 'Incorrect authentication code. Please try again.';
        $awaitingTwoFactor = true;
    }
}

// Step 1 of login: email + password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $db = getDB();
    $stmt = $db->prepare("SELECT s.*, r.name AS role_name FROM staff s JOIN roles r ON s.role_id = r.id WHERE s.email = ? AND s.is_active = 1");
    $stmt->execute([$email]);
    $staff = $stmt->fetch();

    // NOTE: the seed accounts in sql/schema.sql already ship with a real
    // password hash for "Aureum2026!" so you can log in immediately after
    // importing the schema. To set a different password, generate a new
    // hash with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
    // then run: UPDATE staff SET password_hash = '...paste hash here...' WHERE email = '...';

    if ($staff && password_verify($password, $staff['password_hash'])) {
        if ($staff['two_factor_enabled']) {
            // Don't fully log in yet — hold the session at "pending 2FA"
            $_SESSION['2fa_pending_staff_id'] = $staff['id'];
            $awaitingTwoFactor = true;
        } else {
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['staff_name'] = $staff['full_name'];
            $_SESSION['staff_email'] = $staff['email'];
            $_SESSION['staff_role'] = $staff['role_name'];
            logActivity('staff', $staff['id'], 'Logged in');
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        }
    } else {
        $errors[] = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Sign In — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
  /* Override default split layout */
  .auth-shell-single {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(160deg, var(--emerald-deep), var(--emerald));
    padding: 20px;
    position: relative;
  }
  .auth-shell-single::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle at 20% 20%, rgba(176,138,78,0.18), transparent 40%),
                      radial-gradient(circle at 80% 70%, rgba(176,138,78,0.12), transparent 45%);
    pointer-events: none;
  }
</style>
</head>
<body>

<div class="auth-shell-single">
  <div class="auth-form-wrap" style="width: 100%; max-width: 440px; position: relative; z-index: 2;">
    <div class="auth-form" style="background: #fff; padding: 56px 48px; border-radius: var(--radius-lg); box-shadow: 0 24px 64px rgba(0,0,0,0.3); border: 1px solid var(--line);">
      
      <div style="text-align: center; margin-bottom: 32px;">
        <img src="<?= BASE_URL ?>/assets/images/logo.jpeg" alt="Aureum Grand" style="height: 60px; margin-bottom: 16px; border-radius: 4px;">
        <p class="sub" style="margin-top: 0; font-size: 0.95rem; color: var(--clay);">Operational control &amp; staff access.</p>
      </div>

      <?php if ($errors): ?>
        <div class="form-error"><?= implode('<br>', array_map('sanitize', $errors)) ?></div>
      <?php endif; ?>

      <?php if ($awaitingTwoFactor): ?>
        <p class="sub" style="margin-bottom:20px; text-align: center;">Enter the 6-digit code from your authenticator app.</p>
        <form method="POST">
          <div class="form-group">
            <label>Authentication Code</label>
            <input type="text" name="totp_code" maxlength="6" pattern="[0-9]{6}" required autofocus
                   autocomplete="one-time-code" style="font-size:1.3rem;letter-spacing:0.3em;text-align:center;">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Verify &amp; Sign In</button>
        </form>
        <div class="auth-switch" style="text-align: center; margin-top: 24px;"><a href="<?= BASE_URL ?>/admin/login.php">&larr; Start over</a></div>

      <?php else: ?>
      <form method="POST">
        <div class="form-group">
          <label>Staff Email</label>
          <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>" placeholder="admin@aureumgrand.com" style="background: #faf9f5;">
        </div>
        <div class="form-group">
          <label style="display: flex; justify-content: space-between;">
            Password
            <a href="#" style="font-size: 0.75rem; color: var(--brass); font-weight: normal;">Forgot?</a>
          </label>
          <input type="password" name="password" required placeholder="••••••••" style="background: #faf9f5;">
        </div>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top: 24px; padding: 16px; font-size: 1.05rem;">Sign In</button>
      </form>
      <?php endif; ?>

      <div class="auth-switch" style="text-align: center; margin-top: 32px; border-top: 1px solid var(--line); padding-top: 24px;">
        <a href="<?= BASE_URL ?>/public/index.php" style="color: var(--clay); font-weight: 500; transition: color 0.2s;"><span style="color:var(--brass); margin-right: 4px;">&larr;</span> Back to main site</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>

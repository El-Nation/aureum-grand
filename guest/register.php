<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isGuestLoggedIn()) {
    header('Location: ' . BASE_URL . '/guest/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$name) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM guests WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Try signing in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO guests (full_name, email, phone, password_hash) VALUES (?,?,?,?)");
            $stmt->execute([$name, $email, $phone, $hash]);
            $guestId = $db->lastInsertId();

            $_SESSION['guest_id'] = $guestId;
            $_SESSION['guest_name'] = $name;
            $_SESSION['guest_email'] = $email;

            logActivity('guest', $guestId, 'Registered new account');
            header('Location: ' . BASE_URL . '/guest/dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-shell">
  <div class="auth-visual">
    <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=900&q=80" alt="">
  </div>
  <div class="auth-form-wrap">
    <div class="auth-form">
      <span class="eyebrow">Aureum Circle</span>
      <h2 style="margin-top:14px;">Create your account</h2>
      <p class="sub">Track reservations, earn loyalty points, and check out faster next time.</p>

      <?php if ($errors): ?>
        <div class="form-error"><?= implode('<br>', array_map('sanitize', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="full_name" required value="<?= sanitize($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" name="phone" value="<?= sanitize($_POST['phone'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required minlength="8">
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required minlength="8">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
      </form>

      <div class="auth-switch">Already have an account? <a href="<?= BASE_URL ?>/guest/login.php">Sign in</a></div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isGuestLoggedIn()) {
    header('Location: ' . BASE_URL . '/guest/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM guests WHERE email = ?");
    $stmt->execute([$email]);
    $guest = $stmt->fetch();

    if ($guest && password_verify($password, $guest['password_hash'])) {
        $_SESSION['guest_id'] = $guest['id'];
        $_SESSION['guest_name'] = $guest['full_name'];
        $_SESSION['guest_email'] = $guest['email'];
        logActivity('guest', $guest['id'], 'Logged in');
        header('Location: ' . BASE_URL . '/guest/dashboard.php');
        exit;
    } else {
        $errors[] = 'Incorrect email or password.';
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-shell">
  <div class="auth-visual">
    <img src="https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=900&q=80" alt="">
  </div>
  <div class="auth-form-wrap">
    <div class="auth-form">
      <span class="eyebrow">Welcome Back</span>
      <h2 style="margin-top:14px;">Sign in to your account</h2>
      <p class="sub">Access your reservations, loyalty points, and saved rooms.</p>

      <?php if ($errors): ?>
        <div class="form-error"><?= implode('<br>', array_map('sanitize', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" required value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
      </form>

      <div class="auth-switch">New to Aureum? <a href="<?= BASE_URL ?>/guest/register.php">Create an account</a></div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

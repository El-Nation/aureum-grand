<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && $message) {
        // Stored as a generic service request so it surfaces in the admin console.
        $stmt = $db->prepare("INSERT INTO service_requests (request_type, details, status) VALUES ('other', ?, 'new')");
        $stmt->execute(["From: $name <$email>\n\n$message"]);
        $success = true;
    }
}

$pageTitle = 'Contact Us';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section-tight bg-marble">
  <div class="container">
    <span class="eyebrow">Get in Touch</span>
    <h1 style="margin-top:14px;font-size:2.4rem;">Contact Aureum Grand</h1>
    <p class="lead" style="margin-top:18px; max-width: 600px;">We are here to assist you with any inquiries, special requests, or arrangements you may need. Please fill out the form below, and our dedicated team will ensure your needs are met promptly and with the utmost care.</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:800px;">
    <?php if ($success): ?>
      <div class="form-success">Thank you — your message has been received. Our concierge team will respond shortly.</div>
    <?php endif; ?>

    <form method="POST" class="dash-panel" style="padding: 50px;">
      <div class="dash-form-group" style="margin-bottom: 24px;"><label style="font-size: 1.1rem; margin-bottom: 10px; display: block;">Full Name</label><input type="text" name="name" required style="padding: 16px; font-size: 1.05rem; width: 100%;"></div>
      <div class="dash-form-group" style="margin-bottom: 24px;"><label style="font-size: 1.1rem; margin-bottom: 10px; display: block;">Email</label><input type="email" name="email" required style="padding: 16px; font-size: 1.05rem; width: 100%;"></div>
      <div class="dash-form-group" style="margin-bottom: 30px;"><label style="font-size: 1.1rem; margin-bottom: 10px; display: block;">Message</label><textarea name="message" rows="8" required style="padding: 16px; font-size: 1.05rem; width: 100%;"></textarea></div>
      <button type="submit" class="btn btn-primary btn-block" style="padding: 18px; font-size: 1.15rem;">Send Message</button>
    </form>

    <div class="divider"></div>
    <p class="muted">Or reach us directly: <strong>07066784058</strong> · <strong>eghedestiny10@gmail.com</strong></p>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/paystack.php';

$db = getDB();
$ref = $_GET['ref'] ?? '';

$stmt = $db->prepare("SELECT r.*, rc.name AS room_name FROM reservations r
    JOIN room_categories rc ON r.category_id = rc.id WHERE r.booking_reference = ?");
$stmt->execute([$ref]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . BASE_URL . '/public/rooms.php');
    exit;
}

$pageTitle = 'Booking Confirmation';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
  <div class="container" style="max-width:760px;">

    <div class="text-center" style="margin-bottom:40px;">
      <div style="width:64px;height:64px;border-radius:50%;background:var(--marble);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.8rem;">✓</div>
      <span class="eyebrow" style="justify-content:center;">Reservation Received</span>
      <h1 style="margin-top:14px;">Thank you, <?= sanitize(explode(' ', $booking['guest_name'])[0]) ?></h1>
      <p class="lead" style="margin:0 auto;">Your room is held pending payment. Reference: <strong><?= sanitize($booking['booking_reference']) ?></strong></p>
    </div>

    <div class="dash-panel">
      <div class="dash-panel-head"><h3>Stay Details</h3><span class="pill pill-<?= $booking['status'] ?>"><?= str_replace('_',' ',$booking['status']) ?></span></div>
      <table class="data-table">
        <tr><td class="muted">Room</td><td><?= sanitize($booking['room_name']) ?></td></tr>
        <tr><td class="muted">Check-in</td><td><?= date('D, d M Y', strtotime($booking['check_in'])) ?></td></tr>
        <tr><td class="muted">Check-out</td><td><?= date('D, d M Y', strtotime($booking['check_out'])) ?></td></tr>
        <tr><td class="muted">Guests</td><td><?= $booking['adults'] ?> Adult(s), <?= $booking['children'] ?> Child(ren)</td></tr>
        <tr><td class="muted">Nights</td><td><?= $booking['nights'] ?></td></tr>
        <tr><td class="muted">Total Due</td><td><strong>₦<?= number_format($booking['total_amount'], 2) ?></strong></td></tr>
      </table>
    </div>

    <?php if ($booking['payment_status'] === 'unpaid'): ?>
    <div class="dash-panel">
      <div class="dash-panel-head"><h3>Complete Payment</h3></div>
      <p class="muted" style="margin-bottom:18px;font-size:0.9rem;">Pay securely online via Paystack, or choose to pay at the property on arrival.</p>
      <div class="flex gap-12" style="flex-wrap:wrap;">
        <button id="payNowBtn" class="btn btn-primary">Pay Now with Paystack — ₦<?= number_format($booking['total_amount'], 2) ?></button>
        <a href="<?= BASE_URL ?>/public/index.php" class="btn btn-outline">Pay at Property Instead</a>
      </div>
      <p class="muted" style="font-size:0.76rem;margin-top:14px;">
        Note: Paystack keys are placeholders in this build. Add your live keys in <code>config/paystack.php</code> to activate real payments.
      </p>
    </div>
    <?php else: ?>
    <div class="form-success">Payment received — your booking is confirmed.</div>
    <?php endif; ?>

    <div class="text-center" style="margin-top:32px;">
      <a href="<?= BASE_URL ?>/public/index.php" class="muted">&larr; Return to homepage</a>
    </div>
  </div>
</section>

<script src="https://js.paystack.co/v2/inline.js"></script>
<script>
const payBtn = document.getElementById('payNowBtn');
if (payBtn) {
  payBtn.addEventListener('click', async function() {
    payBtn.disabled = true;
    payBtn.textContent = 'Preparing payment…';

    try {
      const res = await fetch(BASE_URL + '/api/initialize-payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_reference: '<?= sanitize($booking['booking_reference']) ?>' })
      });
      const data = await res.json();

      if (data.success && data.authorization_url) {
        window.location.href = data.authorization_url;
      } else {
        alert(data.message || 'Payment setup is not yet configured. Add your Paystack keys in config/paystack.php.');
        payBtn.disabled = false;
        payBtn.textContent = 'Pay Now with Paystack — ₦<?= number_format($booking['total_amount'], 2) ?>';
      }
    } catch (e) {
      alert('Could not start payment. Please try again.');
      payBtn.disabled = false;
      payBtn.textContent = 'Pay Now with Paystack — ₦<?= number_format($booking['total_amount'], 2) ?>';
    }
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

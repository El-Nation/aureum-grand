<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireGuestLogin();

$db = getDB();
$guestId = $_SESSION['guest_id'];

$stmt = $db->prepare("SELECT * FROM guests WHERE id = ?");
$stmt->execute([$guestId]);
$guest = $stmt->fetch();

$stmt = $db->prepare("SELECT r.*, rc.name AS room_name FROM reservations r
    JOIN room_categories rc ON r.category_id = rc.id
    WHERE r.guest_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$guestId]);
$bookings = $stmt->fetchAll();

$stmt = $db->prepare("SELECT f.*, rc.name, rc.base_price, rc.main_image FROM guest_favorites f
    JOIN room_categories rc ON f.room_id = rc.id WHERE f.guest_id = ?");
$stmt->execute([$guestId]);
$favorites = $stmt->fetchAll();

$tierThresholds = ['Silver' => 0, 'Gold' => 500, 'Platinum' => 2000, 'VIP' => 5000];
$nextTier = null; $nextThreshold = null;
foreach ($tierThresholds as $tier => $threshold) {
    if ($threshold > $guest['loyalty_points']) { $nextTier = $tier; $nextThreshold = $threshold; break; }
}

$pageTitle = 'My Account';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section-tight bg-marble">
  <div class="container">
    <span class="eyebrow">My Account</span>
    <h1 style="margin-top:14px;font-size:2.2rem;">Welcome back, <?= sanitize(explode(' ', $guest['full_name'])[0]) ?></h1>
  </div>
</section>

<section class="section" style="padding-top:48px;">
  <div class="container">
    <div class="dash-grid-2">
      <div>
        <div class="dash-panel">
          <div class="dash-panel-head"><h3>Booking History</h3></div>
          <?php if (empty($bookings)): ?>
            <div class="empty-state">
              <p>No reservations yet.</p>
              <a href="<?= BASE_URL ?>/public/rooms.php" class="btn btn-primary btn-sm" style="margin-top:14px;">Browse Rooms</a>
            </div>
          <?php else: ?>
          <div class="table-scroll">
            <table class="data-table">
              <thead><tr><th>Reference</th><th>Room</th><th>Dates</th><th>Total</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($bookings as $b): ?>
                <tr>
                  <td class="ref"><?= sanitize($b['booking_reference']) ?></td>
                  <td><?= sanitize($b['room_name']) ?></td>
                  <td><?= date('d M', strtotime($b['check_in'])) ?> – <?= date('d M Y', strtotime($b['check_out'])) ?></td>
                  <td>₦<?= number_format($b['total_amount']) ?></td>
                  <td><span class="pill pill-<?= $b['status'] ?>"><?= str_replace('_',' ',$b['status']) ?></span></td>
                  <td><a href="<?= BASE_URL ?>/public/booking-confirmation.php?ref=<?= urlencode($b['booking_reference']) ?>" class="btn btn-outline btn-sm">View</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head"><h3>Saved Rooms</h3></div>
          <?php if (empty($favorites)): ?>
            <div class="empty-state"><p>You haven't saved any rooms yet.</p></div>
          <?php else: ?>
            <div class="room-grid" style="grid-template-columns:repeat(2,1fr);">
              <?php foreach ($favorites as $f): ?>
              <div class="room-card">
                <div class="room-card-body">
                  <h3 style="font-size:1.05rem;"><?= sanitize($f['name']) ?></h3>
                  <div class="price-tag" style="margin-top:8px;"><span class="amount" style="font-size:1.1rem;">₦<?= number_format($f['base_price']) ?></span><span class="per"> / night</span></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="dash-panel" style="background:var(--emerald-deep);color:var(--linen);border:none;">
          <div style="font-size:0.78rem;text-transform:uppercase;letter-spacing:0.06em;color:rgba(246,243,236,0.6);">Loyalty Tier</div>
          <div style="font-family:var(--font-display);font-size:1.8rem;color:var(--brass-light);margin:8px 0;"><?= sanitize($guest['loyalty_tier']) ?></div>
          <div style="font-size:0.9rem;color:rgba(246,243,236,0.8);"><?= number_format($guest['loyalty_points']) ?> points</div>
          <?php if ($nextTier): ?>
            <div style="margin-top:16px;">
              <div style="height:6px;background:rgba(246,243,236,0.15);border-radius:3px;overflow:hidden;">
                <div style="height:100%;background:var(--brass);width:<?= min(100, ($guest['loyalty_points'] / max(1,$nextThreshold)) * 100) ?>%;"></div>
              </div>
              <div style="font-size:0.78rem;color:rgba(246,243,236,0.6);margin-top:8px;"><?= $nextThreshold - $guest['loyalty_points'] ?> points to <?= $nextTier ?></div>
            </div>
          <?php endif; ?>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head"><h3>Profile</h3></div>
          <div class="dash-form-group"><label>Name</label><input type="text" value="<?= sanitize($guest['full_name']) ?>" disabled></div>
          <div class="dash-form-group"><label>Email</label><input type="text" value="<?= sanitize($guest['email']) ?>" disabled></div>
          <div class="dash-form-group"><label>Phone</label><input type="text" value="<?= sanitize($guest['phone'] ?: 'Not provided') ?>" disabled></div>
          <a href="<?= BASE_URL ?>/guest/logout.php" class="btn btn-outline btn-block">Sign Out</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

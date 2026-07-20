<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','front_desk','concierge']);

$db = getDB();
$search = $_GET['search'] ?? '';

$sql = "SELECT g.*, COUNT(r.id) AS total_bookings, COALESCE(SUM(CASE WHEN r.status IN ('confirmed','checked_in','checked_out') THEN r.total_amount ELSE 0 END),0) AS lifetime_value
        FROM guests g LEFT JOIN reservations r ON r.guest_id = g.id";
$params = [];
if ($search) { $sql .= " WHERE g.full_name LIKE ? OR g.email LIKE ?"; $params = ["%$search%", "%$search%"]; }
$sql .= " GROUP BY g.id ORDER BY lifetime_value DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$guests = $stmt->fetchAll();

$pageTitle = 'Guests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guests — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar"><div><h1>Guests</h1><div class="sub">All registered guest accounts</div></div></div>

    <div class="dash-panel">
      <form method="GET" style="margin-bottom:20px;">
        <input type="text" name="search" placeholder="Search by name or email…" value="<?= sanitize($search) ?>" style="width:100%;max-width:380px;padding:10px 14px;border:1px solid var(--line);border-radius:6px;">
      </form>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Guest</th><th>Email</th><th>Tier</th><th>Points</th><th>Bookings</th><th>Lifetime Value</th></tr></thead>
          <tbody>
            <?php foreach ($guests as $g): ?>
            <tr>
              <td><?= sanitize($g['full_name']) ?></td>
              <td><?= sanitize($g['email']) ?></td>
              <td><span class="pill pill-confirmed"><?= sanitize($g['loyalty_tier']) ?></span></td>
              <td><?= number_format($g['loyalty_points']) ?></td>
              <td><?= $g['total_bookings'] ?></td>
              <td>₦<?= number_format($g['lifetime_value']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($guests)): ?><tr><td colspan="6" class="empty-state">No guests found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>

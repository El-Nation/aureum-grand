<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','front_desk']);

$db = getDB();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $result = updateReservationStatus((int)$_POST['reservation_id'], $_POST['new_status'], $_SESSION['staff_id']);
    header('Location: ' . BASE_URL . '/admin/reservations.php?msg=' . ($result['success'] ? 'updated' : urlencode($result['message'])));
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT r.*, rc.name AS room_name, rm.room_number FROM reservations r
        JOIN room_categories rc ON r.category_id = rc.id
        LEFT JOIN rooms rm ON r.room_id = rm.id WHERE 1=1";
$params = [];

if ($statusFilter) { $sql .= " AND r.status = ?"; $params[] = $statusFilter; }
if ($search) {
    $sql .= " AND (r.guest_name LIKE ? OR r.booking_reference LIKE ? OR r.guest_email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$sql .= " ORDER BY r.created_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$pageTitle = 'Reservations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservations — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>Reservations</h1><div class="sub">Pending → Confirmed → Checked-In → Checked-Out → Cancelled / No-Show</div></div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
      <div class="<?= $_GET['msg'] === 'updated' ? 'form-success' : 'form-error' ?>">
        <?= $_GET['msg'] === 'updated' ? 'Reservation status updated.' : sanitize($_GET['msg']) ?>
      </div>
    <?php endif; ?>

    <div class="dash-panel">
      <form method="GET" class="flex gap-12" style="margin-bottom:20px;flex-wrap:wrap;">
        <input type="text" name="search" placeholder="Search guest, email, or reference…" value="<?= sanitize($search) ?>" style="flex:1;min-width:240px;padding:10px 14px;border:1px solid var(--line);border-radius:6px;">
        <select name="status" style="padding:10px 14px;border:1px solid var(--line);border-radius:6px;" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <?php foreach (['pending','confirmed','checked_in','checked_out','cancelled','no_show'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= str_replace('_',' ',ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-dark btn-sm">Search</button>
      </form>

      <div class="table-scroll">
        <table class="data-table">
          <thead>
            <tr><th>Reference</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Total</th><th>Payment</th><th>Status</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($reservations as $r): ?>
            <tr>
              <td class="ref"><?= sanitize($r['booking_reference']) ?></td>
              <td><?= sanitize($r['guest_name']) ?><br><span class="muted" style="font-size:0.78rem;"><?= sanitize($r['guest_email']) ?></span></td>
              <td><?= sanitize($r['room_name']) ?><?= $r['room_number'] ? ' · #' . sanitize($r['room_number']) : '' ?></td>
              <td><?= date('d M Y', strtotime($r['check_in'])) ?></td>
              <td><?= date('d M Y', strtotime($r['check_out'])) ?></td>
              <td>₦<?= number_format($r['total_amount']) ?></td>
              <td><span class="pill pill-<?= $r['payment_status'] === 'paid' ? 'confirmed' : 'pending' ?>"><?= ucfirst($r['payment_status']) ?></span></td>
              <td><span class="pill pill-<?= $r['status'] ?>"><?= str_replace('_',' ',$r['status']) ?></span></td>
              <td>
                <?php $next = VALID_STATUS_TRANSITIONS[$r['status']] ?? []; ?>
                <?php if (!empty($next)): ?>
                <form method="POST" style="display:flex;gap:4px;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                  <select name="new_status" class="status-select" onchange="this.form.submit()">
                    <option value="">Move to…</option>
                    <?php foreach ($next as $n): ?>
                      <option value="<?= $n ?>"><?= str_replace('_',' ',ucfirst($n)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
                <?php else: ?>
                  <span class="muted" style="font-size:0.78rem;">Final state</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($reservations)): ?>
              <tr><td colspan="9" class="empty-state">No reservations match this filter.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


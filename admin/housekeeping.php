<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','housekeeping','front_desk']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_room_status') {
    $stmt = $db->prepare("UPDATE rooms SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], (int)$_POST['room_id']]);
    logActivity('staff', $_SESSION['staff_id'], 'Updated room status', 'room', $_POST['room_id'], $_POST['status']);
    header('Location: ' . BASE_URL . '/admin/housekeeping.php');
    exit;
}

$stmt = $db->query("SELECT rm.*, rc.name AS category_name FROM rooms rm
    JOIN room_categories rc ON rm.category_id = rc.id ORDER BY rm.floor, rm.room_number");
$rooms = $stmt->fetchAll();

$byStatus = ['clean' => [], 'dirty' => [], 'in_progress' => [], 'inspected' => [], 'out_of_order' => []];
foreach ($rooms as $r) { $byStatus[$r['status']][] = $r; }

$statusLabels = ['clean' => 'Clean', 'dirty' => 'Dirty', 'in_progress' => 'In Progress', 'inspected' => 'Inspected', 'out_of_order' => 'Out of Order'];

$pageTitle = 'Housekeeping';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Housekeeping — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>Housekeeping</h1><div class="sub">Drag-free status board — use the dropdown on each card to move a room</div></div>
    </div>

    <div class="hk-board">
      <?php foreach ($statusLabels as $key => $label): ?>
      <div class="hk-col">
        <div class="hk-col-head"><span><?= $label ?></span><span><?= count($byStatus[$key]) ?></span></div>
        <?php foreach ($byStatus[$key] as $room): ?>
        <div class="hk-card">
          <div class="flex" style="justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span class="room-num">#<?= sanitize($room['room_number']) ?></span>
            <span class="muted" style="font-size:0.72rem;">Fl. <?= $room['floor'] ?></span>
          </div>
          <div class="muted" style="font-size:0.78rem;margin-bottom:10px;"><?= sanitize($room['category_name']) ?></div>
          <form method="POST">
            <input type="hidden" name="action" value="update_room_status">
            <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
            <select name="status" class="status-select" style="width:100%;" onchange="this.form.submit()">
              <?php foreach ($statusLabels as $k => $l): ?>
                <option value="<?= $k ?>" <?= $k === $key ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
        <?php endforeach; ?>
        <?php if (empty($byStatus[$key])): ?>
          <div class="muted" style="font-size:0.78rem;text-align:center;padding:20px 0;">No rooms</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>

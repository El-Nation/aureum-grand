<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','maintenance']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'create_ticket') {
        $stmt = $db->prepare("INSERT INTO maintenance_tickets (room_id, property_id, issue_type, description, priority, reported_by) VALUES (?,1,?,?,?,?)");
        $stmt->execute([
            $_POST['room_id'] ?: null, $_POST['issue_type'], sanitize($_POST['description']),
            $_POST['priority'], $_SESSION['staff_id']
        ]);
        header('Location: ' . BASE_URL . '/admin/maintenance.php?msg=created');
        exit;
    }
    if (($_POST['action'] ?? '') === 'update_ticket') {
        $resolvedAt = $_POST['status'] === 'resolved' ? 'NOW()' : 'NULL';
        $stmt = $db->prepare("UPDATE maintenance_tickets SET status=?, assigned_to=?, cost=?, resolved_at=" . ($_POST['status'] === 'resolved' ? 'NOW()' : 'NULL') . " WHERE id=?");
        $stmt->execute([$_POST['status'], $_POST['assigned_to'] ?: null, $_POST['cost'] ?: 0, $_POST['ticket_id']]);
        header('Location: ' . BASE_URL . '/admin/maintenance.php?msg=updated');
        exit;
    }
}

$stmt = $db->query("SELECT mt.*, rm.room_number, s.full_name AS assigned_name FROM maintenance_tickets mt
    LEFT JOIN rooms rm ON mt.room_id = rm.id
    LEFT JOIN staff s ON mt.assigned_to = s.id
    ORDER BY mt.created_at DESC");
$tickets = $stmt->fetchAll();

$stmt = $db->query("SELECT id, room_number FROM rooms ORDER BY room_number");
$allRooms = $stmt->fetchAll();

$stmt = $db->query("SELECT id, full_name FROM staff WHERE role_id = (SELECT id FROM roles WHERE name='maintenance')");
$techs = $stmt->fetchAll();

$totalCost = array_sum(array_column($tickets, 'cost'));

$pageTitle = 'Maintenance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>Maintenance</h1><div class="sub">Total recorded cost: ₦<?= number_format($totalCost) ?></div></div>
      <button class="btn btn-dark btn-sm" onclick="document.getElementById('newTicket').showModal()">+ Report Issue</button>
    </div>

    <?php if (isset($_GET['msg'])): ?><div class="form-success">Ticket <?= $_GET['msg'] === 'created' ? 'created' : 'updated' ?>.</div><?php endif; ?>

    <dialog id="newTicket" style="border:none;border-radius:12px;padding:0;max-width:460px;width:90%;">
      <form method="POST" style="padding:28px;">
        <input type="hidden" name="action" value="create_ticket">
        <h3 style="font-family:var(--font-body);margin-bottom:18px;">Report a Maintenance Issue</h3>
        <div class="dash-form-group"><label>Room (optional)</label>
          <select name="room_id"><option value="">— Property-wide —</option>
            <?php foreach ($allRooms as $rm): ?><option value="<?= $rm['id'] ?>">#<?= sanitize($rm['room_number']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="dash-form-row">
          <div class="dash-form-group"><label>Issue Type</label>
            <select name="issue_type"><option value="hvac">HVAC / AC</option><option value="plumbing">Plumbing</option><option value="electrical">Electrical</option><option value="furniture">Furniture</option><option value="other">Other</option></select>
          </div>
          <div class="dash-form-group"><label>Priority</label>
            <select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select>
          </div>
        </div>
        <div class="dash-form-group"><label>Description</label><textarea name="description" rows="3" required></textarea></div>
        <div class="flex gap-12" style="margin-top:18px;">
          <button type="submit" class="btn btn-primary">Create Ticket</button>
          <button type="button" class="btn btn-outline" onclick="document.getElementById('newTicket').close()">Cancel</button>
        </div>
      </form>
    </dialog>

    <div class="dash-panel">
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Room</th><th>Issue</th><th>Description</th><th>Priority</th><th>Assigned To</th><th>Cost</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td><?= $t['room_number'] ? '#' . sanitize($t['room_number']) : '<span class="muted">Property-wide</span>' ?></td>
              <td><?= ucfirst($t['issue_type']) ?></td>
              <td style="max-width:240px;"><?= sanitize($t['description']) ?></td>
              <td><span class="priority-dot priority-<?= $t['priority'] ?>"></span><?= ucfirst($t['priority']) ?></td>
              <td><?= $t['assigned_name'] ? sanitize($t['assigned_name']) : '<span class="muted">Unassigned</span>' ?></td>
              <td>₦<?= number_format($t['cost']) ?></td>
              <td>
                <form method="POST" class="flex gap-12" style="flex-wrap:wrap;">
                  <input type="hidden" name="action" value="update_ticket">
                  <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                  <select name="status" class="status-select" onchange="this.form.submit()">
                    <?php foreach (['open','assigned','in_progress','resolved','closed'] as $s): ?>
                      <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= str_replace('_',' ',ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?><tr><td colspan="7" class="empty-state">No maintenance tickets logged.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


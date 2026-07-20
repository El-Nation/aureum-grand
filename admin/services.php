<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','concierge','front_desk']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_request') {
    $stmt = $db->prepare("UPDATE service_requests SET status=?, assigned_to=? WHERE id=?");
    $stmt->execute([$_POST['status'], $_POST['assigned_to'] ?: null, $_POST['request_id']]);
    header('Location: ' . BASE_URL . '/admin/services.php?msg=updated');
    exit;
}

$stmt = $db->query("SELECT sr.*, r.booking_reference, r.guest_name AS res_guest_name, s.full_name AS assigned_name
    FROM service_requests sr
    LEFT JOIN reservations r ON sr.reservation_id = r.id
    LEFT JOIN staff s ON sr.assigned_to = s.id
    ORDER BY sr.created_at DESC");
$requests = $stmt->fetchAll();

$typeLabels = [
    'airport_transfer' => 'Airport Transfer', 'chauffeur' => 'Chauffeur', 'spa' => 'Spa Appointment',
    'restaurant' => 'Restaurant', 'tour' => 'Tour Booking', 'event_ticket' => 'Event Ticket',
    'room_service' => 'Room Service', 'other' => 'Other',
];

$pageTitle = 'Guest Services';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Guest Services — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>Guest Services</h1><div class="sub">Concierge requests — transfers, spa, dining, room service and more</div></div>
    </div>

    <div class="stub-banner">
      <span>🤖</span>
      <span>The AI Concierge Assistant (auto-recommendations, natural-language request handling) is not wired up in this build — it needs an LLM API key. This page handles requests manually; the hook point for an AI integration is noted in the code comments below.</span>
    </div>

    <?php if (empty($requests)): ?>
      <div class="dash-panel"><div class="empty-state">No guest service requests yet. These will appear here when guests submit requests from their dashboard or the public contact form.</div></div>
    <?php else: ?>
    <div class="dash-panel">
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Type</th><th>Guest / Booking</th><th>Details</th><th>Requested For</th><th>Assigned</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
            <tr>
              <td><?= $typeLabels[$r['request_type']] ?? ucfirst($r['request_type']) ?></td>
              <td><?= sanitize($r['res_guest_name'] ?? 'Guest #' . $r['guest_id']) ?><?= $r['booking_reference'] ? '<br><span class="ref" style="font-size:0.76rem;">' . sanitize($r['booking_reference']) . '</span>' : '' ?></td>
              <td style="max-width:260px;"><?= sanitize($r['details']) ?></td>
              <td><?= $r['requested_for'] ? date('d M, H:i', strtotime($r['requested_for'])) : '—' ?></td>
              <td><?= $r['assigned_name'] ? sanitize($r['assigned_name']) : '<span class="muted">Unassigned</span>' ?></td>
              <td>
                <form method="POST">
                  <input type="hidden" name="action" value="update_request">
                  <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                  <select name="status" class="status-select" onchange="this.form.submit()">
                    <?php foreach (['new','acknowledged','in_progress','completed','cancelled'] as $s): ?>
                      <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= str_replace('_',' ',ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


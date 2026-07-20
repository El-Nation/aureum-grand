<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager']);

$db = getDB();
$stmt = $db->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 200");
$logs = $stmt->fetchAll();

$pageTitle = 'Activity Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar"><div><h1>Activity Log</h1><div class="sub">Audit trail of every critical action taken on the platform</div></div></div>

    <div class="dash-panel">
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td class="muted" style="font-size:0.8rem;"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></td>
              <td><span class="pill pill-pending"><?= ucfirst($log['actor_type']) ?> #<?= $log['actor_id'] ?? '—' ?></span></td>
              <td><?= sanitize($log['action']) ?></td>
              <td><?= $log['entity_type'] ? sanitize($log['entity_type']) . ' #' . $log['entity_id'] : '—' ?></td>
              <td class="muted" style="font-size:0.78rem;"><?= sanitize($log['ip_address']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?><tr><td colspan="5" class="empty-state">No activity recorded yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin();

$db = getDB();
$propertyId = 1;
$startOfMonth = date('Y-m-01');
$today = date('Y-m-d');

$occupancy = getOccupancyRate($propertyId, $startOfMonth, date('Y-m-t'));
$adr = getADR($propertyId, $startOfMonth, date('Y-m-t'));
$revpar = getRevPAR($propertyId, $startOfMonth, date('Y-m-t'));

$stmt = $db->query("SELECT COUNT(*) c FROM reservations WHERE status = 'pending'");
$pendingCount = $stmt->fetch()['c'];

$stmt = $db->query("SELECT r.*, rc.name AS room_name FROM reservations r
    JOIN room_categories rc ON r.category_id = rc.id
    ORDER BY r.created_at DESC LIMIT 8");
$recentBookings = $stmt->fetchAll();

$stmt = $db->query("SELECT room.room_number, room.status, rc.name FROM rooms room
    JOIN room_categories rc ON room.category_id = rc.id ORDER BY room.floor, room.room_number");
$roomStatuses = $stmt->fetchAll();

$stmt = $db->query("SELECT COUNT(*) c FROM maintenance_tickets WHERE status IN ('open','assigned','in_progress')");
$openMaintenance = $stmt->fetch()['c'];

// Last 7 days booking volume for the chart
$stmt = $db->query("SELECT DATE(created_at) d, COUNT(*) c FROM reservations WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)");
$bookingsByDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$role = $_SESSION['staff_role'];
$pageTitle = 'Overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Overview — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>

<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

  <main class="dash-main">
    <div class="dash-topbar">
      <div>
        <h1>Good day, <?= sanitize(explode(' ', $_SESSION['staff_name'])[0]) ?></h1>
        <div class="sub"><?= date('l, d F Y') ?> · Aureum Grand Hotel</div>
      </div>
      <a href="<?= BASE_URL ?>/admin/reservations.php" class="btn btn-dark btn-sm">+ New Reservation</a>
    </div>

    <div class="kpi-row">
      <div class="kpi-card">
        <div class="kpi-label">Occupancy (MTD)</div>
        <div class="kpi-value"><?= $occupancy ?>%</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">ADR (Avg. Daily Rate)</div>
        <div class="kpi-value">₦<?= number_format($adr) ?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">RevPAR</div>
        <div class="kpi-value">₦<?= number_format($revpar) ?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Pending Reservations</div>
        <div class="kpi-value"><?= $pendingCount ?></div>
        <?php if ($openMaintenance > 0): ?><div class="kpi-delta down">⚠ <?= $openMaintenance ?> open maintenance tickets</div><?php endif; ?>
      </div>
    </div>

    <div class="dash-grid-2">
      <div class="dash-panel">
        <div class="dash-panel-head">
          <h3>Recent Reservations</h3>
          <a href="<?= BASE_URL ?>/admin/reservations.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-scroll">
          <table class="data-table">
            <thead><tr><th>Reference</th><th>Guest</th><th>Room</th><th>Dates</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentBookings as $b): ?>
              <tr>
                <td class="ref"><?= sanitize($b['booking_reference']) ?></td>
                <td><?= sanitize($b['guest_name']) ?></td>
                <td><?= sanitize($b['room_name']) ?></td>
                <td><?= date('d M', strtotime($b['check_in'])) ?> – <?= date('d M', strtotime($b['check_out'])) ?></td>
                <td><span class="pill pill-<?= $b['status'] ?>"><?= str_replace('_',' ',$b['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recentBookings)): ?>
                <tr><td colspan="5" class="empty-state">No reservations yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="dash-panel">
        <div class="dash-panel-head"><h3>Room Status</h3></div>
        <?php
        $statusCounts = array_count_values(array_column($roomStatuses, 'status'));
        ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach (['clean' => 'Clean', 'dirty' => 'Dirty', 'in_progress' => 'In Progress', 'inspected' => 'Inspected', 'out_of_order' => 'Out of Order'] as $key => $label): ?>
          <div class="flex" style="justify-content:space-between;align-items:center;">
            <span class="pill pill-<?= $key ?>"><?= $label ?></span>
            <strong><?= $statusCounts[$key] ?? 0 ?></strong>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>/admin/housekeeping.php" class="btn btn-outline btn-block" style="margin-top:18px;">Open Housekeeping Board</a>
      </div>
    </div>

    <div class="dash-panel">
      <div class="dash-panel-head"><h3>Bookings — Last 7 Days</h3></div>
      <div class="chart-box">
        <canvas id="bookingsChart"></canvas>
      </div>
    </div>

  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const labels = [];
const data = [];
<?php for ($i = 6; $i >= 0; $i--): $d = date('Y-m-d', strtotime("-$i day")); ?>
  labels.push('<?= date('D', strtotime($d)) ?>');
  data.push(<?= $bookingsByDay[$d] ?? 0 ?>);
<?php endfor; ?>

new Chart(document.getElementById('bookingsChart'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'New Bookings',
      data: data,
      backgroundColor: '#b08a4e',
      borderRadius: 4,
      maxBarThickness: 36
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});
</script>

<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


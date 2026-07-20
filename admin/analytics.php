<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','revenue_manager']);

$db = getDB();
$propertyId = 1;

// Revenue by room category (all time, confirmed+)
$stmt = $db->query("SELECT rc.name, SUM(r.total_amount) AS revenue, COUNT(r.id) AS bookings FROM reservations r
    JOIN room_categories rc ON r.category_id = rc.id
    WHERE r.status IN ('confirmed','checked_in','checked_out')
    GROUP BY rc.id ORDER BY revenue DESC");
$revenueByCategory = $stmt->fetchAll();

// Bookings by source
$stmt = $db->query("SELECT source, COUNT(*) c FROM reservations GROUP BY source");
$bySource = $stmt->fetchAll();

// Cancellation & no-show rates
$stmt = $db->query("SELECT status, COUNT(*) c FROM reservations GROUP BY status");
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalReservations = array_sum($statusCounts);
$cancelRate = $totalReservations ? round((($statusCounts['cancelled'] ?? 0) / $totalReservations) * 100, 1) : 0;
$noShowRate = $totalReservations ? round((($statusCounts['no_show'] ?? 0) / $totalReservations) * 100, 1) : 0;

// Repeat guest rate
$stmt = $db->query("SELECT COUNT(*) c FROM (SELECT guest_id FROM reservations WHERE guest_id IS NOT NULL GROUP BY guest_id HAVING COUNT(*) > 1) t");
$repeatGuests = $stmt->fetch()['c'];
$stmt = $db->query("SELECT COUNT(DISTINCT guest_id) c FROM reservations WHERE guest_id IS NOT NULL");
$totalGuests = $stmt->fetch()['c'] ?: 1;
$repeatRate = round(($repeatGuests / $totalGuests) * 100, 1);

// 30-day demand trend
$stmt = $db->query("SELECT check_in AS d, COUNT(*) c FROM reservations WHERE check_in BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY GROUP BY check_in");
$demandTrend = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar"><div><h1>Analytics</h1><div class="sub">Performance trends across the property</div></div></div>

    <div class="kpi-row">
      <div class="kpi-card"><div class="kpi-label">Cancellation Rate</div><div class="kpi-value"><?= $cancelRate ?>%</div></div>
      <div class="kpi-card"><div class="kpi-label">No-Show Rate</div><div class="kpi-value"><?= $noShowRate ?>%</div></div>
      <div class="kpi-card"><div class="kpi-label">Repeat Guest Rate</div><div class="kpi-value"><?= $repeatRate ?>%</div></div>
      <div class="kpi-card"><div class="kpi-label">Total Reservations</div><div class="kpi-value"><?= $totalReservations ?></div></div>
    </div>

    <div class="dash-grid-2">
      <div class="dash-panel">
        <div class="dash-panel-head"><h3>Revenue by Room Category</h3></div>
        <div class="chart-box"><canvas id="revenueChart"></canvas></div>
      </div>
      <div class="dash-panel">
        <div class="dash-panel-head"><h3>Booking Sources</h3></div>
        <div class="chart-box"><canvas id="sourceChart"></canvas></div>
      </div>
    </div>

    <div class="dash-panel">
      <div class="dash-panel-head"><h3>30-Day Demand Forecast</h3><span class="muted" style="font-size:0.82rem;">Based on confirmed check-in dates</span></div>
      <div class="chart-box"><canvas id="demandChart"></canvas></div>
    </div>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($revenueByCategory, 'name')) ?>,
    datasets: [{ data: <?= json_encode(array_map('floatval', array_column($revenueByCategory, 'revenue'))) ?>, backgroundColor: '#14322a', borderRadius: 4 }]
  },
  options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('sourceChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_map(fn($s) => str_replace('_',' ',ucfirst($s['source'])), $bySource)) ?>,
    datasets: [{ data: <?= json_encode(array_column($bySource, 'c')) ?>, backgroundColor: ['#b08a4e','#14322a','#8a6e52','#2c5a8a','#c97b3d'] }]
  },
  options: { responsive: true, maintainAspectRatio: false }
});

const demandLabels = [], demandData = [];
<?php for ($i = 0; $i <= 30; $i++): $d = date('Y-m-d', strtotime("+$i day")); ?>
  demandLabels.push('<?= date('d M', strtotime($d)) ?>');
  demandData.push(<?= $demandTrend[$d] ?? 0 ?>);
<?php endfor; ?>
new Chart(document.getElementById('demandChart'), {
  type: 'line',
  data: { labels: demandLabels, datasets: [{ data: demandData, borderColor: '#b08a4e', backgroundColor: 'rgba(176,138,78,0.12)', fill: true, tension: 0.35 }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
</script>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>

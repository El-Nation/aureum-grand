<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','revenue_manager']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_rule') {
    $stmt = $db->prepare("INSERT INTO pricing_rules (category_id, rule_type, name, start_date, end_date, adjustment_type, adjustment_value, promo_code, min_stay_nights) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['category_id'], $_POST['rule_type'], sanitize($_POST['name']),
        $_POST['start_date'] ?: null, $_POST['end_date'] ?: null,
        $_POST['adjustment_type'], $_POST['adjustment_value'],
        $_POST['promo_code'] ? strtoupper(sanitize($_POST['promo_code'])) : null,
        $_POST['min_stay_nights'] ?: 1
    ]);
    header('Location: ' . BASE_URL . '/admin/revenue.php?msg=created');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_rule') {
    $stmt = $db->prepare("UPDATE pricing_rules SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_POST['rule_id']]);
    header('Location: ' . BASE_URL . '/admin/revenue.php');
    exit;
}

$stmt = $db->query("SELECT pr.*, rc.name AS category_name FROM pricing_rules pr JOIN room_categories rc ON pr.category_id = rc.id ORDER BY pr.id DESC");
$rules = $stmt->fetchAll();

$stmt = $db->query("SELECT id, name FROM room_categories WHERE is_active = 1");
$categories = $stmt->fetchAll();

$pageTitle = 'Revenue & Pricing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Revenue & Pricing — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>Revenue &amp; Pricing</h1><div class="sub">Seasonal, promotional and corporate pricing rules</div></div>
      <button class="btn btn-dark btn-sm" onclick="document.getElementById('newRule').showModal()">+ New Pricing Rule</button>
    </div>

    <?php if (isset($_GET['msg'])): ?><div class="form-success">Pricing rule created.</div><?php endif; ?>

    <dialog id="newRule" style="border:none;border-radius:12px;padding:0;max-width:480px;width:90%;">
      <form method="POST" style="padding:28px;">
        <input type="hidden" name="action" value="create_rule">
        <h3 style="font-family:var(--font-body);margin-bottom:18px;">New Pricing Rule</h3>
        <div class="dash-form-group"><label>Rule Name</label><input type="text" name="name" required placeholder="e.g. December Holiday Rate"></div>
        <div class="dash-form-row">
          <div class="dash-form-group"><label>Room Category</label>
            <select name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="dash-form-group"><label>Rule Type</label>
            <select name="rule_type">
              <?php foreach (['seasonal','weekend','holiday','promo','corporate','group','early_bird','last_minute'] as $t): ?>
                <option value="<?= $t ?>"><?= str_replace('_',' ',ucfirst($t)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="dash-form-row">
          <div class="dash-form-group"><label>Start Date</label><input type="date" name="start_date"></div>
          <div class="dash-form-group"><label>End Date</label><input type="date" name="end_date"></div>
        </div>
        <div class="dash-form-row">
          <div class="dash-form-group"><label>Adjustment Type</label>
            <select name="adjustment_type">
              <option value="percent_discount">% Discount</option>
              <option value="percent_increase">% Increase</option>
              <option value="fixed_discount">Fixed ₦ Discount</option>
              <option value="fixed_price">Fixed ₦ Price</option>
            </select>
          </div>
          <div class="dash-form-group"><label>Value</label><input type="number" step="0.01" name="adjustment_value" required></div>
        </div>
        <div class="dash-form-row">
          <div class="dash-form-group"><label>Promo Code (optional)</label><input type="text" name="promo_code" placeholder="e.g. SUMMER10"></div>
          <div class="dash-form-group"><label>Min Stay (nights)</label><input type="number" name="min_stay_nights" value="1" min="1"></div>
        </div>
        <div class="flex gap-12" style="margin-top:18px;">
          <button type="submit" class="btn btn-primary">Create Rule</button>
          <button type="button" class="btn btn-outline" onclick="document.getElementById('newRule').close()">Cancel</button>
        </div>
      </form>
    </dialog>

    <div class="dash-panel">
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Rule</th><th>Category</th><th>Type</th><th>Window</th><th>Promo</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($rules as $r): ?>
            <tr>
              <td><?= sanitize($r['name']) ?></td>
              <td><?= sanitize($r['category_name']) ?></td>
              <td><?= str_replace('_',' ',ucfirst($r['rule_type'])) ?></td>
              <td><?= $r['start_date'] ? date('d M Y', strtotime($r['start_date'])) . ' – ' . date('d M Y', strtotime($r['end_date'])) : 'Always active' ?></td>
              <td><?= $r['promo_code'] ? '<code>' . sanitize($r['promo_code']) . '</code>' : '—' ?></td>
              <td><span class="pill pill-<?= $r['is_active'] ? 'confirmed' : 'cancelled' ?>"><?= $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
              <td>
                <form method="POST"><input type="hidden" name="action" value="toggle_rule"><input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm"><?= $r['is_active'] ? 'Disable' : 'Enable' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rules)): ?><tr><td colspan="7" class="empty-state">No pricing rules yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


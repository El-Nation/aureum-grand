<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_staff') {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO staff (property_id, role_id, full_name, email, password_hash) VALUES (1,?,?,?,?)");
    try {
        $stmt->execute([$_POST['role_id'], sanitize($_POST['full_name']), trim($_POST['email']), $hash]);
        header('Location: ' . BASE_URL . '/admin/staff.php?msg=created');
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/admin/staff.php?msg=' . urlencode('Email already exists.'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_staff') {
    $stmt = $db->prepare("UPDATE staff SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_POST['staff_id']]);
    header('Location: ' . BASE_URL . '/admin/staff.php');
    exit;
}

$stmt = $db->query("SELECT s.*, r.name AS role_name FROM staff s JOIN roles r ON s.role_id = r.id ORDER BY s.created_at DESC");
$staffList = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM roles ORDER BY name");
$roles = $stmt->fetchAll();

$pageTitle = 'Staff & Roles';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff & Roles — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>Staff &amp; Roles</h1><div class="sub">Administrator-only — manage staff accounts and role assignments</div></div>
      <button class="btn btn-dark btn-sm" onclick="document.getElementById('newStaff').showModal()">+ Add Staff Member</button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
      <div class="<?= $_GET['msg'] === 'created' ? 'form-success' : 'form-error' ?>">
        <?= $_GET['msg'] === 'created' ? 'Staff account created.' : sanitize($_GET['msg']) ?>
      </div>
    <?php endif; ?>

    <dialog id="newStaff" style="border:none;border-radius:12px;padding:0;max-width:420px;width:90%;">
      <form method="POST" style="padding:28px;">
        <input type="hidden" name="action" value="create_staff">
        <h3 style="font-family:var(--font-body);margin-bottom:18px;">Add Staff Member</h3>
        <div class="dash-form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
        <div class="dash-form-group"><label>Email</label><input type="email" name="email" required></div>
        <div class="dash-form-group"><label>Temporary Password</label><input type="text" name="password" required minlength="8"></div>
        <div class="dash-form-group"><label>Role</label>
          <select name="role_id" required>
            <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= str_replace('_',' ',ucfirst($r['name'])) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="flex gap-12" style="margin-top:18px;">
          <button type="submit" class="btn btn-primary">Create Account</button>
          <button type="button" class="btn btn-outline" onclick="document.getElementById('newStaff').close()">Cancel</button>
        </div>
      </form>
    </dialog>

    <div class="dash-panel">
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>2FA</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($staffList as $s): ?>
            <tr>
              <td><?= sanitize($s['full_name']) ?></td>
              <td><?= sanitize($s['email']) ?></td>
              <td><?= str_replace('_',' ',ucfirst($s['role_name'])) ?></td>
              <td><span class="pill pill-<?= $s['two_factor_enabled'] ? 'confirmed' : 'pending' ?>"><?= $s['two_factor_enabled'] ? 'Enabled' : 'Not Set Up' ?></span></td>
              <td><span class="pill pill-<?= $s['is_active'] ? 'confirmed' : 'cancelled' ?>"><?= $s['is_active'] ? 'Active' : 'Disabled' ?></span></td>
              <td>
                <form method="POST"><input type="hidden" name="action" value="toggle_staff"><input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm"><?= $s['is_active'] ? 'Disable' : 'Enable' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


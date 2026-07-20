<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','revenue_manager']);

$db = getDB();
$uploadDir = __DIR__ . '/../public/uploads/rooms/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// ── CREATE new room category ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_room') {
    $amenitiesRaw = trim($_POST['amenities'] ?? '');
    $amenitiesArr = array_filter(array_map('trim', explode(',', $amenitiesRaw)));
    $amenitiesJson = json_encode(array_values($amenitiesArr));

    $stmt = $db->prepare("INSERT INTO room_categories
        (property_id, name, description, base_price, max_occupancy, bed_configuration, size_sqm, view_type, is_accessible, amenities, is_active)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['description']),
        (float)$_POST['base_price'],
        (int)$_POST['max_occupancy'],
        trim($_POST['bed_configuration']),
        (int)$_POST['size_sqm'],
        trim($_POST['view_type']),
        isset($_POST['is_accessible']) ? 1 : 0,
        $amenitiesJson,
    ]);
    $newId = $db->lastInsertId();

    // Also create physical room records (room_number based on count)
    $roomCount = max(1, (int)($_POST['room_count'] ?? 1));
    $floor     = (int)($_POST['floor'] ?? 1);
    for ($i = 1; $i <= $roomCount; $i++) {
        $roomNum = ($floor * 100) + $i;
        try {
            $db->prepare("INSERT INTO rooms (category_id, room_number, floor, status) VALUES (?,?,?,'clean')")
               ->execute([$newId, (string)$roomNum, $floor]);
        } catch (Exception $e) { /* skip duplicate room numbers */ }
    }

    // Handle main image upload
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $ext      = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
        $fileName = 'room_' . $newId . '_main_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['main_image']['tmp_name'], $uploadDir . $fileName)) {
            $db->prepare("UPDATE room_categories SET main_image=? WHERE id=?")
               ->execute([BASE_URL . '/public/uploads/rooms/' . $fileName, $newId]);
        }
    }
    // Handle bathroom image upload
    if (isset($_FILES['bathroom_image']) && $_FILES['bathroom_image']['error'] === UPLOAD_ERR_OK) {
        $ext      = strtolower(pathinfo($_FILES['bathroom_image']['name'], PATHINFO_EXTENSION));
        $fileName = 'room_' . $newId . '_bath_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['bathroom_image']['tmp_name'], $uploadDir . $fileName)) {
            $db->prepare("UPDATE room_categories SET bathroom_image=? WHERE id=?")
               ->execute([BASE_URL . '/public/uploads/rooms/' . $fileName, $newId]);
        }
    }
    // Handle toilet image upload
    if (isset($_FILES['toilet_image']) && $_FILES['toilet_image']['error'] === UPLOAD_ERR_OK) {
        $ext      = strtolower(pathinfo($_FILES['toilet_image']['name'], PATHINFO_EXTENSION));
        $fileName = 'room_' . $newId . '_toilet_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['toilet_image']['tmp_name'], $uploadDir . $fileName)) {
            $db->prepare("UPDATE room_categories SET toilet_image=? WHERE id=?")
               ->execute([BASE_URL . '/public/uploads/rooms/' . $fileName, $newId]);
        }
    }

    logActivity('staff', $_SESSION['staff_id'], 'Created room category', 'room_category', $newId);
    header('Location: ' . BASE_URL . '/admin/rooms.php?msg=created');
    exit;
}

// ── UPDATE existing room category ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_room') {
    $roomId = (int)$_POST['id'];
    $stmt = $db->prepare("UPDATE room_categories SET name=?, description=?, base_price=?, max_occupancy=?, size_sqm=?, bed_configuration=?, view_type=?, is_accessible=?, is_active=? WHERE id=?");
    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['description']),
        (float)$_POST['base_price'],
        (int)$_POST['max_occupancy'],
        (int)$_POST['size_sqm'],
        trim($_POST['bed_configuration']),
        trim($_POST['view_type']),
        isset($_POST['is_accessible']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0,
        $roomId
    ]);

    // Handle image uploads
    foreach (['main_image','bathroom_image','toilet_image'] as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $ext      = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $prefix   = ['main_image'=>'main','bathroom_image'=>'bath','toilet_image'=>'toilet'][$field];
            $fileName = 'room_' . $roomId . '_' . $prefix . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $fileName)) {
                $db->prepare("UPDATE room_categories SET $field=? WHERE id=?")
                   ->execute([BASE_URL . '/public/uploads/rooms/' . $fileName, $roomId]);
            }
        }
    }

    logActivity('staff', $_SESSION['staff_id'], 'Updated room category', 'room_category', $roomId);
    header('Location: ' . BASE_URL . '/admin/rooms.php?msg=updated');
    exit;
}

$stmt = $db->query("SELECT rc.*, COUNT(rm.id) AS room_count FROM room_categories rc
    LEFT JOIN rooms rm ON rm.category_id = rc.id GROUP BY rc.id ORDER BY rc.base_price DESC");
$categories = $stmt->fetchAll();

$stmt = $db->query("SELECT pr.*, rc.name AS category_name FROM pricing_rules pr
    JOIN room_categories rc ON pr.category_id = rc.id ORDER BY pr.is_active DESC, pr.id DESC");
$pricingRules = $stmt->fetchAll();

$pageTitle = 'Rooms & Rates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rooms &amp; Rates — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
<style>
dialog { border:none; border-radius:14px; padding:0; width:90%; max-width:560px; box-shadow:0 24px 80px rgba(0,0,0,0.18); }
dialog::backdrop { background:rgba(10,18,14,0.55); }
.dlg-body { padding:32px; max-height:80vh; overflow-y:auto; }
.dlg-body h3 { font-family:var(--font-display); margin-bottom:22px; font-size:1.3rem; }
.dlg-body label { display:block; font-size:0.82rem; font-weight:600; color:var(--clay); margin-bottom:5px; }
.dlg-body input[type=text],
.dlg-body input[type=number],
.dlg-body textarea,
.dlg-body select { width:100%; padding:10px 14px; border:1px solid var(--line); border-radius:var(--radius-sm); font-size:0.95rem; background:#fafafa; margin-bottom:14px; box-sizing:border-box; }
.dlg-body textarea { resize:vertical; min-height:80px; }
.dlg-body .row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.dlg-body .cb-row { display:flex; align-items:center; gap:8px; margin-bottom:14px; }
.dlg-footer { display:flex; gap:10px; padding:16px 32px; border-top:1px solid var(--line); background:#f9f8f6; border-radius:0 0 14px 14px; }
.img-preview { width:100%; height:90px; object-fit:cover; border-radius:8px; margin-bottom:6px; }
</style>
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div>
        <h1>Rooms &amp; Rates</h1>
        <div class="sub">Manage inventory, base pricing and category details</div>
      </div>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('dlg-new').showModal()">+ Add Room Category</button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="form-success" style="margin-bottom:16px;">
      <?= $_GET['msg'] === 'created' ? '✓ New room category created and is now live on the website!' : '✓ Room category updated successfully.' ?>
    </div>
    <?php endif; ?>

    <!-- Room Categories Table -->
    <div class="dash-panel">
      <div class="dash-panel-head"><h3>Room Categories</h3></div>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Category</th><th>Description</th><th>Base Price</th><th>Max Occ.</th><th>Size (m²)</th><th>Rooms</th><th>Active</th><th>Edit</th></tr></thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
              <td><strong><?= sanitize($cat['name']) ?></strong></td>
              <td style="max-width:200px;"><span style="font-size:0.82rem;color:var(--clay);"><?= sanitize(truncateText($cat['description'] ?? '', 60)) ?></span></td>
              <td>₦<?= number_format($cat['base_price']) ?></td>
              <td><?= $cat['max_occupancy'] ?></td>
              <td><?= $cat['size_sqm'] ?></td>
              <td><?= $cat['room_count'] ?></td>
              <td><span class="pill pill-<?= $cat['is_active'] ? 'confirmed' : 'cancelled' ?>"><?= $cat['is_active'] ? 'Active' : 'Inactive' ?></span></td>
              <td><button class="btn btn-outline btn-sm" onclick="document.getElementById('edit-<?= $cat['id'] ?>').showModal()">Edit</button></td>
            </tr>

            <!-- EDIT DIALOG -->
            <dialog id="edit-<?= $cat['id'] ?>">
              <form method="POST" enctype="multipart/form-data">
                <div class="dlg-body">
                  <h3>Edit: <?= sanitize($cat['name']) ?></h3>
                  <input type="hidden" name="action" value="update_room">
                  <input type="hidden" name="id" value="<?= $cat['id'] ?>">

                  <label>Room Name</label>
                  <input type="text" name="name" value="<?= sanitize($cat['name']) ?>" required>

                  <label>Description (shown on website)</label>
                  <textarea name="description"><?= sanitize($cat['description'] ?? '') ?></textarea>

                  <div class="row2">
                    <div>
                      <label>Base Price (₦)</label>
                      <input type="number" name="base_price" value="<?= $cat['base_price'] ?>" required>
                    </div>
                    <div>
                      <label>Max Occupancy</label>
                      <input type="number" name="max_occupancy" value="<?= $cat['max_occupancy'] ?>" required>
                    </div>
                  </div>

                  <div class="row2">
                    <div>
                      <label>Room Size (m²)</label>
                      <input type="number" name="size_sqm" value="<?= $cat['size_sqm'] ?>">
                    </div>
                    <div>
                      <label>Bed Configuration</label>
                      <input type="text" name="bed_configuration" value="<?= sanitize($cat['bed_configuration'] ?? '') ?>" placeholder="e.g. 1 King Bed">
                    </div>
                  </div>

                  <label>View Type</label>
                  <input type="text" name="view_type" value="<?= sanitize($cat['view_type'] ?? '') ?>" placeholder="e.g. Ocean View">

                  <label>Main Room Image (replace)</label>
                  <?php if (!empty($cat['main_image'])): ?>
                    <img src="<?= sanitize(getImageUrl($cat['main_image'])) ?>" class="img-preview" alt="Room Image">
                  <?php endif; ?>
                  <input type="file" name="main_image" accept="image/*" style="margin-bottom:14px;">

                  <label>Bathroom Image (replace)</label>
                  <?php if (!empty($cat['bathroom_image'])): ?>
                    <img src="<?= sanitize(getImageUrl($cat['bathroom_image'])) ?>" class="img-preview" alt="Bathroom">
                  <?php endif; ?>
                  <input type="file" name="bathroom_image" accept="image/*" style="margin-bottom:14px;">

                  <label>Toilet Image (replace)</label>
                  <?php if (!empty($cat['toilet_image'])): ?>
                    <img src="<?= sanitize(getImageUrl($cat['toilet_image'])) ?>" class="img-preview" alt="Toilet">
                  <?php endif; ?>
                  <input type="file" name="toilet_image" accept="image/*" style="margin-bottom:14px;">

                  <div class="cb-row">
                    <input type="checkbox" name="is_accessible" id="acc-<?= $cat['id'] ?>" <?= $cat['is_accessible'] ? 'checked' : '' ?>>
                    <label for="acc-<?= $cat['id'] ?>" style="margin:0;">Wheelchair accessible</label>
                  </div>
                  <div class="cb-row">
                    <input type="checkbox" name="is_active" id="act-<?= $cat['id'] ?>" <?= $cat['is_active'] ? 'checked' : '' ?>>
                    <label for="act-<?= $cat['id'] ?>" style="margin:0;">Active &amp; bookable on website</label>
                  </div>
                </div>
                <div class="dlg-footer">
                  <button type="submit" class="btn btn-primary">Save Changes</button>
                  <button type="button" class="btn btn-outline" onclick="document.getElementById('edit-<?= $cat['id'] ?>').close()">Cancel</button>
                </div>
              </form>
            </dialog>

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pricing Rules Table -->
    <div class="dash-panel">
      <div class="dash-panel-head"><h3>Active Pricing Rules</h3><span class="muted" style="font-size:0.82rem;">Seasonal, weekend, promo &amp; corporate rates</span></div>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Rule</th><th>Category</th><th>Type</th><th>Adjustment</th><th>Promo Code</th><th>Window</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($pricingRules as $rule): ?>
            <tr>
              <td><?= sanitize($rule['name']) ?></td>
              <td><?= sanitize($rule['category_name']) ?></td>
              <td><?= str_replace('_',' ',ucfirst($rule['rule_type'])) ?></td>
              <td>
                <?php
                  $sign = in_array($rule['adjustment_type'], ['percent_increase']) ? '+' : '−';
                  $unit = strpos($rule['adjustment_type'], 'percent') !== false ? '%' : '₦';
                  echo $rule['adjustment_type'] === 'fixed_price' ? '= ₦' . number_format($rule['adjustment_value']) : $sign . $unit . number_format($rule['adjustment_value']);
                ?>
              </td>
              <td><?= $rule['promo_code'] ? '<code>' . sanitize($rule['promo_code']) . '</code>' : '—' ?></td>
              <td><?= $rule['start_date'] ? date('d M', strtotime($rule['start_date'])) . ' – ' . date('d M Y', strtotime($rule['end_date'])) : 'Always' ?></td>
              <td><span class="pill pill-<?= $rule['is_active'] ? 'confirmed' : 'cancelled' ?>"><?= $rule['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- ADD NEW ROOM CATEGORY DIALOG -->
<dialog id="dlg-new">
  <form method="POST" enctype="multipart/form-data">
    <div class="dlg-body">
      <h3>✦ Add New Room Category</h3>
      <input type="hidden" name="action" value="create_room">

      <label>Room Category Name *</label>
      <input type="text" name="name" required placeholder="e.g. Deluxe Ocean Suite">

      <label>Description (shown on website) *</label>
      <textarea name="description" required placeholder="Describe this room type for guests…"></textarea>

      <div class="row2">
        <div>
          <label>Base Price per Night (₦) *</label>
          <input type="number" name="base_price" required placeholder="e.g. 120000">
        </div>
        <div>
          <label>Max Occupancy *</label>
          <input type="number" name="max_occupancy" required placeholder="e.g. 2" min="1">
        </div>
      </div>

      <div class="row2">
        <div>
          <label>Bed Configuration</label>
          <input type="text" name="bed_configuration" placeholder="e.g. 1 King Bed">
        </div>
        <div>
          <label>Room Size (m²)</label>
          <input type="number" name="size_sqm" placeholder="e.g. 42">
        </div>
      </div>

      <div class="row2">
        <div>
          <label>View Type</label>
          <input type="text" name="view_type" placeholder="e.g. Ocean View">
        </div>
        <div>
          <label>Number of Physical Rooms</label>
          <input type="number" name="room_count" value="1" min="1" placeholder="1">
        </div>
      </div>

      <div class="row2">
        <div>
          <label>Starting Floor</label>
          <input type="number" name="floor" value="1" min="1" placeholder="e.g. 3">
        </div>
        <div>&nbsp;</div>
      </div>

      <label>Amenities (comma-separated)</label>
      <input type="text" name="amenities" placeholder='e.g. Free Wi-Fi, Air Conditioning, Smart TV, Minibar'>

      <label>Main Room Image</label>
      <input type="file" name="main_image" accept="image/*" style="margin-bottom:14px;">

      <label>Bathroom Image</label>
      <input type="file" name="bathroom_image" accept="image/*" style="margin-bottom:14px;">

      <label>Toilet Image</label>
      <input type="file" name="toilet_image" accept="image/*" style="margin-bottom:14px;">

      <div class="cb-row">
        <input type="checkbox" name="is_accessible" id="new-accessible">
        <label for="new-accessible" style="margin:0;">Wheelchair accessible room</label>
      </div>
    </div>
    <div class="dlg-footer">
      <button type="submit" class="btn btn-primary">Create Room Category</button>
      <button type="button" class="btn btn-outline" onclick="document.getElementById('dlg-new').close()">Cancel</button>
    </div>
  </form>
</dialog>

<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>

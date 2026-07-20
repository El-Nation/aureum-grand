<?php
/**
 * admin/ota-sync.php
 *
 * Manages which room categories are mapped to which OTA (Booking.com,
 * Expedia, etc.) listings, and lets staff toggle sync on/off per channel.
 *
 * IMPORTANT — why this doesn't call Booking.com/Expedia directly:
 * Individual hotels cannot integrate with these platforms via a simple
 * API key the way Paystack or Twilio work. Booking.com and Expedia both
 * require going through a certified "Channel Manager" middleman (e.g.
 * SiteMinder, Cloudbeds, RateGain, STAAH) — these are paid third-party
 * services with their own contracts, certification process, and per-
 * property onboarding. There is no code shortcut around this; it's a
 * deliberate gatekeeping model the OTAs use.
 *
 * What this page DOES give you: the local data model and admin UI for
 * managing channel mappings, plus a working sync-log structure. The
 * moment you sign up with a channel manager, most of them let you export
 * inventory via a CSV/iCal feed or a webhook — this page's `ota_channels`
 * table and the sync toggle are designed to slot straight into that.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffLogin(['administrator','general_manager','revenue_manager']);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_mapping') {
    $stmt = $db->prepare("INSERT INTO ota_channels (name, category_id, external_listing_id, sync_enabled) VALUES (?,?,?,?)");
    $stmt->execute([
        sanitize($_POST['name']), $_POST['category_id'], sanitize($_POST['external_listing_id']),
        isset($_POST['sync_enabled']) ? 1 : 0
    ]);
    logActivity('staff', $_SESSION['staff_id'], 'Created OTA channel mapping');
    header('Location: ' . BASE_URL . '/admin/ota-sync.php?msg=created');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_sync') {
    $stmt = $db->prepare("UPDATE ota_channels SET sync_enabled = NOT sync_enabled WHERE id = ?");
    $stmt->execute([$_POST['channel_id']]);
    header('Location: ' . BASE_URL . '/admin/ota-sync.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_sync') {
    // Simulates what a real sync pass would do: push current availability
    // and pricing for each mapped category, then stamp last_synced_at.
    // Swap the body of this block for a real API/webhook call once you
    // have a channel manager account.
    $stmt = $db->query("SELECT * FROM ota_channels WHERE sync_enabled = 1");
    $channels = $stmt->fetchAll();
    foreach ($channels as $ch) {
        $upd = $db->prepare("UPDATE ota_channels SET last_synced_at = NOW() WHERE id = ?");
        $upd->execute([$ch['id']]);
    }
    logActivity('staff', $_SESSION['staff_id'], 'Ran OTA sync (local-only, no channel manager connected)', null, null, count($channels) . ' channel(s)');
    header('Location: ' . BASE_URL . '/admin/ota-sync.php?msg=synced');
    exit;
}

$stmt = $db->query("SELECT oc.*, rc.name AS category_name, rc.base_price FROM ota_channels oc
    JOIN room_categories rc ON oc.category_id = rc.id ORDER BY oc.id DESC");
$channels = $stmt->fetchAll();

$stmt = $db->query("SELECT id, name FROM room_categories WHERE is_active = 1");
$categories = $stmt->fetchAll();

$pageTitle = 'OTA Sync';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OTA Sync — Aureum Console</title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>
<div class="dash-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="dash-main">
    <div class="dash-topbar">
      <div><h1>OTA Channel Sync</h1><div class="sub">Map room categories to Booking.com, Expedia, and other travel platforms</div></div>
      <button class="btn btn-dark btn-sm" onclick="document.getElementById('newMapping').showModal()">+ Add Channel Mapping</button>
    </div>

    <div class="stub-banner">
      <span>ℹ️</span>
      <span>
        <strong>Why this isn't "live" yet:</strong> Booking.com and Expedia don't offer direct API keys to individual
        hotels — they require a certified <strong>Channel Manager</strong> middleman (SiteMinder, Cloudbeds, RateGain,
        STAAH, etc.), which is a separate paid service with its own contract and onboarding. This page manages the
        local mapping data and is built to plug straight into that service's webhook/feed once you sign up —
        there's no code shortcut around the OTA's own requirements.
      </span>
    </div>

    <?php if (isset($_GET['msg'])): ?>
      <div class="form-success">
        <?= $_GET['msg'] === 'created' ? 'Channel mapping created.' : 'Sync pass completed for all enabled channels (local timestamp only — connect a channel manager to push real updates).' ?>
      </div>
    <?php endif; ?>

    <dialog id="newMapping" style="border:none;border-radius:12px;padding:0;max-width:460px;width:90%;">
      <form method="POST" style="padding:28px;">
        <input type="hidden" name="action" value="create_mapping">
        <h3 style="font-family:var(--font-body);margin-bottom:18px;">New Channel Mapping</h3>
        <div class="dash-form-group"><label>Channel Name</label>
          <input type="text" name="name" required placeholder="e.g. Booking.com, Expedia">
        </div>
        <div class="dash-form-group"><label>Room Category</label>
          <select name="category_id" required>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="dash-form-group"><label>External Listing ID</label>
          <input type="text" name="external_listing_id" placeholder="ID from the OTA/channel manager dashboard">
        </div>
        <div class="dash-form-group"><label><input type="checkbox" name="sync_enabled" checked> Enable sync</label></div>
        <div class="flex gap-12" style="margin-top:18px;">
          <button type="submit" class="btn btn-primary">Create Mapping</button>
          <button type="button" class="btn btn-outline" onclick="document.getElementById('newMapping').close()">Cancel</button>
        </div>
      </form>
    </dialog>

    <div class="dash-panel">
      <div class="dash-panel-head">
        <h3>Channel Mappings</h3>
        <form method="POST"><input type="hidden" name="action" value="run_sync">
          <button type="submit" class="btn btn-outline btn-sm">Run Sync Pass</button>
        </form>
      </div>
      <div class="table-scroll">
        <table class="data-table">
          <thead><tr><th>Channel</th><th>Room Category</th><th>Current Rate</th><th>External Listing ID</th><th>Last Synced</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($channels as $ch): ?>
            <tr>
              <td><?= sanitize($ch['name']) ?></td>
              <td><?= sanitize($ch['category_name']) ?></td>
              <td>₦<?= number_format($ch['base_price']) ?></td>
              <td><code><?= sanitize($ch['external_listing_id'] ?: '—') ?></code></td>
              <td class="muted" style="font-size:0.8rem;"><?= $ch['last_synced_at'] ? date('d M Y, H:i', strtotime($ch['last_synced_at'])) : 'Never' ?></td>
              <td><span class="pill pill-<?= $ch['sync_enabled'] ? 'confirmed' : 'cancelled' ?>"><?= $ch['sync_enabled'] ? 'Sync Enabled' : 'Disabled' ?></span></td>
              <td>
                <form method="POST"><input type="hidden" name="action" value="toggle_sync"><input type="hidden" name="channel_id" value="<?= $ch['id'] ?>">
                  <button type="submit" class="btn btn-outline btn-sm"><?= $ch['sync_enabled'] ? 'Disable' : 'Enable' ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($channels)): ?><tr><td colspan="7" class="empty-state">No channel mappings yet. Add one to get started once you've signed up with a channel manager.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/admin-mobile-toggle.php'; ?>
</body>
</html>


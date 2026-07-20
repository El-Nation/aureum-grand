<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$pageTitle = 'Rooms & Suites';

$checkin  = $_GET['checkin'] ?? '';
$checkout = $_GET['checkout'] ?? '';
$guests   = (int) ($_GET['guests'] ?? 0);
$categoryFilter = $_GET['category'] ?? '';
$minBudget = $_GET['min_budget'] ?? '';
$maxBudget = $_GET['max_budget'] ?? '';
$viewType = $_GET['view'] ?? '';
$accessible = isset($_GET['accessible']);

$sql = "SELECT * FROM room_categories WHERE is_active = 1";
$params = [];

if ($categoryFilter) { $sql .= " AND id = ?"; $params[] = $categoryFilter; }
if ($guests) { $sql .= " AND max_occupancy >= ?"; $params[] = $guests; }
if ($minBudget !== '') { $sql .= " AND base_price >= ?"; $params[] = $minBudget; }
if ($maxBudget !== '') { $sql .= " AND base_price <= ?"; $params[] = $maxBudget; }
if ($viewType) { $sql .= " AND view_type LIKE ?"; $params[] = "%$viewType%"; }
if ($accessible) { $sql .= " AND is_accessible = 1"; }
$sql .= " ORDER BY base_price ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// If dates were given, filter out categories with no availability and compute live price
$results = [];
foreach ($rooms as $room) {
    $avail = ['available' => true, 'rooms_left' => null];
    $price = $room['base_price'];
    if ($checkin && $checkout) {
        $avail = isCategoryAvailable($room['id'], $checkin, $checkout);
        if (!$avail['available']) continue;
        $stay = calculateStayTotal($room['id'], $checkin, $checkout);
        $price = $stay['avg_rate'];
    }
    $room['live_price'] = $price;
    $room['rooms_left'] = $avail['rooms_left'] ?? null;
    $results[] = $room;
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="section-tight bg-marble">
  <div class="container">
    <span class="eyebrow">All Accommodations</span>
    <h1 style="margin-top:14px;font-size:2.6rem;">Rooms &amp; Suites</h1>
    <p class="lead" style="margin-top:18px; max-width: 700px;">Discover our collection of 128 meticulously designed rooms and suites. Each space is thoughtfully curated with premium furnishings, cutting-edge amenities, and breathtaking views to provide an unforgettable sanctuary of comfort and luxury during your stay.</p>
  </div>
</section>

<section class="section" style="padding-top:48px;">
  <div class="container">
    <form method="GET" action="<?= BASE_URL ?>/public/rooms.php">
      <div class="filters-toggle-row">
        <strong style="font-size:0.92rem;"><?= count($results) ?> room<?= count($results) !== 1 ? 's' : '' ?> found</strong>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('filtersPanel').classList.toggle('show-mobile')">Refine Search</button>
      </div>

      <div class="filters-panel" id="filtersPanel">
        <div class="field">
          <label>Check-in</label>
          <input type="date" name="checkin" value="<?= sanitize($checkin) ?>">
        </div>
        <div class="field">
          <label>Check-out</label>
          <input type="date" name="checkout" value="<?= sanitize($checkout) ?>">
        </div>
        <div class="field">
          <label>Guests</label>
          <select name="guests">
            <option value="0">Any</option>
            <?php for ($g = 1; $g <= 4; $g++): ?>
              <option value="<?= $g ?>" <?= $guests == $g ? 'selected' : '' ?>><?= $g ?> Guest<?= $g > 1 ? 's' : '' ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="field">
          <label>View Type</label>
          <select name="view">
            <option value="">Any View</option>
            <option value="Garden" <?= $viewType === 'Garden' ? 'selected' : '' ?>>Garden View</option>
            <option value="Ocean" <?= $viewType === 'Ocean' ? 'selected' : '' ?>>Ocean View</option>
            <option value="City" <?= $viewType === 'City' ? 'selected' : '' ?>>City View</option>
          </select>
        </div>
        <div class="field">
          <label>Min Budget (₦)</label>
          <input type="number" name="min_budget" placeholder="0" value="<?= sanitize($minBudget) ?>">
        </div>
        <div class="field">
          <label>Max Budget (₦)</label>
          <input type="number" name="max_budget" placeholder="500000" value="<?= sanitize($maxBudget) ?>">
        </div>
      </div>

      <div class="chip-group" style="margin-bottom:32px;">
        <label class="chip <?= $accessible ? 'active' : '' ?>" style="margin:0;">
          <input type="checkbox" name="accessible" value="1" <?= $accessible ? 'checked' : '' ?> style="display:none;" onchange="this.parentElement.classList.toggle('active');this.form.submit()">
          ♿ Accessible Rooms
        </label>
        <button type="submit" class="chip">Apply Filters</button>
        <a href="<?= BASE_URL ?>/public/rooms.php" class="chip">Clear All</a>
      </div>
    </form>

    <?php if (empty($results)): ?>
      <div class="empty-state">
        <h3 style="font-family:var(--font-body);">No rooms match these filters</h3>
        <p class="muted" style="margin-top:8px;">Try widening your dates or budget range.</p>
      </div>
    <?php else: ?>
    <div class="room-grid" id="roomResults">
      <?php
        // Load all category IDs once so each room always maps to its own image
        $roomImages = getRoomImages();
        $allCatIds  = $db->query("SELECT id FROM room_categories WHERE is_active=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($results as $i => $room):
          $amenities = json_decode($room['amenities'], true) ?: [];
          // Prefer DB-stored main_image (set via admin), fall back to folder index
          if (!empty($room['main_image'])) {
              $imgSrc = getImageUrl($room['main_image']);
          } else {
              $roomIdx = array_search($room['id'], $allCatIds);
              if ($roomIdx === false) $roomIdx = $i;
              $imgSrc  = $roomImages[$roomIdx % count($roomImages)];
          }
      ?>
      <div class="room-card fade-up" style="animation-delay:<?= ($i % 6) * 0.08 ?>s">
        <div class="room-card-img">
          <img src="<?= $imgSrc ?>" alt="<?= sanitize($room['name']) ?>">
          <span class="room-card-tag"><?= sanitize($room['view_type']) ?></span>
          <button class="room-card-fav" aria-label="Save to favorites" type="button">♥</button>
        </div>
        <div class="room-card-body">
          <div class="room-card-cat"><?= $room['rooms_left'] !== null ? $room['rooms_left'] . ' room(s) left for these dates' : 'Suite Category' ?></div>
          <h3><?= sanitize($room['name']) ?></h3>
          <div class="room-card-meta">
            <span>👤 Up to <?= $room['max_occupancy'] ?></span>
            <span>🛏 <?= sanitize($room['bed_configuration']) ?></span>
            <span>📐 <?= $room['size_sqm'] ?> m²</span>
          </div>
          <div class="chip-group" style="margin-top:10px;">
            <?php foreach (array_slice($amenities, 0, 3) as $a): ?>
              <span class="chip" style="cursor:default;font-size:0.74rem;padding:4px 10px;"><?= sanitize($a) ?></span>
            <?php endforeach; ?>
          </div>
          <div class="room-card-foot">
            <div class="price-tag">
              <span class="amount">₦<?= number_format($room['live_price']) ?></span>
              <div class="per">per night</div>
            </div>
            <a href="<?= BASE_URL ?>/public/room-detail.php?id=<?= $room['id'] ?>&checkin=<?= urlencode($checkin) ?>&checkout=<?= urlencode($checkout) ?>" class="btn btn-dark btn-sm">View Room</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<style>
@media (max-width: 900px) {
  .filters-panel { display: none; }
  .filters-panel.show-mobile { display: grid; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

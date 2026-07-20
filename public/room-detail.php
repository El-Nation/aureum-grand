<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$roomId = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM room_categories WHERE id = ? AND is_active = 1");
$stmt->execute([$roomId]);
$room = $stmt->fetch();

if (!$room) {
    header('Location: ' . BASE_URL . '/public/rooms.php');
    exit;
}

$amenities = json_decode($room['amenities'], true) ?: [];
$checkin  = $_GET['checkin']  ?? date('Y-m-d', strtotime('+1 day'));
$checkout = $_GET['checkout'] ?? date('Y-m-d', strtotime('+2 day'));

// Resolve main image: prefer the DB-stored main_image, fall back to upload folder index
if (!empty($room['main_image'])) {
    $mainImg = getImageUrl($room['main_image']);
} else {
    $allCatIds  = $db->query("SELECT id FROM room_categories WHERE is_active=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    $roomImages = getRoomImages();
    $roomIndex  = array_search($room['id'], $allCatIds);
    if ($roomIndex === false) $roomIndex = 0;
    $mainImg    = $roomImages[$roomIndex % count($roomImages)];
}
// Resolve bathroom / toilet images
$bathroomImages = getBathroomImages();
$allCatIds2     = isset($allCatIds) ? $allCatIds : $db->query("SELECT id FROM room_categories WHERE is_active=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
$roomIndex2     = array_search($room['id'], $allCatIds2);
if ($roomIndex2 === false) $roomIndex2 = 0;
$bathroomImg = !empty($room['bathroom_image']) ? getImageUrl($room['bathroom_image']) : $bathroomImages[$roomIndex2 % count($bathroomImages)];
$toiletImg   = !empty($room['toilet_image'])   ? getImageUrl($room['toilet_image'])   : null;

$pageTitle = $room['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Room Detail — fully responsive layout ── */
.rd-back { padding: 36px 0 0; }
.rd-grid {
  display: grid;
  grid-template-columns: 1.35fr 1fr;
  gap: 48px;
  align-items: start;
}
.rd-main-img {
  border-radius: var(--radius-lg);
  overflow: hidden;
  aspect-ratio: 16/10;
  margin-bottom: 24px;
}
.rd-main-img img { width:100%; height:100%; object-fit:cover; display:block; }

/* Bathroom section */
.rd-bathroom {
  margin-bottom: 36px;
}
.rd-bathroom-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-wrap: wrap;
  flex-wrap: wrap;
  gap: 10px;
}
.rd-bathroom-gallery {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}
.rd-bathroom-header h3 {
  font-family: var(--font-body);
  font-size: 1.05rem;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.rd-bathroom-thumb {
  border-radius: var(--radius-md);
  overflow: hidden;
  aspect-ratio: 16/9;
  cursor: pointer;
  position: relative;
}
.rd-bathroom-thumb img {
  width: 100%; height: 100%; object-fit: cover; display: block;
  transition: transform 0.35s ease;
}
.rd-bathroom-thumb:hover img { transform: scale(1.04); }
.rd-bathroom-thumb .rd-view-overlay {
  position: absolute;
  inset: 0;
  background: rgba(20,33,28,0.42);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.25s;
}
.rd-bathroom-thumb:hover .rd-view-overlay { opacity: 1; }
.rd-view-overlay span {
  background: var(--gold);
  color: var(--forest);
  font-size: 0.82rem;
  font-weight: 700;
  padding: 8px 20px;
  border-radius: 99px;
  letter-spacing: 0.04em;
}

/* Lightbox */
#bathLightbox {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(10,18,14,0.92);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
#bathLightbox.open { display: flex; }
#bathLightbox img {
  max-width: 90vw;
  max-height: 85vh;
  border-radius: var(--radius-lg);
  object-fit: contain;
  box-shadow: 0 32px 80px rgba(0,0,0,0.6);
}
#bathLightbox .lb-close {
  position: absolute;
  top: 20px; right: 28px;
  font-size: 2rem;
  color: #fff;
  cursor: pointer;
  line-height: 1;
  background: none;
  border: none;
}

/* Stats row */
.rd-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-top: 36px;
}
.rd-stat {
  background: var(--marble);
  border-radius: var(--radius-md);
  padding: 16px 14px;
  text-align: center;
}
.rd-stat .label { font-size: 0.72rem; text-transform: uppercase; color: var(--clay); margin-bottom: 6px; }
.rd-stat .value { font-family: var(--font-display); font-size: 1.2rem; }

/* Booking panel */
.rd-panel {
  position: sticky;
  top: 110px;
  background: var(--emerald-deep);
  color: var(--linen);
  border-radius: var(--radius-lg);
  padding: 42px 36px;
  box-shadow: 0 20px 60px rgba(20,33,28,0.25);
}
.rd-panel label { color: rgba(246,243,236,0.85); font-size: 0.85rem; font-weight: 500; }
.rd-panel input,
.rd-panel select,
.rd-panel textarea {
  background: rgba(246,243,236,0.08);
  border: 1px solid rgba(246,243,236,0.18);
  color: var(--linen);
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  width: 100%;
  font-size: 0.95rem;
  margin-top: 8px;
}
.rd-panel input::placeholder,
.rd-panel textarea::placeholder { color: rgba(246,243,236,0.5); }
.rd-panel input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }
.rd-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.rd-form-group { margin-bottom: 14px; }
.rd-breakdown {
  background: rgba(246,243,236,0.06);
  border-radius: var(--radius-sm);
  padding: 14px 16px;
  margin: 18px 0;
  font-size: 0.87rem;
}
.rd-breakdown .row { display: flex; justify-content: space-between; margin-bottom: 5px; }
.rd-breakdown .total { font-weight: 700; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(246,243,236,0.15); }

/* Responsive */
@media (max-width: 1024px) {
  .rd-grid { grid-template-columns: 1fr; }
  .rd-panel { position: static; margin-top: 40px; }
  .rd-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .rd-stats { grid-template-columns: 1fr 1fr; gap: 10px; }
  .rd-stat { padding: 12px 10px; }
  .rd-stat .value { font-size: 1rem; }
  .rd-form-row { grid-template-columns: 1fr; }
  .rd-panel { padding: 24px 18px; }
  .rd-bathroom-header { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
  .rd-back { padding: 20px 0 0; }
  h1 { font-size: 1.8rem !important; }
}
</style>

<section class="rd-back">
  <div class="container">
    <a href="<?= BASE_URL ?>/public/rooms.php" class="muted" style="font-size:0.86rem;">&larr; Back to all rooms</a>
  </div>
</section>

<section class="section" style="padding-top:24px;">
  <div class="container">
    <div class="rd-grid">

      <!-- LEFT: Images + Info -->
      <div>
        <!-- Main Room Image -->
        <div class="rd-main-img">
          <img src="<?= $mainImg ?>" alt="<?= sanitize($room['name']) ?>">
        </div>

        <!-- Bathroom & Toilet Section -->
        <div class="rd-bathroom">
          <div class="rd-bathroom-header">
            <h3>🛁 Bathroom &amp; Toilet</h3>
            <span class="eyebrow" style="font-size:0.72rem;">En-suite private facilities</span>
          </div>
          <div class="rd-bathroom-gallery">
            <div class="rd-bathroom-thumb" onclick="openLightbox('<?= $bathroomImg ?>')">
              <img src="<?= $bathroomImg ?>" alt="Bathroom — <?= sanitize($room['name']) ?>">
              <div class="rd-view-overlay"><span>View Bathroom</span></div>
            </div>
            <?php if ($toiletImg): ?>
            <div class="rd-bathroom-thumb" onclick="openLightbox('<?= $toiletImg ?>')">
              <img src="<?= $toiletImg ?>" alt="Toilet — <?= sanitize($room['name']) ?>">
              <div class="rd-view-overlay"><span>View Toilet</span></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Room Info -->
        <span class="eyebrow"><?= sanitize($room['view_type']) ?></span>
        <h1 style="margin-top:14px;font-size:2.2rem;"><?= sanitize($room['name']) ?></h1>
        <p class="lead" style="margin-top:16px;"><?= sanitize($room['description']) ?></p>

        <!-- Stats -->
        <div class="rd-stats">
          <div class="rd-stat">
            <div class="label">Occupancy</div>
            <div class="value">Up to <?= $room['max_occupancy'] ?></div>
          </div>
          <div class="rd-stat">
            <div class="label">Bed Setup</div>
            <div class="value"><?= sanitize($room['bed_configuration']) ?></div>
          </div>
          <div class="rd-stat">
            <div class="label">Room Size</div>
            <div class="value"><?= $room['size_sqm'] ?> m²</div>
          </div>
          <div class="rd-stat">
            <div class="label">Accessibility</div>
            <div class="value"><?= $room['is_accessible'] ? 'Accessible' : 'Standard' ?></div>
          </div>
        </div>

        <div class="divider" style="margin:32px 0;"></div>

        <h3 style="font-family:var(--font-body);font-size:1.05rem;margin-bottom:16px;">Amenities</h3>
        <div class="chip-group">
          <?php foreach ($amenities as $a): ?>
            <span class="chip" style="cursor:default;"><?= sanitize($a) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- RIGHT: Booking Panel -->
      <div>
        <div class="rd-panel">
          <div style="margin-bottom:20px;">
            <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:rgba(246,243,236,0.6);margin-bottom:4px;">Starting from</div>
            <span style="font-family:var(--font-display);font-size:2rem;color:var(--gold);">₦<?= number_format($room['base_price']) ?></span>
            <span style="font-size:0.85rem;color:rgba(246,243,236,0.55);"> / night</span>
          </div>

          <form id="bookingForm">
            <input type="hidden" name="category_id" value="<?= $room['id'] ?>">

            <div class="rd-form-row">
              <div class="rd-form-group">
                <label>Check-in</label>
                <input type="date" name="check_in" id="check_in" value="<?= sanitize($checkin) ?>" min="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="rd-form-group">
                <label>Check-out</label>
                <input type="date" name="check_out" id="check_out" value="<?= sanitize($checkout) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
              </div>
            </div>

            <div class="rd-form-row">
              <div class="rd-form-group">
                <label>Adults</label>
                <select name="adults" id="adults">
                  <?php for ($a = 1; $a <= $room['max_occupancy']; $a++): ?>
                    <option value="<?= $a ?>"><?= $a ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="rd-form-group">
                <label>Children</label>
                <select name="children" id="children">
                  <?php for ($c = 0; $c < $room['max_occupancy']; $c++): ?>
                    <option value="<?= $c ?>"><?= $c ?></option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>

            <div class="rd-form-group">
              <label>Promo Code (optional)</label>
              <input type="text" name="promo_code" id="promo_code" placeholder="e.g. EARLY15">
            </div>

            <div class="rd-breakdown">
              <div class="row"><span>Nights</span><span id="bdNights">—</span></div>
              <div class="row"><span>Avg. rate / night</span><span id="bdRate">₦0</span></div>
              <div class="row total"><span>Total</span><span id="bdTotal">₦0</span></div>
            </div>

            <div id="availabilityNote" style="font-size:0.82rem;margin-bottom:14px;"></div>

            <div class="rd-form-group">
              <label>Full Name</label>
              <input type="text" name="guest_name" required <?= isGuestLoggedIn() ? 'value="' . sanitize($_SESSION['guest_name'] ?? '') . '"' : '' ?>>
            </div>
            <div class="rd-form-group">
              <label>Email</label>
              <input type="email" name="guest_email" required <?= isGuestLoggedIn() ? 'value="' . sanitize($_SESSION['guest_email'] ?? '') . '"' : '' ?>>
            </div>
            <div class="rd-form-group">
              <label>Phone</label>
              <input type="tel" name="guest_phone">
            </div>
            <div class="rd-form-group">
              <label>Special Requests</label>
              <textarea name="special_requests" rows="2" placeholder="Late check-in, dietary needs…"></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="submitBookingBtn" style="width:100%;padding:14px;">Reserve This Room</button>
            <p style="font-size:0.74rem;margin-top:10px;text-align:center;color:rgba(246,243,236,0.5);">You won't be charged yet. Payment via Paystack on the next step.</p>
          </form>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Bathroom/Toilet Lightbox -->
<div id="bathLightbox" role="dialog" aria-modal="true" aria-label="Facility view" onclick="closeLightbox(event)">
  <button class="lb-close" onclick="document.getElementById('bathLightbox').classList.remove('open')" aria-label="Close">&times;</button>
  <img id="lightboxImg" src="" alt="Facility View">
</div>

<script>
const categoryId = <?= $room['id'] ?>;

function openLightbox(imgSrc) {
  document.getElementById('lightboxImg').src = imgSrc;
  document.getElementById('bathLightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox(e) {
  if (e.target === document.getElementById('bathLightbox')) {
    document.getElementById('bathLightbox').classList.remove('open');
    document.body.style.overflow = '';
  }
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.getElementById('bathLightbox').classList.remove('open');
    document.body.style.overflow = '';
  }
});

function nightsBetween(a, b) {
  const d1 = new Date(a), d2 = new Date(b);
  return Math.max(0, Math.round((d2 - d1) / 86400000));
}

async function refreshPrice() {
  const checkIn  = document.getElementById('check_in').value;
  const checkOut = document.getElementById('check_out').value;
  const promo    = document.getElementById('promo_code').value;
  const nights   = nightsBetween(checkIn, checkOut);
  document.getElementById('bdNights').textContent = nights || 0;
  if (!checkIn || !checkOut || nights <= 0) return;
  try {
    const res  = await fetch(BASE_URL + `/api/calculate-price.php?category_id=${categoryId}&check_in=${checkIn}&check_out=${checkOut}&promo_code=${encodeURIComponent(promo)}`);
    const data = await res.json();
    if (data.success) {
      document.getElementById('bdRate').textContent  = '₦' + Math.round(data.avg_rate).toLocaleString();
      document.getElementById('bdTotal').textContent = '₦' + Math.round(data.total).toLocaleString();
      const note      = document.getElementById('availabilityNote');
      const submitBtn = document.getElementById('submitBookingBtn');
      if (data.available) {
        note.innerHTML    = `<span style="color:#6fcf97">✓ Available — ${data.rooms_left ?? ''} room(s) left</span>`;
        submitBtn.disabled = false;
      } else {
        note.innerHTML    = `<span style="color:#eb5757">✕ ${data.reason || 'Not available for these dates'}</span>`;
        submitBtn.disabled = true;
      }
    }
  } catch(e) { console.error('Price check failed', e); }
}

['check_in','check_out','promo_code','adults','children'].forEach(id => {
  document.getElementById(id).addEventListener('change', refreshPrice);
});
refreshPrice();

document.getElementById('bookingForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const submitBtn = document.getElementById('submitBookingBtn');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Processing…';
  const payload = Object.fromEntries(new FormData(this).entries());
  try {
    const res  = await fetch(BASE_URL + '/api/create-booking.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = BASE_URL + `/public/booking-confirmation.php?ref=${data.booking_reference}`;
    } else {
      alert('Booking failed: ' + (data.errors ? data.errors.join(', ') : data.message));
      submitBtn.disabled = false;
      submitBtn.textContent = 'Reserve This Room';
    }
  } catch(err) {
    alert('Something went wrong. Please try again.');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Reserve This Room';
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

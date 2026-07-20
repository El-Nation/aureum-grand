<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$pageTitle = 'A Five-Star Residence in Lagos';

// Pull featured rooms for the homepage showcase
$stmt = $db->query("SELECT * FROM room_categories WHERE is_active = 1 ORDER BY base_price DESC LIMIT 3");
$featuredRooms = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <div class="container">
    <div class="hero-grid">
      <div class="fade-up">
        <span class="eyebrow">Victoria Island, Lagos</span>
        <h1>Rest, <em>composed</em>.<br>Service, considered.</h1>
        <p class="lead">Aureum Grand is a five-star residence built around quiet detail — from the weight of the linen to the temperature of your evening light. Reserve a room, or let our concierge arrange the rest.</p>
        <div class="hero-actions">
          <a href="<?= BASE_URL ?>/public/rooms.php" class="btn btn-primary">View Rooms &amp; Suites</a>
          <a href="#services" class="btn btn-outline" style="color:var(--linen);border-color:var(--line-dark);">Guest Services</a>
        </div>
        <div class="hero-stats">
          <div class="hero-stat"><div class="num">128</div><div class="label">Rooms &amp; Suites</div></div>
          <div class="hero-stat"><div class="num">4.9</div><div class="label">Guest Rating</div></div>
          <div class="hero-stat"><div class="num">24/7</div><div class="label">Concierge</div></div>
        </div>
      </div>
      <div class="hero-visual fade-up" style="animation-delay:0.15s">
        <img src="<?= BASE_URL ?>/assets/images/home-screen.jpg" alt="Aureum Grand suite interior" style="width:100%;height:100%;object-fit:cover;">

      </div>
    </div>
  </div>
</section>

<div class="container">
  <form class="search-bar" action="<?= BASE_URL ?>/public/rooms.php" method="GET">
    <div class="search-grid">
      <div class="field">
        <label for="checkin">Check-in</label>
        <input type="date" id="checkin" name="checkin" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
      </div>
      <div class="field">
        <label for="checkout">Check-out</label>
        <input type="date" id="checkout" name="checkout" min="<?= date('Y-m-d', strtotime('+2 day')) ?>" value="<?= date('Y-m-d', strtotime('+3 day')) ?>">
      </div>
      <div class="field">
        <label for="guests">Guests</label>
        <select id="guests" name="guests">
          <option value="1">1 Guest</option>
          <option value="2" selected>2 Guests</option>
          <option value="3">3 Guests</option>
          <option value="4">4 Guests</option>
        </select>
      </div>
      <div class="field">
        <label for="category">Room Type</label>
        <select id="category" name="category">
          <option value="">Any Category</option>
          <?php foreach ($db->query("SELECT id, name FROM room_categories WHERE is_active=1") as $c): ?>
            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Check Availability</button>
    </div>
  </form>
</div>

<section class="section" id="rooms">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="eyebrow">Featured Stays</span>
        <h2 style="margin-top:14px;">Rooms built for staying still</h2>
      </div>
      <a href="<?= BASE_URL ?>/public/rooms.php" class="btn btn-outline">View All Rooms</a>
    </div>

    <div class="room-grid">
      <?php
        $roomImages  = getRoomImages();
        $allCatIds   = $db->query("SELECT id FROM room_categories WHERE is_active=1 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($featuredRooms as $i => $room):
          $amenities = json_decode($room['amenities'], true) ?: [];
          $roomIdx   = array_search($room['id'], $allCatIds);
          if ($roomIdx === false) $roomIdx = $i;
          $imgSrc    = $roomImages[$roomIdx % count($roomImages)];
      ?>
      <div class="room-card fade-up" style="animation-delay:<?= $i * 0.1 ?>s">
        <div class="room-card-img">
          <img src="<?= $imgSrc ?>" alt="<?= sanitize($room['name']) ?>">
          <span class="room-card-tag"><?= sanitize($room['view_type']) ?></span>
          <button class="room-card-fav" aria-label="Save to favorites" type="button">♥</button>
        </div>
        <div class="room-card-body">
          <div class="room-card-cat">Suite Category</div>
          <h3><?= sanitize($room['name']) ?></h3>
          <p class="muted" style="font-size:0.88rem;"><?= sanitize(truncateText($room['description'], 90)) ?></p>
          <div class="room-card-meta">
            <span>👤 <?= $room['max_occupancy'] ?> Guests</span>
            <span>📐 <?= $room['size_sqm'] ?> m²</span>
          </div>
          <div class="room-card-foot">
            <div class="price-tag">
              <span class="amount">₦<?= number_format($room['base_price']) ?></span>
              <div class="per">per night</div>
            </div>
            <a href="<?= BASE_URL ?>/public/room-detail.php?id=<?= $room['id'] ?>" class="btn btn-dark btn-sm">View Room</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-marble" id="services">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="eyebrow">While You Stay</span>
        <h2 style="margin-top:14px;">Guest services, on request</h2>
        <p class="lead" style="margin-top:18px; max-width: 650px;">Experience a seamless stay with our curated guest services. From effortless transportation to personalized wellness and dining, our dedicated team is at your disposal 24/7 to ensure your utmost comfort and convenience.</p>
      </div>
    </div>
    <div class="feature-row">
      <div class="feature-item fade-up">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11l19-7-7 19-3-7-9-5z"/></svg>
        <h3 style="font-size:1.1rem;font-family:var(--font-body);font-weight:600;">Airport Transfers</h3>
        <p class="muted" style="font-size:0.88rem;">Private chauffeur pickup, tracked against your flight in real time.</p>
      </div>
      <div class="feature-item fade-up" style="animation-delay:0.1s">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        <h3 style="font-size:1.1rem;font-family:var(--font-body);font-weight:600;">Spa &amp; Wellness</h3>
        <p class="muted" style="font-size:0.88rem;">Same-day appointment booking for treatments, pool and fitness suite.</p>
      </div>
      <div class="feature-item fade-up" style="animation-delay:0.2s">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19V5a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M14 3v6h6"/></svg>
        <h3 style="font-size:1.1rem;font-family:var(--font-body);font-weight:600;">Dining Reservations</h3>
        <p class="muted" style="font-size:0.88rem;">Tables held at our restaurants or trusted partners across Lagos.</p>
      </div>
      <div class="feature-item fade-up" style="animation-delay:0.3s">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 1 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <h3 style="font-size:1.1rem;font-family:var(--font-body);font-weight:600;">In-Room Requests</h3>
        <p class="muted" style="font-size:0.88rem;">Room service, housekeeping and maintenance, answered around the clock.</p>
      </div>
    </div>
  </div>
</section>

<section class="section bg-dark" id="loyalty">
  <div class="container">
    <div class="section-head">
      <div>
        <span class="eyebrow">Aureum Circle</span>
        <h2 style="margin-top:14px;color:var(--linen);">Loyalty that compounds</h2>
        <p class="lead" style="color:rgba(246,243,236,0.7);margin-top:14px;">Earn points on every stay, referral and restaurant visit. Tiers unlock upgrades, late checkout and priority concierge access.</p>
      </div>
    </div>
    <div class="tier-grid">
      <div class="tier-card">
        <div class="tier-name">Silver</div>
        <ul><li>1 point per ₦1,000 spent</li><li>Member-only rates</li><li>Birthday gift</li></ul>
      </div>
      <div class="tier-card">
        <div class="tier-name">Gold</div>
        <ul><li>All Silver benefits</li><li>Late checkout (2pm)</li><li>Welcome amenity</li></ul>
      </div>
      <div class="tier-card featured">
        <div class="tier-name">Platinum</div>
        <ul><li>All Gold benefits</li><li>Room upgrade, when available</li><li>Priority concierge line</li></ul>
      </div>
      <div class="tier-card">
        <div class="tier-name">VIP</div>
        <ul><li>All Platinum benefits</li><li>Dedicated relationship manager</li><li>Complimentary airport transfer</li></ul>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

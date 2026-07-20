<?php
// includes/header.php — shared header for public-facing pages
if (!isset($pageTitle)) $pageTitle = 'Aureum Grand Hotel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle) ?> — Aureum Grand Hotel</title>
<meta name="description" content="A premium hotel management and booking platform — luxury rooms, intelligent reservations, and concierge services in Lagos, Nigeria.">
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=1.1">
<script>var BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>

<header class="site-header" id="siteHeader">
  <div class="container" style="display:flex;align-items:center;justify-content:space-between;">
    <a href="<?= BASE_URL ?>/public/index.php" class="logo" style="display:flex;align-items:center;z-index:9999;">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpeg" alt="Aureum Grand" style="height:45px;margin-right:10px;">
    </a>
    <nav class="main-nav nav-links">
      <a href="<?= BASE_URL ?>/public/index.php">Home</a>
      <a href="<?= BASE_URL ?>/public/rooms.php">Rooms &amp; Suites</a>
      <a href="<?= BASE_URL ?>/public/index.php#services">Services</a>
      <a href="<?= BASE_URL ?>/public/index.php#loyalty">Loyalty</a>
      <a href="<?= BASE_URL ?>/public/contact.php">Contact</a>
    </nav>
    <div class="header-actions nav-auth" style="display:flex;align-items:center;gap:10px;">
      <?php if (isGuestLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/guest/dashboard.php" class="btn btn-outline btn-sm">My Account</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/guest/login.php" class="btn btn-outline btn-sm">Sign In</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/public/rooms.php" class="btn btn-primary btn-sm">Book Now</a>
    </div>
    <!-- Hamburger (shown on mobile) -->
    <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<script>
(function(){
  var btn    = document.getElementById('navToggle');
  var header = document.getElementById('siteHeader');
  var open   = false;
  if (!btn) return;
  btn.addEventListener('click', function(){
    open = !open;
    document.body.classList.toggle('nav-mobile-open', open);
    btn.setAttribute('aria-expanded', open);
    btn.innerHTML = open
      ? '<span style="transform:rotate(45deg) translate(5px,5px)"></span><span style="opacity:0"></span><span style="transform:rotate(-45deg) translate(5px,-5px)"></span>'
      : '<span></span><span></span><span></span>';
  });
  // Close when a nav link is clicked
  document.querySelectorAll('.nav-links a').forEach(function(a){
    a.addEventListener('click', function(){
      open = false;
      document.body.classList.remove('nav-mobile-open');
      btn.setAttribute('aria-expanded', 'false');
      btn.innerHTML = '<span></span><span></span><span></span>';
    });
  });
})();
</script>

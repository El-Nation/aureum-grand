<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="<?= BASE_URL ?>/public/index.php" class="logo" style="display:flex; align-items:center;"><img src="<?= BASE_URL ?>/assets/images/logo.jpeg" alt="Aureum Grand" style="height: 40px; margin-right: 10px;"></a>
        <p style="margin-top:16px;max-width:32ch;font-size:0.9rem;">12 Victoria Island Boulevard, Lagos, Nigeria. A five-star residence for the discerning traveller.</p>
      </div>
      <div>
        <h4>Explore</h4>
        <ul>
          <li><a href="<?= BASE_URL ?>/public/rooms.php">Rooms &amp; Suites</a></li>
          <li><a href="<?= BASE_URL ?>/public/index.php#services">Guest Services</a></li>
          <li><a href="<?= BASE_URL ?>/public/index.php#loyalty">Loyalty Program</a></li>
          <li><a href="<?= BASE_URL ?>/public/contact.php">Contact</a></li>
        </ul>
      </div>
      <div>
        <h4>Account</h4>
        <ul>
          <li><a href="<?= BASE_URL ?>/guest/login.php">Sign In</a></li>
          <li><a href="<?= BASE_URL ?>/guest/register.php">Create Account</a></li>
          <li><a href="<?= BASE_URL ?>/admin/login.php">Staff Portal</a></li>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul>
          <li>07066784058</li>
          <li>eghedestiny10@gmail.com</li>
          <li>Concierge available 24/7</li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> Aureum Grand Hotel. All rights reserved.</span>
      <span>Built on the Aureum Hospitality Platform</span>
    </div>
  </div>
</footer>
<?php require_once __DIR__ . '/concierge-widget.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>

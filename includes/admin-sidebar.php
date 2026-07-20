<?php
// includes/admin-sidebar.php — role-aware sidebar for the staff dashboard
$role = $_SESSION['staff_role'] ?? 'front_desk';
$currentPage = basename($_SERVER['PHP_SELF']);

// Define which roles see which nav items.
$navItems = [
    ['href' => 'index.php',          'label' => 'Overview',        'icon' => 'grid',     'roles' => ['administrator','general_manager','front_desk','housekeeping','concierge','revenue_manager','maintenance']],
    ['href' => 'reservations.php',   'label' => 'Reservations',    'icon' => 'calendar', 'roles' => ['administrator','general_manager','front_desk']],
    ['href' => 'rooms.php',          'label' => 'Rooms & Rates',   'icon' => 'bed',      'roles' => ['administrator','general_manager','revenue_manager']],
    ['href' => 'housekeeping.php',   'label' => 'Housekeeping',    'icon' => 'sparkle',  'roles' => ['administrator','general_manager','housekeeping','front_desk']],
    ['href' => 'maintenance.php',    'label' => 'Maintenance',     'icon' => 'wrench',   'roles' => ['administrator','general_manager','maintenance']],
    ['href' => 'services.php',       'label' => 'Guest Services',  'icon' => 'bell',     'roles' => ['administrator','general_manager','concierge','front_desk']],
    ['href' => 'revenue.php',        'label' => 'Revenue & Pricing','icon' => 'chart',   'roles' => ['administrator','general_manager','revenue_manager']],
    ['href' => 'analytics.php',      'label' => 'Analytics',       'icon' => 'bar',      'roles' => ['administrator','general_manager','revenue_manager']],
    ['href' => 'guests.php',         'label' => 'Guests',          'icon' => 'user',     'roles' => ['administrator','general_manager','front_desk','concierge']],
    ['href' => 'staff.php',          'label' => 'Staff & Roles',   'icon' => 'users',    'roles' => ['administrator']],
    ['href' => 'integrations.php',   'label' => 'Integrations',    'icon' => 'plug',     'roles' => ['administrator']],
    ['href' => 'ota-sync.php',       'label' => 'OTA Sync',        'icon' => 'plug',     'roles' => ['administrator','general_manager','revenue_manager']],
    ['href' => 'activity-log.php',   'label' => 'Activity Log',    'icon' => 'shield',   'roles' => ['administrator','general_manager']],
    ['href' => 'two-factor-setup.php','label' => 'Security (2FA)', 'icon' => 'lock',     'roles' => ['administrator','general_manager','front_desk','housekeeping','concierge','revenue_manager','maintenance']],
];

$icons = [
  'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
  'bed' => '<path d="M3 18v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7M3 18h18M3 18v2M21 18v2M3 13h18"/>',
  'sparkle' => '<path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/>',
  'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.77z"/>',
  'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
  'chart' => '<path d="M3 3v18h18"/><path d="M18 9l-5 5-3-3-4 4"/>',
  'bar' => '<rect x="4" y="10" width="3" height="10"/><rect x="10.5" y="6" width="3" height="14"/><rect x="17" y="13" width="3" height="7"/>',
  'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0 1 7-7h2a7 7 0 0 1 7 7v1"/>',
  'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20v-1a6 6 0 0 1 6-6h1a6 6 0 0 1 6 6v1"/><circle cx="17.5" cy="8.5" r="2.7"/>',
  'plug' => '<path d="M9 7V3M15 7V3M7 10h10v3a5 5 0 0 1-10 0v-3z"/><path d="M9 21v-3M15 21v-3"/>',
  'shield' => '<path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/>',
  'lock' => '<rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
];

function svgIcon($name, $icons) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg>';
}

$roleLabels = [
    'administrator'   => 'Administrator',
    'general_manager' => 'General Manager',
    'front_desk'      => 'Front Desk',
    'housekeeping'    => 'Housekeeping',
    'concierge'       => 'Concierge',
    'revenue_manager' => 'Revenue Manager',
    'maintenance'     => 'Maintenance Team',
];
?>
<aside class="dash-sidebar" id="dashSidebar">
  <div class="dash-logo"><span style="color:var(--brass-light);font-style:italic;">Aureum</span> Console</div>
  <div class="dash-role-badge"><?= sanitize($roleLabels[$role] ?? $role) ?></div>

  <nav class="dash-nav">
    <?php foreach ($navItems as $item):
        if (!in_array($role, $item['roles'])) continue;
        $isActive = ($currentPage === $item['href']);
    ?>
      <a href="<?= $item['href'] ?>" class="<?= $isActive ? 'active' : '' ?>">
        <?= svgIcon($item['icon'], $icons) ?>
        <span><?= $item['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="dash-user">
    <div class="dash-user-avatar"><?= strtoupper(substr($_SESSION['staff_name'] ?? 'S', 0, 1)) ?></div>
    <div>
      <div class="dash-user-name"><?= sanitize($_SESSION['staff_name'] ?? 'Staff') ?></div>
      <div class="dash-user-mail"><a href="logout.php" style="color:rgba(246,243,236,0.5)">Sign out</a></div>
    </div>
  </div>
</aside>

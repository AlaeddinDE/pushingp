<?php
if (!isset($user)) {
    require_once __DIR__ . '/auth.php';
}

$adminActive = $adminActive ?? 'dashboard';
$adminSubnav = isset($adminSubnav) && is_array($adminSubnav) ? $adminSubnav : [];
$showSidebarToggle = $showSidebarToggle ?? false;

$mainLinks = [
    'dashboard' => ['label' => 'Dashboard', 'href' => '/admin/index.php'],
    'kasse'     => ['label' => 'Kasse', 'href' => '/admin/index.php#transactions'],
    'shifts'    => ['label' => 'Schichten', 'href' => '/admin/index.php#shifts'],
    'members'   => ['label' => 'Mitglieder', 'href' => '/admin/users.php'],
    'board'     => ['label' => 'Board', 'href' => '/admin/index.php#admin-board'],
];
?>
<header class="admin-topbar">
  <div class="admin-topbar__inner">
    <?php if ($showSidebarToggle): ?>
      <button id="toggleSidebar" class="admin-burger" type="button" aria-label="Navigation ein- oder ausblenden">
        <span></span>
        <span></span>
        <span></span>
      </button>
    <?php endif; ?>
    <a href="/admin/index.php" class="admin-brand" aria-label="Zurück zum Admin-Dashboard">
      <span class="admin-brand__logo">P</span>
      <span class="admin-brand__text">
        <span class="admin-brand__title">Pushing P</span>
        <span class="admin-brand__subtitle">Admin</span>
      </span>
    </a>
    <nav class="admin-nav" aria-label="Admin Navigation">
      <?php foreach ($mainLinks as $key => $link): $active = $adminActive === $key; ?>
        <a href="<?= htmlspecialchars($link['href']) ?>"
           class="admin-nav__link<?= $active ? ' admin-nav__link--active' : '' ?>">
          <?= htmlspecialchars($link['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-actions">
      <span class="admin-actions__user">👋 <?= htmlspecialchars($user) ?></span>
      <a href="/" class="admin-actions__btn">Start</a>
      <a href="/logout.php" class="admin-actions__btn admin-actions__btn--danger">Logout</a>
    </div>
  </div>
  <?php if (!empty($adminSubnav)): ?>
    <div class="admin-subnav" aria-label="Bereichsnavigation">
      <?php foreach ($adminSubnav as $item): ?>
        <a href="<?= htmlspecialchars($item['href'] ?? '#') ?>" class="admin-subnav__chip">
          <?= htmlspecialchars($item['label'] ?? '') ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</header>

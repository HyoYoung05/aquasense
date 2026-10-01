<?php
$activeNav = $activeNav ?? 'dashboard';
$navLink = static function(string $key,string $symbol,string $label,string $path) use($activeNav): string {
    $active=$activeNav===$key;
    return '<a class="nav-item'.($active?' active':'').'" href="'.e(url($path)).'"'.($active?' aria-current="page"':'').'>'.icon($symbol).'<span>'.e($label).'</span>'.($active?'<span class="nav-active-dot"></span>':'').'</a>';
};
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
<a class="brand" href="<?= e(url('admin/dashboard.php')) ?>"><span class="brand-symbol"><?= icon('drop') ?></span><span>AQUASENSE<span class="brand-plus">+</span></span></a>
<div class="workspace-label"><span class="workspace-mark">SA</span><div><strong>San Antonio</strong><span>Barangay workspace</span></div></div>
<nav aria-label="Primary"><p class="nav-label">OVERVIEW</p>
<?= $navLink('dashboard','dashboard','Dashboard','admin/dashboard.php') ?>
<p class="nav-label">ADMINISTRATIVE CORE</p>
<?= $navLink('establishments','store','Establishments','admin/establishments.php') ?>
<?= $navLink('grease-traps','activity','Grease Traps','admin/grease-traps.php') ?>
<?= $navLink('devices','device','Devices','admin/devices.php') ?>
<p class="nav-label">OPERATIONS</p>
<?= $navLink('monitoring','activity','Monitoring','admin/monitoring.php') ?>
<?= $navLink('alerts','bell','Alerts','admin/alerts.php') ?>
<?= $navLink('oil-surrenders','oil','Oil Surrenders','admin/oil-surrenders.php') ?>
<?= $navLink('incentives','gift','Incentives','admin/incentives.php') ?>
<?= $navLink('compliance-ledger','ledger','Compliance Ledger','admin/compliance-ledger.php') ?>
<?= $navLink('reports','chart','Reports','admin/placeholder.php?module=reports') ?>
<?php if($user['role_slug']==='administrator'): ?><p class="nav-label">ADMINISTRATION</p><?= $navLink('users','users','Users','admin/placeholder.php?module=users') ?><?= $navLink('settings','settings','Alert Settings','admin/settings.php') ?><?php endif; ?>
</nav><div class="sidebar-bottom"><div class="program-note"><?= icon('leaf') ?><strong>Sana Oil incentives</strong><p>Approved surrenders, rice rewards, and compliance history.</p></div><div class="sidebar-version"><span class="status-dot"></span> Phase 6 <span>v<?= e(APP_VERSION) ?></span></div></div>
</aside><button type="button" class="sidebar-backdrop" data-menu-close aria-label="Close navigation" tabindex="-1"></button>

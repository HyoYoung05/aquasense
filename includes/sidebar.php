<?php
$activeNav = $activeNav ?? 'dashboard';
$navLink = static function(string $key,string $symbol,string $label,string $path) use($activeNav): string {
    $active=$activeNav===$key;
    return '<a class="nav-item'.($active?' active':'').'" href="'.e(url($path)).'"'.($active?' aria-current="page"':'').'>'.icon($symbol).'<span>'.e($label).'</span>'.($active?'<span class="nav-active-dot"></span>':'').'</a>';
};
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
<a class="brand" href="<?= e(url('admin/dashboard.php')) ?>"><span class="brand-symbol"><img class="official-logo" src="<?= e(url('assets/images/barangay-san-antonio-logo.png')) ?>" alt="" width="512" height="512" decoding="async"></span><span>AQUASENSE<span class="brand-plus">+</span></span></a>
<div class="workspace-label"><span class="workspace-mark"><img class="official-logo" src="<?= e(url('assets/images/barangay-san-antonio-logo.png')) ?>" alt="" width="512" height="512" loading="lazy" decoding="async"></span><div><strong>San Antonio</strong><span>Barangay workspace</span></div></div>
<nav aria-label="Primary"><p class="nav-label">OVERVIEW</p>
<?= $navLink('dashboard','dashboard','Dashboard','admin/dashboard.php') ?>
<?php if(is_system_admin($user)): ?>
<p class="nav-label">ADMINISTRATIVE CORE</p>
<?= $navLink('establishments','store','Establishments','admin/establishments.php') ?>
<?= $navLink('grease-traps','activity','Grease Traps','admin/grease-traps.php') ?>
<?= $navLink('devices','device','Devices','admin/devices.php') ?>
<?php endif; ?>
<p class="nav-label">OPERATIONS</p>
<?= $navLink('monitoring','activity','Monitoring','admin/monitoring.php') ?>
<?= $navLink('alerts','bell','Alerts','admin/alerts.php') ?>
<?php if(can_use_device_support($user)): ?><?= $navLink('device-support','mail',is_system_admin($user)?'Support Inbox':'Device Support','admin/device-support.php') ?><?php endif; ?>
<?= $navLink('oil-surrenders','oil','Oil Surrenders','admin/oil-surrenders.php') ?>
<?= $navLink('incentives','gift','Incentives','admin/incentives.php') ?>
<?= $navLink('compliance-ledger','ledger','Compliance Ledger','admin/compliance-ledger.php') ?>
<?= $navLink('reports','chart','Reports','admin/reports.php') ?>
<?php if(is_system_admin($user)): ?><p class="nav-label">ADMINISTRATION</p><?= $navLink('users','users','User Accounts','admin/users.php') ?><?= $navLink('settings','settings','Alert Settings','admin/settings.php') ?><?php endif; ?>
</nav><div class="sidebar-bottom"><div class="program-note"><?= icon('shield') ?><strong>Release monitoring</strong><p>Operational records, alerts, compliance, and traceable exports.</p></div><div class="sidebar-version"><span class="status-dot"></span> Production release <span>v<?= e(APP_VERSION) ?></span></div></div>
</aside><button type="button" class="sidebar-backdrop" data-menu-close aria-label="Close navigation" tabindex="-1"></button>

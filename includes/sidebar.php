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
<p class="nav-label">UPCOMING OPERATIONS</p>
<?php foreach([['monitoring','activity','Monitoring'],['alerts','bell','Alerts'],['oil-surrenders','oil','Oil Surrenders'],['incentives','gift','Incentives'],['compliance-ledger','ledger','Compliance Ledger'],['reports','chart','Reports']] as [$key,$symbol,$label]): ?><?= $navLink($key,$symbol,$label,'admin/placeholder.php?module='.$key) ?><?php endforeach; ?>
<?php if($user['role_slug']==='administrator'): ?><p class="nav-label">ADMINISTRATION</p><?= $navLink('users','users','Users','admin/placeholder.php?module=users') ?><?= $navLink('settings','settings','Settings','admin/placeholder.php?module=settings') ?><?php endif; ?>
</nav><div class="sidebar-bottom"><div class="program-note"><?= icon('leaf') ?><strong>Administrative core</strong><p>Registration and assignment records for cleaner waterways.</p></div><div class="sidebar-version"><span class="status-dot"></span> Phase 2 <span>v<?= e(APP_VERSION) ?></span></div></div>
</aside><button type="button" class="sidebar-backdrop" data-menu-close aria-label="Close navigation" tabindex="-1"></button>

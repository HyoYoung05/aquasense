<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="brand" href="<?= e(url('admin/dashboard.php')) ?>"><span class="brand-symbol"><?= icon('drop') ?></span><span>AQUASENSE<span class="brand-plus">+</span></span></a>
    <div class="workspace-label"><span class="workspace-mark">SA</span><div><strong>San Antonio</strong><span>Barangay workspace</span></div></div>
    <nav aria-label="Primary">
        <p class="nav-label">OVERVIEW</p>
        <a class="nav-item active" href="<?= e(url('admin/dashboard.php')) ?>" aria-current="page"><?= icon('dashboard') ?><span>Dashboard</span><span class="nav-active-dot"></span></a>
        <p class="nav-label">OPERATIONS</p>
        <?php foreach ([['store', 'Establishments'], ['activity', 'Monitoring'], ['bell', 'Alerts'], ['oil', 'Oil Surrenders'], ['gift', 'Incentives'], ['ledger', 'Compliance Ledger'], ['chart', 'Reports']] as [$symbol, $label]): ?>
            <span class="nav-item upcoming" aria-disabled="true"><?= icon($symbol) ?><span><?= e($label) ?></span><span class="soon">Soon</span></span>
        <?php endforeach; ?>
        <?php if ($user['role_slug'] === 'administrator'): ?>
            <p class="nav-label">ADMINISTRATION</p>
            <?php foreach ([['users', 'Users'], ['settings', 'Settings']] as [$symbol, $label]): ?>
                <span class="nav-item upcoming" aria-disabled="true"><?= icon($symbol) ?><span><?= e($label) ?></span><span class="soon">Soon</span></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom"><div class="program-note"><?= icon('leaf') ?><strong>A little care goes a long way.</strong><p>Working together for cleaner waterways.</p></div><div class="sidebar-version"><span class="status-dot"></span> Website foundation <span>v<?= e(APP_VERSION) ?></span></div></div>
</aside>
<button type="button" class="sidebar-backdrop" data-menu-close aria-label="Close navigation" tabindex="-1"></button>

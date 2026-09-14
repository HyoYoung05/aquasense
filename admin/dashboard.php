<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();
$pageTitle = 'Dashboard';
// Phase 1 shows account activity only. Operational metrics arrive in later phases.
$statement = db()->prepare("SELECT action, created_at FROM audit_logs
    WHERE user_id = ? AND action IN ('LOGIN', 'LOGOUT') ORDER BY id DESC LIMIT 5");
$statement->execute([$user['id']]);
$activity = $statement->fetchAll();
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> Overview</div><h1>Dashboard</h1><p>Your community’s environmental workspace.</p></div><div class="date-label"><?= icon('clock') ?> <?= e(date('l, F j, Y')) ?></div></div>

<section class="welcome-banner" aria-labelledby="welcome-title">
    <div class="banner-copy"><span class="eyebrow light">AQUASENSE+ · BARANGAY SAN ANTONIO</span><h2 id="welcome-title">A cleaner community<br>starts with better care.</h2><p>Welcome, <?= e(explode(' ', $user['full_name'])[0]) ?>. Your administrative workspace is ready.<br>Let’s build better waste oil management, together.</p><span class="banner-tag"><?= icon('check') ?> Account access enabled</span></div>
    <div class="banner-art" aria-hidden="true"><div class="banner-ring ring-outer"></div><div class="banner-ring ring-inner"></div><div class="banner-droplet"><?= icon('drop') ?></div><span class="banner-leaf"><?= icon('leaf') ?></span><span class="banner-spark spark-one">+</span><span class="banner-spark spark-two">+</span><div class="water-line line-one"></div><div class="water-line line-two"></div></div>
</section>

<div class="phase-notice"><?= icon('info') ?><p><strong>We’re setting things up.</strong> This is the website foundation. Monitoring, alerts, and oil surrender tools will become available in upcoming phases.</p><span class="tag">Phase 1 of 8</span></div>

<section class="module-cards" aria-label="Upcoming services">
    <?php foreach ([['store', 'Establishments', 'A directory of registered food businesses.', 'Phase 2', 'mint'], ['activity', 'Grease trap monitoring', 'A clearer view of waste levels and conditions.', 'Phase 3', 'blue'], ['oil', 'Oil surrenders', 'Review collected oil and photo evidence.', 'Phase 5', 'sand'], ['gift', 'Sana Oil incentives', 'Track rice rewards for responsible disposal.', 'Phase 6', 'lavender']] as [$symbol, $title, $description, $phase, $color]): ?>
        <article class="module-card"><div class="card-top"><span class="feature-icon <?= e($color) ?>"><?= icon($symbol) ?></span><span class="tag subtle"><?= e($phase) ?></span></div><h3><?= e($title) ?></h3><p><?= e($description) ?></p><div class="module-card-bottom"><span class="small-dot"></span> Coming soon</div></article>
    <?php endforeach; ?>
</section>

<div class="dashboard-columns">
    <section class="panel activity-panel" aria-labelledby="activity-title">
        <div class="panel-heading"><div><h2 id="activity-title">Your recent activity</h2><p>Sign-ins and sign-outs for your account.</p></div><span class="feature-icon compact mint"><?= icon('clock') ?></span></div>
        <div class="table-scroll"><table><thead><tr><th scope="col">Activity</th><th scope="col">Date &amp; time <span class="table-timezone">(Manila)</span></th><th scope="col">Status</th></tr></thead><tbody>
        <?php foreach ($activity as $event): ?><tr><td><span class="table-event"><?= icon($event['action'] === 'LOGIN' ? 'shield' : 'logout') ?> <?= $event['action'] === 'LOGIN' ? 'Signed in to workspace' : 'Signed out of workspace' ?></span></td><td class="muted"><?= e(display_date($event['created_at'])) ?></td><td><span class="badge-success">Successful</span></td></tr><?php endforeach; ?>
        <?php if (!$activity): ?><tr><td colspan="3" class="empty-state">Your account activity will appear here.</td></tr><?php endif; ?>
        </tbody></table></div>
        <div class="panel-footnote"><?= icon('shield') ?> Your account activity is recorded for accountability.</div>
    </section>

<section class="panel account-panel" aria-labelledby="account-title"><div class="panel-heading"><div><h2 id="account-title">Your account</h2><p>Authorized Barangay personnel</p></div></div><div class="account-identity"><span class="avatar avatar-large"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span><div><strong><?= e($user['full_name']) ?></strong><span><?= e($user['email']) ?></span></div></div><dl class="account-details"><div><dt>Role</dt><dd><?= e($user['role_name']) ?></dd></div><div><dt>Account status</dt><dd><span class="badge-success"><span class="status-dot"></span> Active</span></dd></div><div><dt>Session</dt><dd><?= e((string) ((int) $config['session_timeout'] / 60)) ?>-minute inactivity limit</dd></div></dl><div class="account-help"><?= icon('info') ?><p>Need account assistance? Contact your Barangay administrator.</p></div></section>
</div>

<section class="next-section"><div class="next-icon"><?= icon('leaf') ?></div><div><span class="eyebrow">WHAT’S NEXT</span><h2>A home for every participating establishment.</h2><p>The next phase adds establishment registration, grease trap records, and device assignments.</p></div><span class="tag">Phase 2 · Administrative core</span></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

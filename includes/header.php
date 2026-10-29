<?php
require_once __DIR__ . '/alert-engine.php';
$notificationSummary = phase4_unresolved_alert_summary(db());
$notificationCount = $notificationSummary['total'];
$notificationTitle = $notificationCount === 0
    ? 'No unresolved alerts'
    : $notificationCount . ' unresolved alert' . ($notificationCount === 1 ? '' : 's');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#103f3b">
    <link rel="icon" type="image/png" href="<?= e(url('assets/images/barangay-san-antonio-logo.png')) ?>">
    <title><?= e($pageTitle) ?> · AQUASENSE+</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(APP_VERSION) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>?v=<?= e(APP_VERSION) ?>" defer></script>
    <?php if (!empty($pageScript)): ?><script src="<?= e(url($pageScript)) ?>?v=<?= e(APP_VERSION) ?>" defer></script><?php endif; ?>
</head>
<body class="dashboard-page">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="workspace">
    <header class="topbar">
        <div class="topbar-left"><button type="button" class="icon-button mobile-menu" data-menu-toggle aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button><span class="topbar-brand">AQUASENSE+</span><span class="topbar-divider"></span><span class="muted">Barangay San Antonio</span></div>
        <div class="topbar-right"><a class="notification-status<?= $notificationSummary['critical'] > 0 ? ' has-critical' : '' ?>" href="<?= e(url('admin/alerts.php')) ?>" title="<?= e($notificationTitle) ?>" aria-label="<?= e($notificationTitle) ?>" data-notification-status data-summary-url="<?= e(url('api/admin/notification-summary.php')) ?>"><?= icon('bell') ?><span class="notification-count" data-notification-count<?= $notificationCount === 0 ? ' hidden' : '' ?>><?= e($notificationCount > 99 ? '99+' : (string) $notificationCount) ?></span><span class="status-dot muted-dot" data-notification-empty<?= $notificationCount > 0 ? ' hidden' : '' ?>></span><span class="sr-only" data-notification-label><?= e($notificationTitle) ?></span></a><span class="topbar-divider"></span><span class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span><div class="topbar-user"><strong><?= e($user['full_name']) ?></strong><span><?= e($user['role_name']) ?></span></div><form action="<?= e(url('public/logout.php')) ?>" method="post"><?= csrf_field() ?><button class="icon-button" type="submit" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></button></form></div>
    </header>
    <main id="main-content" class="main-content">

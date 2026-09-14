<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#103f3b">
    <title><?= e($pageTitle) ?> · AQUASENSE+</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= e(APP_VERSION) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="dashboard-page">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="workspace">
    <header class="topbar">
        <div class="topbar-left"><button type="button" class="icon-button mobile-menu" data-menu-toggle aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button><span class="topbar-brand">AQUASENSE+</span><span class="topbar-divider"></span><span class="muted">Barangay San Antonio</span></div>
        <div class="topbar-right"><span class="notification-status" title="Notifications will be available with the alerts module"><?= icon('bell') ?><span class="status-dot muted-dot"></span><span class="sr-only">Notifications not enabled</span></span><span class="topbar-divider"></span><span class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span><div class="topbar-user"><strong><?= e($user['full_name']) ?></strong><span><?= e($user['role_name']) ?></span></div><form action="<?= e(url('public/logout.php')) ?>" method="post"><?= csrf_field() ?><button class="icon-button" type="submit" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></button></form></div>
    </header>
    <main id="main-content" class="main-content">

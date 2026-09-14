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
<body class="auth-page <?= e($authPageClass ?? '') ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="auth-page-header">
    <a class="brand brand-light" href="<?= e(url()) ?>"><span class="brand-symbol"><?= icon('drop') ?></span><span>AQUASENSE<span class="brand-plus">+</span></span></a>
    <span class="tag">Administrative website</span>
    <span class="auth-environment">Local development</span>
</header>
<div class="auth-shell">
    <div class="water-art" aria-hidden="true">
        <div class="water-drop"><?= icon('drop') ?></div>
    </div>
    <aside class="auth-story" aria-label="About AQUASENSE+">
        <div class="story-copy">
            <span class="eyebrow light">CLEANER WATER. STRONGER COMMUNITIES.</span>
            <h1>Small actions.<br>A cleaner<br><span>San Antonio.</span></h1>
            <p>A shared workspace for responsible waste oil management and a healthier barangay.</p>
        </div>
    </aside>
    <div class="auth-main">
        <main id="main-content" class="auth-form-area">

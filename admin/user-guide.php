<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_staff();
$pageTitle = 'Help and guides';
$activeNav = 'account-settings';
$canManage = can_manage_operations($user);
$roleNames = [
    'system_admin' => 'System Admin',
    'administrator' => 'Barangay Admin',
    'environmental_staff' => 'Environmental Management',
];
$roleName = $roleNames[$user['role_slug']] ?? $user['role_name'];
$accessDescription = $canManage
    ? 'You can review records and use the action buttons allowed for your account.'
    : 'You can view readings, alerts, oil surrender records, incentives, and reports. Ask your Barangay Admin when a record needs to be changed.';
$topics = [
    [
        'id' => 'getting-started',
        'title' => 'Sign in and use the menu',
        'steps' => [
            'Open the website and enter your email address or contact number and password. Select Sign in.',
            'Check your name and role at the top of the page.',
            'Choose a page from the left menu. On a phone or small screen, use the menu button to open it.',
            'If your sign-in form expires, enter your password again on the refreshed form. If too many attempts are shown, wait for the time given.',
            'Ask your Barangay Admin for help with a forgotten password. Keep your password private.',
            'Open Settings to update your email or contact number, or change your password. Enter your current password to save either change.',
            'Use the sign-out button at the top when you finish on a shared computer.',
        ],
    ],
    [
        'id' => 'monitoring',
        'title' => 'Dashboard and grease trap readings',
        'steps' => [
            'Open Dashboard to see the totals and records that need attention.',
            'Open Monitoring to view the latest readings for each establishment and grease trap.',
            'Check the establishment, trap, assigned device, and time of the last reading together.',
            'Select Details beside a trap to open its charts and reading history.',
            'Choose the Period. For a custom range, choose From and To dates, then select Apply.',
            'Use Previous, Next, or a page number to view another history page. Swipe or drag the page numbers sideways when they extend beyond the screen.',
            'Read the unit beside each value. ADC (raw) is a sensor value that still needs calibration before it can be used as a calibrated measurement.',
            'If readings stop changing, ask the owner to check the device power and Wi-Fi. Record the device name and last reading time when asking for help.',
        ],
    ],
    [
        'id' => 'alerts',
        'title' => 'Check and handle an alert',
        'steps' => [
            'Open Alerts from the menu, or use the bell at the top of the page.',
            'Choose the status, severity, establishment, device, or dates you need, then select Filter.',
            'Select View beside an alert. Read the trap name, message, value, and time.',
            'Check the actual condition with the owner or the person handling the device.',
            $canManage
                ? 'Select Acknowledge when the alert is being handled. After the issue is fixed, enter a clear resolution note and select Resolve alert.'
                : 'Tell the Barangay Admin what you found so they can acknowledge or resolve the alert.',
            'Check for new readings after the work is done. A new alert may appear if the measured condition reaches the warning limit again.',
        ],
    ],
    [
        'id' => 'oil-surrenders',
        'title' => 'Review an oil surrender',
        'steps' => [
            'Open Oil Surrenders and select the record you need to review.',
            'Check the establishment, owner, submitted quantity, unit, and date.',
            'Open the photo evidence. Use Download photo when you need a copy.',
            'Compare the details and photo with the oil handed over. Review the available sensor history as supporting information.',
            $canManage
                ? 'Select Start review when available. Add a clear note, then use Approve or Reject after checking the submission.'
                : 'Send any concern to your Barangay Admin so they can review and decide on the submission.',
            'Check the status and review history to confirm the recorded decision.',
        ],
    ],
    [
        'id' => 'incentives',
        'title' => 'Check rice incentives and distribution',
        'steps' => [
            'Open Incentives to see approved oil surrenders waiting for a reward and past transactions.',
            'Check the establishment, oil quantity, unit, and applied rice rule.',
            $canManage
                ? 'Select Process incentive for an eligible approved surrender. If a rule is missing, ask the System Admin to set one.'
                : 'Ask the Barangay Admin about records waiting for processing or distribution.',
            'Open the incentive record to check the rice quantity and distribution status.',
            $canManage
                ? 'After the rice has been handed to the correct owner, add the distribution notes, check the confirmation box, and select Confirm distribution.'
                : 'Use the recorded distribution status and date when checking whether the rice has been handed over.',
        ],
    ],
    [
        'id' => 'reports',
        'title' => 'Find records and download or print reports',
        'steps' => [
            'Open Reports and choose the report you need.',
            'Set the dates and other filters, then select Apply filters.',
            'For Establishment detail, choose an establishment before applying the filters.',
            'Check that the names, dates, and totals are correct before exporting.',
            'Use CSV for a spreadsheet copy, PDF for a document copy, or Print for a paper copy.',
            'In the print window, choose portrait or landscape and check the preview before printing.',
            'Open Compliance Ledger when you need to trace a recorded review, alert action, or incentive transaction.',
        ],
    ],
    [
        'id' => 'troubleshooting',
        'title' => 'Common problems and what to do',
        'steps' => [
            'If a value says No data, Not available, or Not connected, check the last reading time and ask for a device check.',
            'If the page stops updating, check your internet connection and reload the page.',
            'If an old layout appears after a website update, press Ctrl + F5 on a computer. On a phone, reload the website.',
            'If a button is missing, check your role at the top of the page and ask the Barangay Admin about access.',
            'When reporting a problem, include the page name, establishment, device, time, and exact message. Keep account passwords private.',
        ],
    ],
];
if (can_manage_administration($user)) {
    $topics[] = [
        'id' => 'administration',
        'title' => 'Manage user accounts and alert settings',
        'steps' => [
            'Open User Accounts, then select Create account.',
            'Enter the full name, contact number, role, and temporary password. Enter an email address if one is available.',
            'For a Carinderia Owner, select the correct establishment. Check the owner name and contact number before saving.',
            'Give the temporary password directly to the user. Owners can change it in their app Settings.',
            'Use Edit to correct an account. Choose Active or Inactive from its status menu to control sign-in access.',
            'Open Alert Settings to review warning limits. Confirm the correct values with the person responsible for the device before saving.',
            'Enable turbidity and flow alerts after the sensors have been calibrated and their limits approved.',
        ],
    ];
}
if (can_use_device_support($user)) {
    $topics[] = [
        'id' => 'support',
        'title' => is_system_admin($user) ? 'Use the Support Inbox' : 'Ask the System Admin for device help',
        'steps' => is_system_admin($user) ? [
            'Open Support Inbox and select the request you want to handle.',
            'Read the device details and messages, then reply with the next steps.',
            'Update the request status as the work progresses. Record the result when the issue is resolved.',
        ] : [
            'Open Device Support and create a request.',
            'Choose the device, category, and priority. Give a short subject and describe what happened.',
            'Include the last reading time and any message shown. Send the request and check back for a reply.',
        ],
    ];
}
if (is_system_admin($user)) {
    $topics[] = [
        'id' => 'system-setup',
        'title' => 'Set up an establishment, grease trap, device, and rice rule',
        'steps' => [
            'Open Establishments and register the business and owner details.',
            'Open Grease Traps and register the trap under that establishment.',
            'Open Devices and register the device. Select the same establishment and its grease trap.',
            'Create the Owner account in User Accounts and link it to the establishment.',
            'Check Monitoring for new readings and the correct device assignment.',
            'Open Incentives, then Manage rules. Enter the oil quantity and unit, rice quantity and unit, calculation method, and effective dates.',
            'Select Active and save the rule. Choose date ranges that avoid overlap with other active rules for the same oil unit.',
        ],
    ];
}
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-heading"><div><div class="breadcrumb">Workspace <span>/</span> Help and guides</div><h1>Help and guides</h1><p>A website manual for Barangay personnel. Follow the steps for the task you need.</p></div></div>
<section class="panel section-panel personnel-guide-access" aria-label="Your access">
    <h2><?= e($roleName) ?></h2><p><?= e($accessDescription) ?></p>
</section>
<section class="panel section-panel personnel-guide">
    <div class="panel-heading"><div><h2>Quick walkthrough</h2><p>Start here when using the website for the first time.</p></div></div>
    <ol class="personnel-guide-walkthrough">
        <li>Check your name and role after signing in.</li>
        <li>Open <a href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a> for an overview.</li>
        <li>Open <a href="<?= e(url('admin/monitoring.php')) ?>">Monitoring</a> and check the latest reading time.</li>
        <li>Open <a href="<?= e(url('admin/alerts.php')) ?>">Alerts</a> and read warnings that need attention.</li>
        <li>Check <a href="<?= e(url('admin/oil-surrenders.php')) ?>">Oil Surrenders</a> and <a href="<?= e(url('admin/incentives.php')) ?>">Incentives</a> as part of your assigned work.</li>
        <li>Use <a href="<?= e(url('admin/reports.php')) ?>">Reports</a> to download or print records.</li>
        <li>Sign out when you finish on a shared computer.</li>
    </ol>
</section>
<section class="panel section-panel personnel-guide">
    <div class="panel-heading"><div><h2>User manual</h2><p>Click or tap a topic to read the steps. You can open more than one topic.</p></div></div>
    <div class="personnel-guide-topics">
        <?php foreach ($topics as $topic): ?>
        <details id="<?= e($topic['id']) ?>" class="personnel-guide-topic">
            <summary><?= e($topic['title']) ?></summary>
            <ol><?php foreach ($topic['steps'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ol>
        </details>
        <?php endforeach; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/bootstrap.php';
$user=require_staff();
$pageTitle='Help and guides';$activeNav='user-guide';
$topics=[
    'Sign in and keep your account safe'=>[
        'Use your email or phone number and password to sign in.',
        'If the form expired, enter your password again on the refreshed form.',
        'If there were too many tries, wait for the time shown. Other users have their own sign-in limit.',
        'Do not share your password. Use Sign out when you finish on a shared computer.',
    ],
    'Dashboard and Monitoring'=>[
        'Open Dashboard to see the totals. Open Monitoring to see the latest readings.',
        'Check the shop, trap, device name, and time of the last reading.',
        'Online means a device has an active assignment. It does not prove the device has power right now.',
        'No data means there is no usable reading. It does not mean zero.',
        'If updates stop, check the device power and Wi-Fi, then ask for help.',
    ],
    'Check an alert'=>[
        'Open Alerts. Choose the filters you need, then select Filter.',
        'Select View beside a warning to read its details.',
        'If your account can change alerts, mark it as checked when work starts. Mark it as resolved only after the problem is fixed.',
        'If you cannot change it, ask the Barangay Admin. A new reading can raise a warning again if the problem returns.',
    ],
    'Check an oil surrender'=>[
        'Open Oil Surrenders and open the record you want to check.',
        'Read the amount, unit, owner name, date, and photo. Compare them with the oil handed over.',
        'If your account can review records, choose the next action shown on the page and add a clear note.',
        'Approve only when the details are right. If they are wrong, explain why in the note.',
    ],
    'Rice rewards'=>[
        'Open Incentives to see approved oil waiting for a reward and past rice records.',
        'A System Admin must set a rice rule before a reward can be worked out.',
        'If your account can process a reward, check the oil amount, unit, and rule before you confirm.',
        'Mark rice as distributed only after it has been handed over. Check the owner and amount first.',
    ],
    'Reports and past records'=>[
        'Open Reports. Choose a report, dates, and other filters, then apply the filters.',
        'Check the results before you download CSV, download PDF, or print.',
        'In the print window, choose portrait or landscape to fit the report.',
        'Use Compliance Ledger to look up past changes and actions.',
    ],
];
if(can_manage_administration($user))$topics['User accounts and Alert Settings']=[
    'Open User Accounts, then Create account. Enter the name, phone number, role, and temporary password. Email is optional.',
    'For an Owner account, choose the owner\'s shop. Check the name and phone number before you save.',
    'Give the temporary password to that person privately. Ask them to change it in the app.',
    'Use Edit to correct account details. Inactive stops the person from signing in.',
    'Open Alert Settings to change warning limits. Check the values carefully before you save.',
];
if(is_system_admin($user))$topics['Set up a shop, trap, device, and rice rule']=[
    'Open Establishments and add the shop and owner details.',
    'Open Grease Traps and add the trap under the right shop.',
    'Open Devices and add the device. Choose that same shop and trap.',
    'Create an Owner account in User Accounts and link it to the shop.',
    'In Incentives, open Manage rules. Enter the oil amount, oil unit, rice amount, rice unit, and start and end dates.',
    'Choose Active and save the rule. Leave Development / Test Data off for real records. Active rules for the same oil unit must not cover the same dates.',
    'Check Monitoring to confirm new readings arrive. A saved assignment alone does not prove the device is sending readings.',
];
if(can_use_device_support($user))$topics['Ask for help with a device']=[
    'Open '.(is_system_admin($user)?'Support Inbox':'Device Support').'.',
    'Choose the device and explain what happened. Include the page name and the message shown.',
    'Check back for a reply. Never put passwords in your message.',
];
require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-heading"><div><h1>Help and guides</h1><p>Simple steps for the tools your account can use.</p></div></div>
<section class="panel section-panel"><div class="panel-heading"><h2>Quick walkthrough</h2></div><div class="form-grid"><ol class="form-span">
<li>Sign in and check your name and role at the top.</li><li>Open Dashboard to see the latest totals.</li><li>Open Monitoring and check when each trap last sent a reading.</li><li>Open Alerts and read any warning that needs help.</li><li>Check Oil Surrenders and Incentives if these are part of your work.</li><li>Open Reports when you need to save or print records.</li><li>Use Sign out when you finish.</li>
</ol></div></section>
<section class="panel section-panel"><div class="panel-heading"><div><h2>User manual</h2><p>Click or tap a topic to open it. Some actions depend on your role.</p></div></div><div class="form-grid">
<?php foreach($topics as $title=>$steps): ?><details class="form-span"><summary><?= e($title) ?></summary><ol><?php foreach($steps as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ol></details><?php endforeach; ?>
</div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>

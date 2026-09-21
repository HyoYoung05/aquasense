<?php
// Copy to local.php if your XAMPP credentials or installation path differ.
// local.php is excluded from Git and blocked from HTTP access.
return [
    'environment' => 'development',
    'db_host' => 'YOUR_DEVELOPMENT_DB_HOST',
    'db_port' => '3306',
    'db_name' => 'aquasense',
    'db_user' => 'root',
    'db_password' => '',
    'base_path' => '/YOUR_LOCAL_PROJECT_PATH',
    'storage_path' => dirname(__DIR__) . '/uploads',
    'log_path' => dirname(__DIR__) . '/logs/php-error.log',
    'test_base_url' => 'http://YOUR_DEVELOPMENT_WEB_HOST/YOUR_LOCAL_PROJECT_PATH',
    // Development browser preview only. Set false for deployment.
    'mobile_allow_local_web_preview' => true,
];

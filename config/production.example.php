<?php
// Copy to local.php only when the host cannot provide environment variables.
// local.php is ignored by Git. Replace every placeholder on the server.
return [
    'environment' => 'production',
    'base_path' => '',
    'db_host' => 'YOUR_PRODUCTION_DB_HOST',
    'db_port' => '3306',
    'db_name' => 'YOUR_PRODUCTION_DB_NAME',
    'db_user' => 'YOUR_PRODUCTION_DB_USER',
    'db_password' => 'REPLACE_WITH_A_STRONG_SECRET',
    // Prefer a private directory outside the web document root.
    'storage_path' => '/ABSOLUTE/PRIVATE/PATH/aquasense-uploads',
    'log_path' => '/ABSOLUTE/PRIVATE/PATH/aquasense.log',
    // Set only when TLS ends at a reverse proxy you control.
    'trusted_proxy_ips' => [],
    // Native Flutter apps do not need CORS. Add exact HTTPS origins only for Flutter web.
    'mobile_web_origins' => [],
    'mobile_allow_local_web_preview' => false,
];

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
redirect(current_user() ? 'admin/dashboard.php' : 'public/login.php');

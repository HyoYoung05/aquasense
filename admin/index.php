<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_staff();
redirect('admin/dashboard.php');

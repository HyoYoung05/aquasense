<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection = null;
    global $config;
    if ($connection === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'], $config['db_port'], $config['db_name']);
        $connection = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // All stored timestamps are UTC; format them in Manila time at the UI boundary.
        $connection->exec("SET time_zone = '+00:00'");
    }
    return $connection;
}

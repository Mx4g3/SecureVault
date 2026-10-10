<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) return $connection;
    $local = is_file(__DIR__ . '/../backend/config/local.php') ? require __DIR__ . '/../backend/config/local.php' : [];
    $setting = static fn(string $name, string $fallback): string => (string)(getenv($name) ?: ($local[$name] ?? $fallback));
    $host = $setting('SV_DB_HOST', '127.0.0.1');
    $port = $setting('SV_DB_PORT', '3306');
    $name = $setting('SV_DB_NAME', 'securevault_db');
    $connection = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $setting('SV_DB_USER', 'securevault_app'), $setting('SV_DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone = '+00:00'");
    return $connection;
}

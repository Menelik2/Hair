<?php
declare(strict_types=1);

$dbName = getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: 'hair_queue');
$dbUser = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root');
$dbPass = getenv('DB_PASSWORD');
if ($dbPass === false) {
    $dbPass = getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '';
}

return [
    'driver'   => getenv('DB_DRIVER') ?: 'mysql',
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => (int)(getenv('DB_PORT') ?: 3306),
    'database' => $dbName,
    'username' => $dbUser,
    'password' => $dbPass,
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ],
];

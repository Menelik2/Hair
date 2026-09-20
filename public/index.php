<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

$routes = [
    '/'             => 'queue.php',
    '/queue'        => 'queue.php',
    '/ticket'       => 'ticket.php',
    '/board'        => 'live-board.php',
    '/live'         => 'live-board.php',
    '/station'      => 'stylist/station.php',
    '/admin'        => 'admin/dashboard.php',
    '/dashboard'    => 'admin/dashboard.php',
    '/login'        => 'login.php',
    '/appointments' => 'appointments.php',
    '/profile'      => 'profile.php',
    '/book'         => 'appointments.php',
];

if (isset($routes[$uri])) {
    require __DIR__ . '/' . $routes[$uri];
    exit;
}
header('Location: /queue.php');
exit;

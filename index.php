<?php
/**
 * Root router for hosts where document root is project root (e.g. AeonFree public_html).
 */
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . '/public' . $uri;

if ($uri !== '/' && is_file($file)) {
    if (substr($file, -4) === '.php') {
        require $file;
        return;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'css' => 'text/css', 'js' => 'application/javascript',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml', 'json' => 'application/json',
        'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
    ];
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
        header('Content-Length: ' . (string)filesize($file));
        readfile($file);
        return;
    }
}

$script = ($uri === '/' || $uri === '') ? '/index.php' : $uri;
$target = __DIR__ . '/public' . $script;
if (substr($target, -4) !== '.php') {
    $target .= '.php';
}
if (is_file($target)) {
    require $target;
    return;
}

http_response_code(404);
echo '404 Not Found';

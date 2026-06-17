<?php
// PHP 8.5 changed the built-in server so that `return false` still falls back
// to the directory's index.php for missing files. We explicitly detect missing
// paths and emit 404 ourselves so security regression tests stay meaningful.
$path = '.' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (is_file($path) || is_dir($path)) {
    return false; // exists — let the built-in server handle it normally
}

http_response_code(404);
header('Content-Type: text/plain');
echo '404 Not Found';
exit;

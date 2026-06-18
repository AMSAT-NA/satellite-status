<?php
// PHP built-in server router for the test environment.
//
// After the versioned-layout restructure, frontend files live under
// frontend/v1/ and API files live under api/.  This router maps:
//   /api/...  → served normally from ./api/ (already under the document root)
//   /*        → served from ./frontend/v1/
//
// PHP resolves relative includes (include "config.php", include "../config.php")
// relative to the including file's own directory, so the duplicated
// frontend/v1/config.php and api/v1/config.php each resolve correctly once
// the file is included from its actual location.
//
// PHP 8.5 changed the built-in server so that `return false` still falls back
// to the directory's index.php for missing files. We explicitly detect missing
// paths and emit 404 ourselves so security regression tests stay meaningful.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API paths are under ./api/ which sits at the document root.
if (strpos($path, '/api') === 0) {
    $file = '.' . $path;
    if (is_file($file) || is_dir($file)) {
        return false;
    }
    http_response_code(404);
    header('Content-Type: text/plain');
    echo '404 Not Found';
    exit;
}

// Frontend paths: map to ./frontend/v1/
$frontendFile = './frontend/v1' . ($path === '/' ? '/index.php' : $path);

if (is_file($frontendFile)) {
    $ext = strtolower(pathinfo($frontendFile, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        // Normalise $_SERVER so PHP_SELF reflects the request URI, matching
        // what the built-in server sets when serving the file directly.
        $_SERVER['PHP_SELF']    = $path;
        $_SERVER['SCRIPT_NAME'] = $path;
        include $frontendFile;
    } else {
        // Static asset: output with correct Content-Type.
        static $mimeMap = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
        readfile($frontendFile);
    }
    exit;
}

if (is_dir($frontendFile)) {
    $indexFile = rtrim($frontendFile, '/') . '/index.php';
    if (is_file($indexFile)) {
        $_SERVER['PHP_SELF']    = rtrim($path, '/') . '/index.php';
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
        include $indexFile;
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain');
echo '404 Not Found';
exit;

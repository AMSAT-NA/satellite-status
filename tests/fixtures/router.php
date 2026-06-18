<?php
// PHP built-in server router for the test environment.
//
// After the versioned-layout restructure, frontend files live under
// frontend/v1/ and API files live under api/.  This router maps:
//   /api/...  → served normally from the document root (api/ is there)
//   /*        → served from frontend/v1/
//
// All file paths are anchored to this file's __DIR__ rather than the CWD
// so that chdir() calls within one request do not break the next.
//
// Before including a frontend PHP file we chdir() to its own directory.
// This is required because PHP resolves relative include paths (e.g.
// include("../config.php")) against the CWD when the including file was
// itself included by a router script, rather than served directly.

$repoRoot = realpath(__DIR__ . '/../..');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API paths: the api/ tree sits under the document root — serve normally.
// Emit 404 explicitly for missing paths so security regression tests hold.
if (strpos($path, '/api') === 0) {
    $file = $repoRoot . $path;
    if (is_file($file) || is_dir($file)) {
        return false;
    }
    http_response_code(404);
    header('Content-Type: text/plain');
    echo '404 Not Found';
    exit;
}

// Frontend paths: map to frontend/v1/
$frontendFile = $repoRoot . '/frontend/v1' . ($path === '/' ? '/index.php' : $path);

if (is_file($frontendFile)) {
    $ext = strtolower(pathinfo($frontendFile, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        // Normalise $_SERVER so PHP_SELF reflects the request URI.
        $_SERVER['PHP_SELF']    = $path;
        $_SERVER['SCRIPT_NAME'] = $path;
        // chdir to the file's own directory so include("../config.php") etc.
        // inside frontend PHP files resolve against that file's location.
        // Restore the repo root afterwards for any subsequent requests that
        // run in the same PHP process.
        chdir(dirname($frontendFile));
        include $frontendFile;
        chdir($repoRoot);
    } else {
        // Static asset: output with correct Content-Type.
        static $mimeMap = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
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
        chdir(dirname($indexFile));
        include $indexFile;
        chdir($repoRoot);
        exit;
    }
}

http_response_code(404);
header('Content-Type: text/plain');
echo '404 Not Found';
exit;

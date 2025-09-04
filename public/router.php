<?php
/**
 * Development Server Router
 * Handles both API routes and static files
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Handle API requests
if (strpos($path, '/api/') === 0) {
    // Forward to main index.php for API handling
    include __DIR__ . '/index.php';
    return;
}

// Handle static assets
if (strpos($path, '/assets/') === 0) {
    $filePath = __DIR__ . $path;
    if (file_exists($filePath)) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        header("Content-Type: $mimeType");
        readfile($filePath);
        return;
    }
}

// Handle HTML pages - serve appropriate HTML file or default to index.html
$htmlFiles = [
    '/' => 'index.html',
    '/login' => 'login.html',
    '/register' => 'register.html',
    '/products' => 'products.html',
    '/dashboard' => 'dashboard.html',
    '/cart' => 'cart.html',
    '/profile' => 'profile.html'
];

$requestPath = rtrim($path, '/') ?: '/';
$htmlFile = $htmlFiles[$requestPath] ?? null;

if ($htmlFile && file_exists(__DIR__ . '/' . $htmlFile)) {
    include __DIR__ . '/' . $htmlFile;
    return;
}

// Check if exact HTML file exists
$htmlPath = __DIR__ . $path;
if (pathinfo($path, PATHINFO_EXTENSION) === 'html' && file_exists($htmlPath)) {
    include $htmlPath;
    return;
}

// If no specific route found, serve index.html for SPA-like behavior
include __DIR__ . '/index.html';
?>
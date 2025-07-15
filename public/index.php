<?php
// Include header component
include __DIR__ . '/components/header.php';

// Get the request URI and remove query string
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading/trailing slashes and 'public' if present
$basePath = str_replace('/public', '', $requestUri);
$basePath = trim($basePath, '/');

// Default to 'landing' if no path is given or if path is 'index.php'
if ($basePath === '' || $basePath === 'index.php') {
    $pagePath = 'landing';
} else {
    $pagePath = $basePath;
}

// Sanitize: only allow letters, numbers, dashes, slashes
if (!preg_match('/^[a-zA-Z0-9\\/_-]*$/', $pagePath)) {
    $pagePath = '404';
}

// Fast routing for login and signup pages
if ($pagePath === 'login') {
    include __DIR__ . '/pages/login.php';
    include __DIR__ . '/components/footer.php';
    exit;
}
if ($pagePath === 'signup') {
    include __DIR__ . '/pages/signup.php';
    include __DIR__ . '/components/footer.php';
    exit;
}

// Build the file path (support nested pages)
$pageFile = __DIR__ . '/pages/' . $pagePath . '.php';

// If the file doesn't exist, try index.php in a subdirectory
if (!file_exists($pageFile)) {
    $indexFile = __DIR__ . '/pages/' . $pagePath . '/index.php';
    if (file_exists($indexFile)) {
        $pageFile = $indexFile;
    } else {
        $pageFile = __DIR__ . '/pages/404.php';
    }
}

// If accessed directly as /public/index.php, always render landing.php
if (basename($_SERVER['SCRIPT_NAME']) === 'index.php' && $requestUri === '/autocrm/public/index.php') {
    include __DIR__ . '/pages/landing.php';
    include __DIR__ . '/components/footer.php';
    exit;
}

// For dashboard pages, always include the sidebar and main content
if (strpos($pagePath, 'dashboard') === 0 && is_file($pageFile)) {
    include $pageFile;
} else {
    // For all other pages, just include the resolved page
    include $pageFile;
}

// Include footer component
include __DIR__ . '/components/footer.php';

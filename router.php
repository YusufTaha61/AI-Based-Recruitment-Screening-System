<?php
// Router for PHP's built-in server. Start the site with:
//   php -S 127.0.0.1:8000 router.php
//
// Only the pages (*.php in the main folder) and the assets/ folder can be
// opened in the browser. Everything else returns 404: the database, the
// uploaded CVs (uploads/), and the code and data folders (includes/, ml/,
// ornekler/, .git/).

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Files that are only for the terminal, never for the browser
const BLOCKED_PAGES = ['/router.php', '/setup.php', '/import_old.php'];

$isHome = $path === '/';
$isPage = preg_match('#^/[a-z_]+\.php$#', $path) && !in_array($path, BLOCKED_PAGES, true);
$isAsset = preg_match('#^/assets/[A-Za-z0-9_\-]+\.(css|js|png|jpg|svg|ico|woff2?)$#', $path);

if (($isHome || $isPage || $isAsset) && ($isHome || is_file(__DIR__ . $path))) {
    return false; // let the server show the file as usual
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Sayfa bulunamadı.';

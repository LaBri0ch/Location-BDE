<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

date_default_timezone_set(APP_TZ);
mb_internal_encoding('UTF-8');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('bde_loc');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $isHttps,
]);
session_start();

require __DIR__ . '/lib.php';
require __DIR__ . '/db.php';
require __DIR__ . '/booking.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/images.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/layout.php';

if (is_installed()) {
    migrate_schema(db());   // met à niveau une base créée par une ancienne version
}

set_exception_handler(function (Throwable $e): void {
    error_log('Erreur non gérée : ' . $e->getMessage() . ' dans ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Une erreur est survenue. Réessaie dans un instant ou préviens le bureau.';
    }
});

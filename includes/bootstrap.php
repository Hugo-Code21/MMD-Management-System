<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Something went wrong</title>'
        . '<body style="font:16px system-ui;max-width:38rem;margin:12vh auto;padding:1.5rem;color:#e8ecf8;background:#10131c">'
        . '<h1>Something went wrong</h1><p>The request could not be completed. Please try again later.</p></body></html>';
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('mmd_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers.php';

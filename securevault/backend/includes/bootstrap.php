<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Messages are deliberately metadata-only: never log request bodies or exception text.
set_exception_handler(static function (Throwable $error): void {
    $reference = bin2hex(random_bytes(6));
    error_log('SecureVault failure ' . $reference . ' [' . get_class($error) . ']');
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>SecureVault • Unable to continue</title><link rel="stylesheet" href="assets/css/style.css"><main class="failure"><span class="eyebrow">SECUREVAULT</span><h1>We couldn’t complete that request.</h1><p>Please try again later. Your request has not been approved.</p><p>Reference: ' . $reference . '</p><a class="btn btn-primary" href="index.php">Return to SecureVault</a></main></html>';
});
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if ($https) header('Strict-Transport-Security: max-age=31536000');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('securevault_session');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
$sessionDirectory = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) throw new RuntimeException('Session storage unavailable');
session_save_path($sessionDirectory);
if (!session_start()) throw new RuntimeException('Session initialization failed');
require_once __DIR__ . '/../../database/connection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../frontend/views/layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12 * 1024 * 1024) abort_request(413, 'The upload request is too large. Choose a file no larger than 10 MB.');

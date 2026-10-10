<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $loaded = false, $user = null;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['user_id'])) return null;
    if (empty($_SESSION['email_verified_at'])) { $_SESSION = []; return null; }
    if (time() - (int)($_SESSION['last_active'] ?? 0) > 1800 || time() - (int)($_SESSION['authenticated_at'] ?? 0) > 28800) {
        $_SESSION = []; session_regenerate_id(true); flash('Your session expired. Please sign in again.'); return null;
    }
    $user = query('SELECT u.id, u.username, u.email, u.created_at FROM users u JOIN verified_emails v ON v.user_id = u.id WHERE u.id = ?', [$_SESSION['user_id']])->fetch() ?: null;
    if (!$user) { $_SESSION = []; return null; }
    $_SESSION['last_active'] = time();
    return $user;
}
function require_auth(): array
{
    $user = current_user();
    if (!$user) redirect('login.php');
    return $user;
}

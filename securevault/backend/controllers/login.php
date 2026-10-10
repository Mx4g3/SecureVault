<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/verification.php';
if (current_user()) redirect('dashboard.php');
$errors = []; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(input('email', $_POST));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $identity = hash('sha256', $email);
    $remote = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $attempts = query('SELECT COUNT(*) FROM login_attempts WHERE attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) AND (identity_hash = ? OR remote_hash = ?)', [$identity, $remote])->fetchColumn();
    if ((int)$attempts >= 10) { http_response_code(429); $errors[] = 'Too many attempts. Please wait 15 minutes before trying again.'; }
    else {
        $account = strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) ? query('SELECT * FROM users WHERE email = ?', [$email])->fetch() : false;
        // A valid dummy hash keeps unknown-account verification on the same expensive path.
        $valid = password_verify(strlen($password) <= 72 ? $password : '', $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if ($account && $valid && strlen($password) >= 8 && strlen($password) <= 72) {
            if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $account['id']]);
            begin_email_verification((int)$account['id']);
        }
        transaction(function () use ($identity, $remote): void {
            query('INSERT INTO login_attempts (identity_hash, remote_hash) VALUES (?, ?)', [$identity, $remote]);
            query('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
            audit('login_failed', 'An unsuccessful sign-in was rejected.');
        });
        http_response_code(422); $errors[] = 'Email or password is incorrect.';
    }
}
page_start('Sign in', '', true);
require __DIR__ . '/../../frontend/views/login.php';

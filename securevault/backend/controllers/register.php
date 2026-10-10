<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/verification.php';
if (current_user()) redirect('dashboard.php');
$errors = []; $username = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = input('username', $_POST); $email = strtolower(input('email', $_POST));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/D', $username)) $errors[] = 'Username must be 3–30 letters, numbers, or underscores.';
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address (up to 254 characters).';
    if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'Password must be between 8 and 72 bytes.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        try {
            $userId = transaction(function () use ($username, $email, $password): int {
                query('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)', [$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int)db()->lastInsertId();
                audit('account_created', 'Account created; email verification pending.', $id);
                return $id;
            });
            begin_email_verification($userId);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? 0) !== 1062) throw $error;
            $errors[] = 'An account could not be created with those details. Try another email or sign in.';
        }
    }
    http_response_code(422);
}
page_start('Create an account', '', true);
require __DIR__ . '/../../frontend/views/register.php';

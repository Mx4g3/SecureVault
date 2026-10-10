<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/verification.php';
if (current_user()) redirect('dashboard.php');
$userId = (int)($_SESSION['pending_user_id'] ?? 0);
if (!$userId || time() - (int)($_SESSION['pending_since'] ?? 0) > 1800) {
    unset($_SESSION['pending_user_id'], $_SESSION['pending_since'], $_SESSION['email_challenge']);
    flash('Sign in with your password to request a verification code.'); redirect('login.php');
}
$errors = [];
if (isset($_SESSION['verification_error'])) { $errors[] = $_SESSION['verification_error']; unset($_SESSION['verification_error']); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (input('action', $_POST) === 'resend') {
        try { issue_email_code($userId); flash('A new code was sent. Previous codes no longer work.'); redirect('verify_email.php'); }
        catch (DomainException $error) { $errors[] = $error->getMessage(); http_response_code(429); }
    } else {
        if (redeem_email_code((string)($_SESSION['email_challenge'] ?? ''), input('code', $_POST), $userId)) {
            $_SESSION = []; session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['last_active'] = $_SESSION['authenticated_at'] = $_SESSION['email_verified_at'] = time();
            redirect('dashboard.php');
        }
        $errors[] = 'The code is incorrect, expired, or unavailable. After five attempts, request a new code.';
        http_response_code(422);
    }
}
$codeDelivered = (bool)query('SELECT id FROM email_challenges WHERE id = ? AND user_id = ? AND delivered = 1 AND consumed = 0', [(string)($_SESSION['email_challenge'] ?? ''),$userId])->fetch();
page_start('Verify your email', '', true);
require __DIR__ . '/../../frontend/views/verify_email.php';

<?php
declare(strict_types=1);
require_once __DIR__ . '/mailer.php';

/** The optional transport is dependency injection for CLI tests, never request input. */
function issue_email_code(int $userId, ?callable $transport = null): string
{
    $code = (string)random_int(100000, 999999);
    $id = bin2hex(random_bytes(32));
    $remote = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'cli'));
    $account = transaction(function () use ($userId, $code, $id, $remote): array {
        $account = query('SELECT id,email FROM users WHERE id = ? FOR UPDATE', [$userId])->fetch();
        if (!$account) throw new DomainException('Please sign in again.');
        query('DELETE FROM email_challenges WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)');
        $recent = query('SELECT COUNT(*) FROM email_challenges WHERE user_id = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND)', [$userId])->fetchColumn();
        $hourly = query('SELECT COUNT(*) FROM email_challenges WHERE created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) AND (user_id = ? OR remote_hash = ?)', [$userId,$remote])->fetchColumn();
        if ($recent || (int)$hourly >= 5) throw new DomainException('Please wait before requesting another code. Codes can be sent once per minute, up to five per hour for this account or connection.');
        query('UPDATE email_challenges SET consumed = 1 WHERE user_id = ?', [$userId]);
        query('INSERT INTO email_challenges (id,user_id,code_hash,remote_hash,expires_at) VALUES (?,?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE))', [$id,$userId,password_hash($code,PASSWORD_DEFAULT),$remote]);
        return $account;
    });
    $_SESSION['email_challenge'] = $id;
    $send = $transport ?? 'send_email';
    $sent = $send($account['email'], 'Your SecureVault verification code', "Your SecureVault code is: $code\n\nIt expires in 10 minutes and can be used once. Do not share this code. If you did not request it, ignore this email.");
    if (!$sent) throw new DomainException('We could not send your code. Please try again later or contact the administrator.');
    query('UPDATE email_challenges SET delivered = 1 WHERE id = ? AND consumed = 0', [$id]);
    audit('verification_sent', 'An email verification code was requested.', $userId);
    return $id;
}

function redeem_email_code(string $id, string $code, int $userId): bool
{
    return transaction(function () use ($id,$code,$userId): bool {
        query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
        $challenge = query('SELECT *, expires_at > UTC_TIMESTAMP() AS unexpired FROM email_challenges WHERE id = ? AND user_id = ? FOR UPDATE', [$id,$userId])->fetch();
        if (!$challenge || !$challenge['delivered'] || $challenge['consumed'] || !$challenge['unexpired'] || $challenge['attempts'] >= 5) return false;
        query('UPDATE email_challenges SET attempts = attempts + 1 WHERE id = ?', [$id]);
        if (!preg_match('/^[0-9]{6}$/D', $code) || !password_verify($code, $challenge['code_hash'])) return false;
        query('UPDATE email_challenges SET consumed = 1 WHERE id = ?', [$id]);
        query('INSERT IGNORE INTO verified_emails (user_id) VALUES (?)', [$userId]);
        audit('email_verified_login', 'Email code verified; sign-in completed.', $userId);
        return true;
    });
}

function begin_email_verification(int $userId): never
{
    $_SESSION = []; session_regenerate_id(true);
    $_SESSION['pending_user_id'] = $userId;
    $_SESSION['pending_since'] = time();
    try { issue_email_code($userId); flash('Check your email for a six-digit code.'); }
    catch (DomainException $error) { $_SESSION['verification_error'] = $error->getMessage(); }
    redirect('verify_email.php');
}

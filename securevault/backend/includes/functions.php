<?php
declare(strict_types=1);

function redirect(string $page): never { header('Location: ' . $page, true, 303); exit; }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function query(string $sql, array $params = []): PDOStatement { $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt; }
function audit(string $event, string $description, ?int $userId = null): void
{
    // Call inside the same transaction as record/account mutations so logging failure rolls back.
    query('INSERT INTO security_logs (user_id, event_type, description) VALUES (?, ?, ?)', [$userId ?? ($_SESSION['user_id'] ?? null), $event, $description]);
}
function transaction(callable $operation): mixed
{
    db()->beginTransaction();
    try { $result = $operation(); db()->commit(); return $result; }
    catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
}
function abort_request(int $status, string $message): never
{
    http_response_code($status);
    page_start('Request unavailable');
    echo '<section class="card empty-state"><div class="empty-symbol">' . icon('shield') . '</div><h1>Unable to continue</h1><p>' . e($message) . '</p><a class="btn btn-primary" href="index.php">Back to SecureVault</a></section>';
    page_end(); exit;
}
function owned_document(int $id, int $userId): array
{
    $record = query('SELECT * FROM documents WHERE id = ? AND user_id = ?', [$id, $userId])->fetch();
    if (!$record) { audit('record_access_denied', 'An unavailable record was requested.'); abort_request(404, 'This record is unavailable.'); }
    return $record;
}
function display_date(string $date): string { return date('M j, Y', strtotime($date . ' UTC')); }
function confidentiality_badge(string $value): string { return '<span class="badge level-' . strtolower(e($value)) . '"><span></span>' . e($value) . '</span>'; }

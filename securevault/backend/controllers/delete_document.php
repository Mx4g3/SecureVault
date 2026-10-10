<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_post(); $user = require_auth(); csrf_check(); $id = positive_id($_POST['id'] ?? null);
owned_document($id, (int)$user['id']);
transaction(function () use ($id, $user): void {
    $stmt = query('DELETE FROM documents WHERE id = ? AND user_id = ?', [$id, $user['id']]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Record unavailable');
    audit('document_deleted', 'Document record #' . $id . ' was deleted.');
});
flash('Document deleted.'); redirect('documents.php');

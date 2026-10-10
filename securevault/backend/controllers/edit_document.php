<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_auth(); $editing = true; $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
$id = positive_id($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null));
$data = owned_document($id, (int)$user['id']);
$attachment = file_summary($id, (int)$user['id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$values, $errors] = validate_document($_POST); $data = array_merge($data, $values);
    $remove = input('remove_attachment', $_POST) === '1';
    try { $upload = validated_upload(); } catch (DomainException $error) { $errors['attachment'] = $error->getMessage(); }
    if ($remove && isset($upload)) $errors['attachment'] = 'Choose either a replacement file or removal, not both.';
    if (!$errors) {
      try {
        transaction(function () use ($values, $id, $user, $upload, $remove): void {
            query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$user['id']]);
            // Lock and recheck ownership within the mutation transaction.
            if (!query('SELECT id FROM documents WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $user['id']])->fetch()) throw new RuntimeException('Record unavailable');
            query('UPDATE documents SET title = ?, document_type = ?, description = ?, confidentiality = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?', [$values['title'], $values['document_type'], $values['description'], $values['confidentiality'], $id, $user['id']]);
            audit('document_updated', 'Document record #' . $id . ' was updated.');
            save_attachment($id, (int)$user['id'], $upload, $remove);
        });
        flash('Your changes have been saved.'); redirect('view_document.php?id=' . $id);
      } catch (DomainException $error) { $errors['attachment'] = $error->getMessage(); }
    }
    http_response_code(422);
}
page_start('Edit document', 'documents'); require __DIR__ . '/../../frontend/views/document_form.php'; page_end();

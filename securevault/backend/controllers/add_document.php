<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_auth(); $editing = false; $errors = []; $attachment = null;
$data = ['title'=>'','document_type'=>'','description'=>'','confidentiality'=>'Private'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); [$data, $errors] = validate_document($_POST);
    try { $upload = validated_upload(); } catch (DomainException $error) { $errors['attachment'] = $error->getMessage(); }
    if (!$errors) {
      try {
        $id = transaction(function () use ($data, $user, $upload): int {
            query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$user['id']]);
            query('INSERT INTO documents (user_id, title, document_type, description, confidentiality) VALUES (?, ?, ?, ?, ?)', [$user['id'], $data['title'], $data['document_type'], $data['description'], $data['confidentiality']]);
            $id = (int)db()->lastInsertId();
            save_attachment($id, (int)$user['id'], $upload);
            audit('document_created', 'Document record #' . $id . ' was created.'); return $id;
        });
        flash('Document saved to your workspace.'); redirect('view_document.php?id=' . $id);
      } catch (DomainException $error) { $errors['attachment'] = $error->getMessage(); }
    }
    http_response_code(422);
}
page_start('Add document', 'documents'); require __DIR__ . '/../../frontend/views/document_form.php'; page_end();

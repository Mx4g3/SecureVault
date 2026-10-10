<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_auth(); $id = positive_id($_GET['id'] ?? null); $record = owned_document($id, (int)$user['id']);
page_start('Delete document', 'documents');
require __DIR__ . '/../../frontend/views/confirm_delete.php';

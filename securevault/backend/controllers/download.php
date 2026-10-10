<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_auth();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true)) { header('Allow: GET, HEAD'); abort_request(405, 'This action requires a download request.'); }
$id = positive_id($_GET['id'] ?? null);
$file = query('SELECT f.original_name, f.byte_size, f.content FROM document_files f JOIN documents d ON d.id = f.document_id WHERE d.id = ? AND d.user_id = ?', [$id,$user['id']])->fetch();
if (!$file) abort_request(404, 'This file is unavailable.');
audit('file_downloaded', 'The attachment for document #' . $id . ' was requested.');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($file['original_name']));
header('Content-Length: ' . (int)$file['byte_size']);
header("Content-Security-Policy: sandbox; default-src 'none'");
session_write_close();
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') echo $file['content'];
exit;

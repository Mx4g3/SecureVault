<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_auth(); $search = input('q', $_GET); $type = input('type', $_GET); $level = input('level', $_GET);
if (mb_strlen($search) > 120 || ($type !== '' && !in_array($type, DOCUMENT_TYPES, true)) || ($level !== '' && !in_array($level, CONFIDENTIALITIES, true))) abort_request(400, 'Choose valid filters and keep your search to 120 characters.');
$page = isset($_GET['page']) ? positive_id($_GET['page']) : 1;
$where = 'user_id = ?'; $params = [$user['id']];
if ($search !== '') { $where .= " AND (title LIKE ? ESCAPE '!' OR description LIKE ? ESCAPE '!')"; $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%'; $params[] = $term; $params[] = $term; }
if ($type !== '') { $where .= ' AND document_type = ?'; $params[] = $type; }
if ($level !== '') { $where .= ' AND confidentiality = ?'; $params[] = $level; }
$count = (int)query('SELECT COUNT(*) FROM documents WHERE ' . $where, $params)->fetchColumn();
$pages = max(1, (int)ceil($count / 10)); $page = min($page, $pages); $offset = ($page - 1) * 10;
$stmt = db()->prepare('SELECT * FROM documents WHERE ' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT 10 OFFSET ?');
foreach ($params as $i => $value) $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$stmt->bindValue(count($params) + 1, $offset, PDO::PARAM_INT); $stmt->execute(); $records = $stmt->fetchAll();
page_start('My documents', 'documents');
require __DIR__ . '/../../frontend/views/documents.php';

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_auth();
$stats = query("SELECT COUNT(*) total, COALESCE(SUM(confidentiality = 'Private'), 0) private_count, COALESCE(SUM(created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)), 0) recent_count FROM documents WHERE user_id = ?", [$user['id']])->fetch();
$records = query('SELECT * FROM documents WHERE user_id = ? ORDER BY updated_at DESC, id DESC LIMIT 5', [$user['id']])->fetchAll();
$types = query('SELECT document_type, COUNT(*) total FROM documents WHERE user_id = ? GROUP BY document_type ORDER BY total DESC, document_type', [$user['id']])->fetchAll();
page_start('Overview', 'dashboard');
require __DIR__ . '/../../frontend/views/dashboard.php';

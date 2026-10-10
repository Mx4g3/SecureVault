<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_auth();
$events = query('SELECT event_type, description, created_at FROM security_logs WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$user['id']])->fetchAll();
page_start('Security activity', 'activity');
require __DIR__ . '/../../frontend/views/activity.php';

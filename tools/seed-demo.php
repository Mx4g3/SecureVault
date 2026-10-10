<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../securevault/database/connection.php';
$pdo = db();
$email = 'demo@securevault.test';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $stmt->execute([$email]);
if ($stmt->fetch()) { echo "Demo account already exists; nothing was changed.\n"; exit; }
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO users (username,email,password_hash) VALUES (?,?,?)');
    $stmt->execute(['demo_student', $email, password_hash('VaultDemo!2026', PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO documents (user_id,title,document_type,description,confidentiality) VALUES (?,?,?,?,?)');
    foreach ([
        ['Scholarship Application','Academic','Sample record: scholarship requirements for AY 2026–2027.','Private'],
        ['Course Registration','Academic','Sample record: enrollment checklist and course schedule.','Internal'],
        ['Monthly Budget Notes','Financial','Sample record: a reference for organizing monthly expenses.','Private'],
        ['Internship Checklist','Work','Sample record: onboarding requirements and reminders.','Internal'],
        ['Community Event Guide','Personal','Sample record: general event information.','Public'],
    ] as $record) $stmt->execute(array_merge([$userId], $record));
    $stmt = $pdo->prepare('INSERT INTO security_logs (user_id,event_type,description) VALUES (?,?,?)');
    $stmt->execute([$userId,'demo_initialized','Five fictional sample records were added.']);
    $pdo->commit();
} catch (Throwable $error) { $pdo->rollBack(); throw $error; }
echo "Demo account: demo@securevault.test\nPassword: VaultDemo!2026\nUse this account only for fictional sample data.\n";

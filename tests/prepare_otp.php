<?php
// CLI-only fixture for the isolated local QA database; never deploy tests in the web root.
if (PHP_SAPI !== 'cli') exit(1);
$email = $argv[1] ?? '';
if (!preg_match('/^qa_[ab]_[0-9]+@example[.]test$/D', $email)) exit(2);
$settings = json_decode(file_get_contents(dirname(__DIR__) . '/.runtime/settings.json'), true);
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;dbname=securevault_db', 'root', $settings['root_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->prepare('SELECT id,password_hash FROM users WHERE email=?'); $stmt->execute([$email]); $account=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$account || !password_verify('TestVault!98765',$account['password_hash'])) exit(3);
$stmt=$pdo->prepare('UPDATE email_challenges SET code_hash=?,delivered=1,created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND) WHERE user_id=? AND consumed=0');
$stmt->execute([password_hash('654321',PASSWORD_DEFAULT),$account['id']]);
if ($stmt->rowCount() !== 1) exit(4);

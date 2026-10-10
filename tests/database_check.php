<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../securevault/database/connection.php';
$pdo = db();
$checks = [];
$accounts = $pdo->query("SELECT email,password_hash FROM users WHERE email REGEXP '^qa_[ab]_[0-9]+@example[.]test$'")->fetchAll();
$checks['Test account passwords are hashes verified by PHP'] = count($accounts) >= 2;
foreach ($accounts as $account) $checks['Test account passwords are hashes verified by PHP'] = $checks['Test account passwords are hashes verified by PHP'] && $account['password_hash'] !== 'TestVault!98765' && password_verify('TestVault!98765', $account['password_hash']);
$logs = $pdo->query('SELECT event_type, description FROM security_logs')->fetchAll();
$serialized = json_encode($logs);
$checks['Audit logs do not include test passwords, hashes, email, or document payloads'] = !preg_match('/TestVault|\$2y\$|example\.test|<script>|Scholarship Application/', $serialized);
$grants = implode("\n", array_map(static fn($row) => implode(' ', $row), $pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_NUM)));
$checks['Application database account has no schema or grant privileges'] = !preg_match('/ALL PRIVILEGES|CREATE|DROP|ALTER|GRANT OPTION/i', $grants);
foreach ($checks as $name=>$passed) echo ($passed ? 'PASS ' : 'FAIL ') . $name . "\n";
file_put_contents(__DIR__ . '/database-results.json', json_encode($checks, JSON_PRETTY_PRINT));
exit(in_array(false, $checks, true) ? 1 : 0);

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
// Only operates on the isolated local database initialized by Start-SecureVault.ps1.
$settings = json_decode(file_get_contents(dirname(__DIR__) . '/.runtime/settings.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;dbname=securevault_db', 'root', $settings['root_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$pdo->exec("DELETE l FROM security_logs l INNER JOIN users u ON l.user_id=u.id WHERE u.email REGEXP '^qa_[ab]_[0-9]+@example[.]test$'");
$pdo->exec("DELETE FROM users WHERE email REGEXP '^qa_[ab]_[0-9]+@example[.]test$'");
// Clear only test identities and their attempts, including the injection-test identity.
$rows = $pdo->query('SELECT id, identity_hash FROM login_attempts')->fetchAll(PDO::FETCH_ASSOC);
$resultsPath = dirname(__DIR__) . '/tests/results.json';
$identities = [hash('sha256', "' or '1'='1")];
if (is_file($resultsPath)) {
    $results = json_decode(file_get_contents($resultsPath), true);
    foreach ($results['qa_emails'] ?? [] as $email) {
        $identities[] = hash('sha256', $email);
        if (preg_match('/^qa_a_([0-9]+)@/', $email, $match)) $identities[] = hash('sha256', 'unknown_' . $match[1] . '@example.test');
    }
}
$delete = $pdo->prepare('DELETE FROM login_attempts WHERE id = ?');
foreach ($rows as $row) if (in_array($row['identity_hash'], $identities, true)) $delete->execute([$row['id']]);
$pdo->commit();
echo "Disposable QA accounts and their test attempts removed.\n";

<?php
declare(strict_types=1);

function icon(string $name): string
{
    $paths = [
        'shield' => '<path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8 12 3 3 5-6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'file' => '<path d="M14 3H5v18h14V8zM14 3v6h5M8 13h8M8 17h5"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
        'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'logout' => '<path d="M9 4H4v16h5M10 12h11m-4-4 4 4-4 4"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['file']) . '</svg>';
}
function page_start(string $title, string $active = '', bool $authPage = false): void
{
    $user = current_user();
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> · SecureVault</title><meta name="description" content="Your personal document record workspace."><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/css/bootstrap.min.css"><link rel="stylesheet" href="assets/css/style.css"><script src="assets/js/app.js" defer></script></head><body><a class="skip-link" href="#main">Skip to content</a>
    <?php if ($user && !$authPage): ?>
    <aside class="sidebar"><a class="brand" href="dashboard.php"><span class="brand-icon"><?= icon('shield') ?></span>SecureVault<span class="brand-dot">.</span></a><div class="workspace-label">PERSONAL WORKSPACE</div><nav aria-label="Main navigation">
    <?php foreach (['dashboard' => ['Overview','grid'], 'documents' => ['My documents','file'], 'activity' => ['Security activity','shield']] as $key => [$label,$symbol]): ?>
        <a class="nav-item <?= $active === $key ? 'selected' : '' ?>" href="<?= $key ?>.php" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?><?= $label ?></a>
    <?php endforeach; ?></nav><div class="sidebar-note"><?= icon('lock') ?><strong>A space of your own</strong><p>Your records are visible only to your account.</p></div><div class="profile"><div class="avatar"><?= e(strtoupper(substr($user['username'], 0, 1))) ?></div><div class="profile-name"><strong><?= e($user['username']) ?></strong><span>Personal account</span></div><form method="post" action="logout.php"><?php csrf_field(); ?><button class="icon-button" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></button></form></div></aside>
    <div class="workspace"><header class="topbar"><span>Workspace <span class="breadcrumb-slash">/</span> <strong><?= e($title) ?></strong></span><span class="session-indicator"><span></span> Protected session</span></header><main id="main" class="main-content">
    <?php else: ?><main id="main" class="auth-shell"><?php endif; ?>
    <?php if (isset($_SESSION['flash'])): ?><div class="alert alert-success flash" role="status"><?= icon('check') ?><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif;
}
function page_end(): void { ?></main><?php if (current_user()): ?></div><?php endif; ?></body></html><?php }
function error_summary(array $errors): void
{
    if (!$errors) return;
    echo '<div class="alert alert-danger" role="alert"><strong>Please check the following:</strong><ul class="mb-0">';
    foreach ($errors as $error) echo '<li>' . e($error) . '</li>';
    echo '</ul></div>';
}
function record_table(array $records): void
{
    if (!$records) { ?><div class="empty-state"><div class="empty-symbol"><?= icon('file') ?></div><h3>No documents here yet</h3><p>Add your first record, or adjust your search.</p><a class="btn btn-primary" href="add_document.php"><?= icon('plus') ?> Add document</a></div><?php return; }
    ?><div class="table-responsive"><table class="table record-table"><thead><tr><th>DOCUMENT NAME</th><th>TYPE</th><th>CONFIDENTIALITY</th><th>UPDATED</th><th><span class="visually-hidden">Open record</span></th></tr></thead><tbody><?php foreach ($records as $record): ?><tr><td><a class="document-link" href="view_document.php?id=<?= (int)$record['id'] ?>"><span class="file-icon"><?= icon('file') ?></span><span><?= e($record['title']) ?><small>Document record #<?= (int)$record['id'] ?></small></span></a></td><td><?= e($record['document_type']) ?></td><td><?= confidentiality_badge($record['confidentiality']) ?></td><td class="date-cell"><?= display_date($record['updated_at']) ?></td><td><a class="icon-button" href="view_document.php?id=<?= (int)$record['id'] ?>" aria-label="Open <?= e($record['title']) ?>"><?= icon('arrow') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php
}

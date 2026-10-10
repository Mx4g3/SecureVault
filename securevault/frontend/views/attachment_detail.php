<hr><section aria-label="Attached file"><h3>Attached file</h3>
<?php if ($attachment): ?>
<p class="attachment-name"><?= icon('file') ?> <?= e($attachment['original_name']) ?></p>
<p class="muted small"><?= number_format($attachment['byte_size'] / 1024, 1) ?> KB · Owner-only download</p>
<a class="btn btn-primary" href="download.php?id=<?= $id ?>">Download file <?= icon('arrow') ?></a>
<?php else: ?><p class="muted">No file attached. You can add one by editing this record.</p><?php endif; ?>
</section>

<div class="attachment-field">
<label for="attachment"><?= $attachment ? 'Replace attached file' : 'Attach a file' ?> <span class="optional">optional</span></label>
<?php if ($attachment): ?><p class="field-hint">Current: <?= e($attachment['original_name']) ?> (<?= number_format($attachment['byte_size'] / 1024, 1) ?> KB)</p><?php endif; ?>
<input type="hidden" name="MAX_FILE_SIZE" value="10485760">
<input class="form-control" type="file" name="attachment" id="attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.png,.jpg,.jpeg,.webp" aria-describedby="attachment-help">
<small class="field-hint" id="attachment-help">PDF, Word, PowerPoint, Excel, TXT, CSV, PNG, JPG or WebP. Maximum 10 MB per file; 100 MB per account. After a validation error, select the file again.</small>
<?php if ($attachment): ?><label class="attachment-remove"><input type="checkbox" name="remove_attachment" value="1"> Remove the current file when saving (permanent)</label><?php endif; ?>
</div>

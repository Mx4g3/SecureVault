<?php
declare(strict_types=1);

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const MAX_ACCOUNT_BYTES = 100 * 1024 * 1024;
const FILE_EXTENSIONS = ['pdf','doc','docx','ppt','pptx','xls','xlsx','txt','csv','png','jpg','jpeg','webp'];

function file_summary(int $id, int $userId): ?array
{
    return query('SELECT f.original_name, f.byte_size, f.mime_type FROM document_files f JOIN documents d ON d.id = f.document_id WHERE d.id = ? AND d.user_id = ?', [$id, $userId])->fetch() ?: null;
}

/** Inspect actual PHP upload bytes; never trust the browser-supplied MIME type or size. */
function validated_upload(): ?array
{
    if (!isset($_FILES['attachment'])) return null;
    $file = $_FILES['attachment'];
    if (!is_array($file) || !is_int($file['error'] ?? null)) throw new DomainException('Choose one valid file.');
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new DomainException('The file exceeds the upload limit (maximum 10 MB).');
    if ($file['error'] !== UPLOAD_ERR_OK) throw new DomainException('The upload did not finish. Select the file and try again.');
    $name = $file['name'] ?? null; $path = $file['tmp_name'] ?? null;
    if (!is_string($name) || !is_string($path) || !is_uploaded_file($path)) throw new DomainException('Choose a valid uploaded file.');
    if (!mb_check_encoding($name, 'UTF-8') || strlen($name) > 180 || preg_match('~[\\\\/:\x00-\x1F\x7F]~', $name) || trim($name) !== $name) throw new DomainException('Use a filename under 180 bytes without control characters or path separators.');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, FILE_EXTENSIONS, true)) throw new DomainException('That file format is not supported. Choose one of the listed formats.');
    $size = filesize($path);
    if (!$size || $size > MAX_FILE_BYTES) throw new DomainException('Choose a nonempty file no larger than 10 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $bytes = file_get_contents($path);
    if ($bytes === false || strlen($bytes) !== $size) throw new RuntimeException('Upload read failed');
    $valid = false;
    if ($ext === 'pdf') $valid = $mime === 'application/pdf' && str_starts_with($bytes, '%PDF-');
    elseif (in_array($ext, ['png','jpg','jpeg','webp'], true)) {
        $expected = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp'][$ext];
        $info = @getimagesize($path);
        $valid = $mime === $expected && $info && ($info['mime'] ?? '') === $expected;
    } elseif (in_array($ext, ['txt','csv'], true)) {
        $valid = in_array($mime, ['text/plain','text/csv','application/csv'], true) && mb_check_encoding($bytes, 'UTF-8') && !str_contains($bytes, "\0");
    } elseif (in_array($ext, ['doc','ppt','xls'], true)) {
        $allowed = ['application/x-ole-storage','application/CDFV2','application/vnd.ms-office', ['doc'=>'application/msword','ppt'=>'application/vnd.ms-powerpoint','xls'=>'application/vnd.ms-excel'][$ext]];
        $valid = str_starts_with($bytes, hex2bin('d0cf11e0a1b11ae1')) && in_array($mime, $allowed, true);
    } elseif (in_array($ext, ['docx','pptx','xlsx'], true)) {
        if (!class_exists(ZipArchive::class)) throw new DomainException('Office uploads require the PHP ZIP extension. Ask the administrator to enable it.');
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            try {
                $main = ['docx'=>'word/document.xml','pptx'=>'ppt/presentation.xml','xlsx'=>'xl/workbook.xml'][$ext];
                $kind = ['docx'=>'wordprocessingml.document','pptx'=>'presentationml.presentation','xlsx'=>'spreadsheetml.sheet'][$ext];
                $typesInfo = $zip->statName('[Content_Types].xml');
                $valid = $zip->numFiles <= 2000 && $zip->locateName($main) !== false && $typesInfo && $typesInfo['size'] <= 131072;
                $total = 0;
                for ($i=0; $valid && $i < $zip->numFiles; $i++) {
                    $entry = $zip->statIndex($i);
                    $total += $entry['size'];
                    if ($total > 50 * 1024 * 1024 || $entry['size'] > 100 * max(1, $entry['comp_size']) || preg_match('~(?:^/|\.\./|\\\\|vbaproject|/embeddings/)~i', $entry['name']) || ($entry['encryption_method'] ?? 0) !== 0) $valid = false;
                }
                if ($valid) {
                    $types = $zip->getFromName('[Content_Types].xml');
                    $valid = is_string($types) && !preg_match('/<!DOCTYPE|<!ENTITY/i', $types);
                    if ($valid) {
                        $xml = new DOMDocument();
                        $valid = @$xml->loadXML($types, LIBXML_NONET);
                        $matched = false;
                        if ($valid) foreach ($xml->getElementsByTagName('Override') as $node) {
                            if ($node->getAttribute('PartName') === '/' . $main && $node->getAttribute('ContentType') === 'application/vnd.openxmlformats-officedocument.' . $kind . '.main+xml') $matched = true;
                        }
                        $valid = $valid && $matched;
                    }
                }
            } finally { $zip->close(); }
        }
    }
    if (!$valid) throw new DomainException('The file contents do not match a supported format, or the document package is unsafe.');
    return ['name'=>$name, 'size'=>$size, 'mime'=>$mime, 'bytes'=>$bytes];
}

/** Caller must hold a transaction. The owner row serializes storage-quota changes. */
function save_attachment(int $id, int $userId, ?array $upload, bool $remove = false): void
{
    if (!$upload && !$remove) return;
    if (!query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId])->fetch()) throw new RuntimeException('Owner unavailable');
    if (!query('SELECT id FROM documents WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $userId])->fetch()) throw new RuntimeException('Record unavailable');
    if ($upload) {
        $used = (int)query('SELECT COALESCE(SUM(f.byte_size),0) FROM document_files f JOIN documents d ON d.id = f.document_id WHERE d.user_id = ? AND d.id <> ?', [$userId, $id])->fetchColumn();
        if ($used + $upload['size'] > MAX_ACCOUNT_BYTES) throw new DomainException('Your account has reached its 100 MB storage limit. Remove a file before uploading another.');
        query('INSERT INTO document_files (document_id, original_name, mime_type, byte_size, content) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE original_name=VALUES(original_name), mime_type=VALUES(mime_type), byte_size=VALUES(byte_size), content=VALUES(content)', [$id,$upload['name'],$upload['mime'],$upload['size'],$upload['bytes']]);
        audit('file_saved', 'A file was attached to document #' . $id . '.');
    } else {
        query('DELETE f FROM document_files f JOIN documents d ON d.id = f.document_id WHERE d.id = ? AND d.user_id = ?', [$id,$userId]);
        audit('file_removed', 'The attachment for document #' . $id . ' was removed.');
    }
}

<?php
/**
 * Nena Studio Admin — securely serve customer reference files from /uploads/enquiries/.
 * The folder itself is blocked by .htaccess; only logged-in admins can view/download.
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$name = basename((string) ($_GET['f'] ?? ''));
$download = isset($_GET['download']);

$stmt = db()->prepare('SELECT reference_files_json FROM enquiries WHERE id = :id');
$stmt->execute([':id' => $id]);
$json = $stmt->fetchColumn();

$match = null;
foreach (json_decode((string) $json, true) ?: [] as $file) {
    if (is_array($file) && ($file['file'] ?? '') === $name) {
        $match = $file;
        break;
    }
}

$path = UPLOAD_ENQUIRY_DIR . '/' . $name;
if (!$match || !preg_match('/^[\w\-]+\.(jpg|png|webp|gif|pdf)$/', $name) || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$original = preg_replace('/[^\w.\- ()]+/', '_', (string) ($match['original'] ?? $name));

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $original . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'');
readfile($path);

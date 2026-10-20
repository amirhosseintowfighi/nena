<?php
/**
 * Nena Studio — core configuration, PDO connection and helpers.
 *
 * cPanel setup:
 *   1. cPanel > MySQL Databases: create a database + user, add the user to the DB (ALL PRIVILEGES).
 *   2. Fill in DB_NAME / DB_USER / DB_PASS below (cPanel prefixes them, e.g. "cpuser_nena").
 *   3. Optionally set SITE_URL (e.g. 'https://www.nenastudio.nl'); leave empty to auto-detect.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
//  Database credentials
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_nena');
define('DB_USER', 'cpaneluser_nena');
define('DB_PASS', 'change-this-password');

// ---------------------------------------------------------------------
//  Site settings
// ---------------------------------------------------------------------
define('SITE_URL', '');            // e.g. 'https://www.nenastudio.nl' (no trailing slash). Empty = auto-detect.
define('APP_DEBUG', false);        // true only while troubleshooting
define('APP_TIMEZONE', 'Europe/Amsterdam');

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_ENQUIRY_DIR', ROOT_PATH . '/uploads/enquiries');
define('UPLOAD_PORTFOLIO_DIR', ROOT_PATH . '/uploads/portfolio');

define('ENQUIRY_MAX_FILES', 8);
define('ENQUIRY_MAX_FILE_BYTES', 8 * 1024 * 1024);       // 8 MB per reference file
define('PORTFOLIO_MAX_IMAGE_BYTES', 12 * 1024 * 1024);   // 12 MB per image
define('PORTFOLIO_MAX_VIDEO_BYTES', 120 * 1024 * 1024);  // 120 MB per video (see .user.ini)

date_default_timezone_set(APP_TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// ---------------------------------------------------------------------
//  Database
// ---------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        error_log('Nena DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'The studio is temporarily unavailable. Please try again shortly.']);
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>Nena Studio</title>'
                . '<body style="font-family:sans-serif;background:#f9f6ec;color:#0a5b59;text-align:center;padding:12vh 20px">'
                . '<h1>We\'ll be right back.</h1><p>Nena Studio is temporarily unavailable. Please try again shortly.</p>'
                . (APP_DEBUG ? '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>' : '') . '</body>';
        }
        exit;
    }
    return $pdo;
}

// ---------------------------------------------------------------------
//  Output helpers
// ---------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** All settings as key => value (cached per request). */
function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// ---------------------------------------------------------------------
//  URL helpers
// ---------------------------------------------------------------------
/** Base path of the install relative to the web root, e.g. '' or '/nena'. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root = realpath(ROOT_PATH) ?: ROOT_PATH;
    $base = '';
    if ($docRoot !== '' && str_starts_with($root, $docRoot)) {
        $base = str_replace('\\', '/', substr($root, strlen($docRoot)));
    }
    $base = rtrim($base, '/');
    return $base;
}

function site_url(): string
{
    if (SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $canonical = '';
    try {
        $canonical = setting('canonical_url');
    } catch (Throwable $e) {
        $canonical = '';
    }
    if ($canonical !== '') {
        return rtrim($canonical, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . base_path();
}

/** Root-relative URL inside the install (works in a sub-folder too). */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Absolute URL (for canonical, OG, sitemap, JSON-LD). */
function abs_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return site_url() . '/' . ltrim($path, '/');
}

/** Static asset URL with cache-busting version. */
function asset(string $path): string
{
    $file = ROOT_PATH . '/' . ltrim($path, '/');
    $ver = is_file($file) ? (string) filemtime($file) : '1';
    $parts = array_map('rawurlencode', explode('/', ltrim($path, '/')));
    return url(implode('/', $parts)) . '?v=' . $ver;
}

/** URL for a stored media path (uploaded file, bundled asset, or external URL). */
function media_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $parts = array_map('rawurlencode', explode('/', ltrim($path, '/')));
    return url(implode('/', $parts));
}

/** URL of an official logo file if it was uploaded, else null (caller falls back to inline SVG). */
function logo_file_url(string $variant = 'MAIN'): ?string
{
    $rel = 'assets/images/NENA ' . strtoupper($variant) . '.svg';
    return is_file(ROOT_PATH . '/' . $rel) ? asset($rel) : null;
}

function project_url(string $slug): string
{
    return url('stories/' . rawurlencode($slug));
}

// ---------------------------------------------------------------------
//  Domain data
// ---------------------------------------------------------------------
function project_categories(): array
{
    return [
        'weddings'     => 'Weddings & Special Occasions',
        'made-for-you' => 'Made for You',
        'alterations'  => 'Alterations & Reimagining',
    ];
}

function enquiry_project_types(): array
{
    return ['Weddings & Special Occasions', 'Made for You', 'Alterations & Reimagining', 'Other'];
}

function enquiry_statuses(): array
{
    return ['New', 'In Discussion', 'In Progress', 'Completed'];
}

function contact_methods(): array
{
    return ['Email', 'WhatsApp / Phone', 'Instagram'];
}

function json_list(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? array_values(array_filter($data, 'is_string')) : [];
}

function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? substr($text, 0, 180) : 'project-' . bin2hex(random_bytes(3));
}

/**
 * Render a stored story (plain text) as safe HTML:
 * blank line = new paragraph, "## Heading" = <h3>, single newline = <br>.
 */
function render_story(string $text, string $headingTag = 'h3'): string
{
    $html = '';
    $blocks = preg_split("/\R{2,}/", trim(str_replace("\r\n", "\n", $text))) ?: [];
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $lines = explode("\n", $block);
        if (str_starts_with($lines[0], '## ')) {
            $html .= '<' . $headingTag . '>' . e(substr(array_shift($lines), 3)) . '</' . $headingTag . '>';
            if (!$lines) {
                continue;
            }
        }
        $html .= '<p>' . implode('<br>', array_map('e', $lines)) . '</p>';
    }
    return $html;
}

/** Detect an embeddable video provider. Returns ['type' => 'file'|'youtube'|'vimeo', 'src' => url]. */
function video_embed(?string $video): ?array
{
    $video = trim((string) $video);
    if ($video === '') {
        return null;
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $video, $m)) {
        return ['type' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1]];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $video, $m)) {
        return ['type' => 'vimeo', 'src' => 'https://player.vimeo.com/video/' . $m[1]];
    }
    $ext = strtolower(pathinfo(parse_url($video, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    return ['type' => 'file', 'src' => media_url($video), 'mime' => $ext === 'webm' ? 'video/webm' : 'video/mp4'];
}

// ---------------------------------------------------------------------
//  Sessions & security
// ---------------------------------------------------------------------
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('NENASESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    start_secure_session();
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// ---------------------------------------------------------------------
//  Uploads
// ---------------------------------------------------------------------
/**
 * Normalise a $_FILES entry (single or multiple) into a flat list.
 */
function normalize_files(array $entry): array
{
    $files = [];
    if (!isset($entry['name'])) {
        return $files;
    }
    if (is_array($entry['name'])) {
        foreach ($entry['name'] as $i => $name) {
            $files[] = [
                'name'     => $name,
                'type'     => $entry['type'][$i] ?? '',
                'tmp_name' => $entry['tmp_name'][$i] ?? '',
                'error'    => $entry['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $entry['size'][$i] ?? 0,
            ];
        }
    } else {
        $files[] = $entry;
    }
    return array_values(array_filter($files, fn($f) => ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
}

/**
 * Validate and move an uploaded file using real MIME sniffing.
 *
 * @param array  $file     one normalised $_FILES item
 * @param array  $allowed  mime => extension map
 * @param int    $maxBytes size limit
 * @param string $destDir  absolute directory
 * @return string stored filename
 * @throws RuntimeException with a user-friendly message
 */
function store_upload(array $file, array $allowed, int $maxBytes, string $destDir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'is larger than the server allows',
            UPLOAD_ERR_PARTIAL => 'was only partially uploaded',
            default => 'could not be uploaded',
        };
        throw new RuntimeException('"' . basename((string) $file['name']) . '" ' . $msg . '.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('"' . basename((string) $file['name']) . '" is too large (max ' . round($maxBytes / 1048576) . ' MB).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('"' . basename((string) $file['name']) . '" is not an allowed file type.');
    }
    if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' && @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('"' . basename((string) $file['name']) . '" is not a valid image.');
    }
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException('Upload folder is not writable.');
    }
    $filename = date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
        throw new RuntimeException('Could not save "' . basename((string) $file['name']) . '".');
    }
    @chmod($destDir . '/' . $filename, 0644);
    return $filename;
}

function allowed_image_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
}

function allowed_video_mimes(): array
{
    return [
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm',
    ];
}

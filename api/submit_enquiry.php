<?php
/**
 * Nena Studio — AJAX enquiry handler.
 * Validates the "Let's Create Together" form, stores reference files in
 * /uploads/enquiries/ (not publicly accessible) and saves the enquiry in MySQL.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/content.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
send_security_headers();

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

// post_max_size exceeded -> PHP empties $_POST and $_FILES
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, ['success' => false, 'message' => 'Your files are too large to send together. Please remove a few and try again.']);
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    respond(419, ['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.']);
}

// Spam protection: honeypot + minimum fill time
$started = (int) ($_POST['form_started'] ?? 0);
if (trim((string) ($_POST['website'] ?? '')) !== '' || ($started > 0 && time() - $started < 3)) {
    respond(200, ['success' => true, 'message' => "Thank you! I'll be in touch soon."]);
}

$ip = client_ip();
$pdo = db();

// Rate limit: max 5 enquiries per IP per hour
$rate = $pdo->prepare('SELECT COUNT(*) FROM enquiries WHERE ip_address = :ip AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)');
$rate->execute([':ip' => $ip]);
if ((int) $rate->fetchColumn() >= 5) {
    respond(429, ['success' => false, 'message' => 'You have sent several enquiries already. Please wait a little while, or email the studio directly.']);
}

// ---------------------------------------------------------------------
//  Validate fields
// ---------------------------------------------------------------------
$clean = static fn(string $key, int $max): string => mb_substr(trim(strip_tags((string) ($_POST[$key] ?? ''))), 0, $max);

$name        = $clean('name', 150);
$email       = mb_strtolower($clean('email', 190));
$projectType = $clean('project_type', 80);
$details     = mb_substr(trim((string) ($_POST['story_details'] ?? '')), 0, 5000);
$targetDate  = $clean('target_date', 10);
$contact     = $clean('preferred_contact', 40);
$handle      = $clean('contact_handle', 190);

$errors = [];
if (mb_strlen($name) < 2) {
    $errors['name'] = 'Please tell me your name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if (!in_array($projectType, enquiry_project_types(), true)) {
    $errors['project_type'] = 'Please choose the type of project.';
}
if (mb_strlen($details) < 10) {
    $errors['story_details'] = 'Tell me a little more about your idea.';
}
if ($targetDate !== '') {
    $dt = DateTime::createFromFormat('!Y-m-d', $targetDate);
    if (!$dt || $dt->format('Y-m-d') !== $targetDate) {
        $errors['target_date'] = 'Please choose a valid date.';
    } elseif ($dt < new DateTime('today')) {
        $errors['target_date'] = 'Please choose a date in the future.';
    }
} else {
    $targetDate = null;
}
if ($contact !== '' && !in_array($contact, contact_methods(), true)) {
    $contact = '';
}
if (in_array($contact, ['WhatsApp / Phone', 'Instagram'], true) && mb_strlen($handle) < 3) {
    $errors['contact_handle'] = 'Please add your contact details for this method.';
}
if ($contact === 'WhatsApp / Phone' && $handle !== '' && !preg_match('/^[+\d][\d\s().\-]{5,24}$/', $handle)) {
    $errors['contact_handle'] = 'Please enter a valid phone number.';
}
if ($contact === 'Email' || $contact === '') {
    $handle = '';
}

$uploads = isset($_FILES['reference_files']) ? normalize_files($_FILES['reference_files']) : [];
if (count($uploads) > ENQUIRY_MAX_FILES) {
    $errors['reference_files'] = 'You can upload up to ' . ENQUIRY_MAX_FILES . ' files.';
}

if ($errors) {
    respond(422, ['success' => false, 'message' => 'Please check the highlighted fields.', 'errors' => $errors]);
}

// ---------------------------------------------------------------------
//  Store files
// ---------------------------------------------------------------------
$allowed = allowed_image_mimes() + ['application/pdf' => 'pdf'];
$stored = [];
try {
    foreach ($uploads as $file) {
        $filename = store_upload($file, $allowed, ENQUIRY_MAX_FILE_BYTES, UPLOAD_ENQUIRY_DIR);
        $stored[] = [
            'file'     => $filename,
            'original' => mb_substr(preg_replace('/[^\w.\- ()]+/u', '_', basename((string) $file['name'])) ?? 'file', 0, 120),
            'size'     => (int) $file['size'],
        ];
    }
} catch (RuntimeException $e) {
    foreach ($stored as $s) {
        @unlink(UPLOAD_ENQUIRY_DIR . '/' . $s['file']);
    }
    respond(422, ['success' => false, 'message' => $e->getMessage(), 'errors' => ['reference_files' => $e->getMessage()]]);
}

// ---------------------------------------------------------------------
//  Save enquiry
// ---------------------------------------------------------------------
try {
    $stmt = $pdo->prepare(
        'INSERT INTO enquiries (name, email, project_type, story_details, target_date, preferred_contact, contact_handle, reference_files_json, status, ip_address)
         VALUES (:name, :email, :type, :details, :target, :contact, :handle, :files, :status, :ip)'
    );
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':type'    => $projectType,
        ':details' => $details,
        ':target'  => $targetDate,
        ':contact' => $contact !== '' ? $contact : null,
        ':handle'  => $handle !== '' ? $handle : null,
        ':files'   => json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':status'  => 'New',
        ':ip'      => $ip,
    ]);
    $enquiryId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    foreach ($stored as $s) {
        @unlink(UPLOAD_ENQUIRY_DIR . '/' . $s['file']);
    }
    error_log('Nena enquiry insert failed: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Something went wrong while saving your enquiry. Please try again or email the studio directly.']);
}

// ---------------------------------------------------------------------
//  Notify the studio (best effort — never blocks the visitor)
// ---------------------------------------------------------------------
$notify = setting('notification_email', setting('contact_email'));
if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
    $host = preg_replace('/^www\./', '', parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
    $subject = '=?UTF-8?B?' . base64_encode('New Nena enquiry #' . $enquiryId . ' — ' . $projectType) . '?=';
    $body = "A new enquiry has arrived at Nena Studio.\n\n"
        . "Name: {$name}\nEmail: {$email}\nProject type: {$projectType}\n"
        . 'Needed by: ' . ($targetDate ?? 'Not specified') . "\n"
        . 'Preferred contact: ' . ($contact !== '' ? $contact : 'Not specified') . ($handle !== '' ? " ({$handle})" : '') . "\n"
        . 'Reference files: ' . count($stored) . "\n\n"
        . "What they have in mind:\n{$details}\n\n"
        . 'Manage it here: ' . abs_url('admin/index.php?tab=enquiries&view=' . $enquiryId) . "\n";
    $safeName = preg_replace('/[\r\n"]+/', '', $name);
    $headers = [
        'From: Nena Studio <no-reply@' . $host . '>',
        'Reply-To: "' . $safeName . '" <' . $email . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: NenaStudio',
    ];
    @mail($notify, $subject, $body, implode("\r\n", $headers));
}

respond(200, [
    'success' => true,
    'message' => str_replace('{name}', $name, content('form_success_text')),
]);

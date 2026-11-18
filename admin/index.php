<?php
/**
 * Nena Studio Admin — Dashboard, Enquiries Manager, Portfolio (Behind the Stitches) CRUD,
 * SEO & Site Settings and password management.
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_admin();

$pdo = db();
$tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($tab, ['dashboard', 'enquiries', 'content', 'portfolio', 'settings'], true)) {
    $tab = 'dashboard';
}

// =====================================================================
//  POST actions (Post/Redirect/Get)
// =====================================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'The upload was larger than the server allows (post_max_size = ' . ini_get('post_max_size') . '). Try smaller or fewer files.');
        redirect($_SERVER['REQUEST_URI'] ?? admin_url());
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash('error', 'Your session expired. Please try again.');
        redirect(admin_url(['tab' => $tab]));
    }

    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        // ---------------------------------------------------- Enquiries
        case 'enquiry_update':
            $id = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'New');
            if (!in_array($status, enquiry_statuses(), true)) {
                $status = 'New';
            }
            $notes = mb_substr(trim((string) ($_POST['admin_notes'] ?? '')), 0, 10000);
            $pdo->prepare('UPDATE enquiries SET status = :s, admin_notes = :n WHERE id = :id')
                ->execute([':s' => $status, ':n' => $notes, ':id' => $id]);
            flash('success', 'Enquiry #' . $id . ' updated.');
            redirect(admin_url(['tab' => 'enquiries', 'view' => $id]));

        case 'enquiry_status':
            $id = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            if (in_array($status, enquiry_statuses(), true)) {
                $pdo->prepare('UPDATE enquiries SET status = :s WHERE id = :id')->execute([':s' => $status, ':id' => $id]);
                flash('success', 'Enquiry #' . $id . ' marked as "' . $status . '".');
            }
            redirect(admin_url(array_filter(['tab' => 'enquiries', 'status' => $_POST['return_status'] ?? ''])));

        case 'enquiry_delete':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT reference_files_json FROM enquiries WHERE id = :id');
            $stmt->execute([':id' => $id]);
            foreach (json_decode((string) $stmt->fetchColumn(), true) ?: [] as $file) {
                $name = basename((string) ($file['file'] ?? ''));
                if ($name !== '' && is_file(UPLOAD_ENQUIRY_DIR . '/' . $name)) {
                    @unlink(UPLOAD_ENQUIRY_DIR . '/' . $name);
                }
            }
            $pdo->prepare('DELETE FROM enquiries WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Enquiry #' . $id . ' and its files were deleted.');
            redirect(admin_url(['tab' => 'enquiries']));

        // ---------------------------------------------------- Portfolio
        case 'project_save':
            $id = (int) ($_POST['id'] ?? 0);
            $existing = null;
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT * FROM portfolio_projects WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $existing = $stmt->fetch() ?: null;
                if (!$existing) {
                    flash('error', 'Project not found.');
                    redirect(admin_url(['tab' => 'portfolio']));
                }
            }

            $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190);
            $slug = slugify((string) ($_POST['slug'] ?? '') !== '' ? (string) $_POST['slug'] : $title);
            $category = (string) ($_POST['category'] ?? 'weddings');
            if (!array_key_exists($category, project_categories())) {
                $category = 'weddings';
            }
            $summary = mb_substr(trim((string) ($_POST['short_summary'] ?? '')), 0, 500);
            $story = trim((string) ($_POST['full_story'] ?? ''));
            $alt = mb_substr(trim((string) ($_POST['image_alt'] ?? '')), 0, 255);
            $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
            $published = isset($_POST['is_published']) ? 1 : 0;
            $sort = (int) ($_POST['sort_order'] ?? 0);
            $createdAt = trim((string) ($_POST['created_at'] ?? ''));

            $_SESSION['project_form'] = $_POST;
            $back = admin_url(['tab' => 'portfolio', $id ? 'edit' : 'new' => $id ?: 1]);

            if ($title === '') {
                flash('error', 'Please add a title.');
                redirect($back);
            }
            if ($videoUrl !== '' && !preg_match('#^(https?://|uploads/portfolio/)#i', $videoUrl)) {
                flash('error', 'The video URL must start with https:// (or be an uploaded file).');
                redirect($back);
            }

            // Unique slug
            $base = $slug;
            $n = 2;
            $check = $pdo->prepare('SELECT COUNT(*) FROM portfolio_projects WHERE slug = :s AND id <> :id');
            while (true) {
                $check->execute([':s' => $slug, ':id' => $id]);
                if ((int) $check->fetchColumn() === 0) {
                    break;
                }
                $slug = $base . '-' . $n++;
            }

            $featured = $existing['featured_image'] ?? null;
            $gallery = json_list($existing['media_gallery_json'] ?? null);
            $video = $videoUrl !== '' ? $videoUrl : null;
            $newFiles = [];

            try {
                // Remove selected gallery images
                $remove = array_map('strval', (array) ($_POST['remove_gallery'] ?? []));
                if ($remove) {
                    $gallery = array_values(array_diff($gallery, $remove));
                }
                if (isset($_POST['remove_featured'])) {
                    $featured = null;
                }

                // Featured image upload
                if (!empty($_FILES['featured_image']) && ($_FILES['featured_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $file = store_upload($_FILES['featured_image'], allowed_image_mimes(), PORTFOLIO_MAX_IMAGE_BYTES, UPLOAD_PORTFOLIO_DIR);
                    $newFiles[] = $file;
                    $featured = 'uploads/portfolio/' . $file;
                }

                // Gallery uploads
                foreach (normalize_files($_FILES['gallery_images'] ?? []) as $g) {
                    $file = store_upload($g, allowed_image_mimes(), PORTFOLIO_MAX_IMAGE_BYTES, UPLOAD_PORTFOLIO_DIR);
                    $newFiles[] = $file;
                    $gallery[] = 'uploads/portfolio/' . $file;
                }

                // Video upload (mp4/webm)
                if (!empty($_FILES['video_file']) && ($_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $file = store_upload($_FILES['video_file'], allowed_video_mimes(), PORTFOLIO_MAX_VIDEO_BYTES, UPLOAD_PORTFOLIO_DIR);
                    $newFiles[] = $file;
                    $video = 'uploads/portfolio/' . $file;
                }
            } catch (RuntimeException $e) {
                foreach ($newFiles as $f) {
                    @unlink(UPLOAD_PORTFOLIO_DIR . '/' . $f);
                }
                flash('error', $e->getMessage());
                redirect($back);
            }

            // Use first gallery image as cover if none set
            if (!$featured && $gallery) {
                $featured = $gallery[0];
            }
            // Keep the cover inside the gallery list for the lightbox
            if ($featured && !in_array($featured, $gallery, true)) {
                array_unshift($gallery, $featured);
            }

            $params = [
                ':title'    => $title,
                ':slug'     => $slug,
                ':category' => $category,
                ':summary'  => $summary,
                ':story'    => $story,
                ':alt'      => $alt,
                ':featured' => $featured,
                ':video'    => $video,
                ':gallery'  => json_encode(array_values(array_unique($gallery)), JSON_UNESCAPED_SLASHES),
                ':pub'      => $published,
                ':sort'     => $sort,
            ];
            $dateSql = '';
            if ($createdAt !== '' && ($d = DateTime::createFromFormat('Y-m-d', $createdAt))) {
                $params[':created'] = $d->format('Y-m-d') . ' 10:00:00';
                $dateSql = ', created_at = :created';
            }

            if ($existing) {
                $params[':id'] = $id;
                $pdo->prepare("UPDATE portfolio_projects SET title = :title, slug = :slug, category = :category, short_summary = :summary,
                               full_story = :story, image_alt = :alt, featured_image = :featured, video_url = :video,
                               media_gallery_json = :gallery, is_published = :pub, sort_order = :sort{$dateSql} WHERE id = :id")
                    ->execute($params);

                // Clean up files no longer referenced
                $old = array_merge(json_list($existing['media_gallery_json']), [$existing['featured_image'], $existing['video_url']]);
                $now = array_merge($gallery, [$featured, $video]);
                foreach (array_diff(array_filter($old), array_filter($now)) as $orphan) {
                    if (str_starts_with((string) $orphan, 'uploads/portfolio/')) {
                        @unlink(ROOT_PATH . '/uploads/portfolio/' . basename((string) $orphan));
                    }
                }
                flash('success', '"' . $title . '" was updated.');
            } else {
                $cols = 'title, slug, category, short_summary, full_story, image_alt, featured_image, video_url, media_gallery_json, is_published, sort_order';
                $vals = ':title, :slug, :category, :summary, :story, :alt, :featured, :video, :gallery, :pub, :sort';
                if ($dateSql !== '') {
                    $cols .= ', created_at';
                    $vals .= ', :created';
                }
                $pdo->prepare("INSERT INTO portfolio_projects ({$cols}) VALUES ({$vals})")->execute($params);
                $id = (int) $pdo->lastInsertId();
                flash('success', '"' . $title . '" was added to Behind the Stitches.');
            }
            unset($_SESSION['project_form']);
            redirect(admin_url(['tab' => 'portfolio', 'edit' => $id]));

        case 'project_delete':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM portfolio_projects WHERE id = :id');
            $stmt->execute([':id' => $id]);
            if ($p = $stmt->fetch()) {
                $files = array_merge(json_list($p['media_gallery_json']), [$p['featured_image'], $p['video_url']]);
                foreach (array_unique(array_filter($files)) as $f) {
                    if (str_starts_with((string) $f, 'uploads/portfolio/')) {
                        @unlink(ROOT_PATH . '/uploads/portfolio/' . basename((string) $f));
                    }
                }
                $pdo->prepare('DELETE FROM portfolio_projects WHERE id = :id')->execute([':id' => $id]);
                flash('success', '"' . $p['title'] . '" was deleted.');
            }
            redirect(admin_url(['tab' => 'portfolio']));

        case 'project_toggle':
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE portfolio_projects SET is_published = 1 - is_published WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Visibility updated.');
            redirect(admin_url(['tab' => 'portfolio']));

        // ---------------------------------------------------- Website texts
        case 'content_save':
            $groupKey = (string) ($_POST['group'] ?? '');
            $schema = content_schema();
            if (!isset($schema[$groupKey])) {
                flash('error', 'Unknown section.');
                redirect(admin_url(['tab' => 'content']));
            }
            $posted = (array) ($_POST['content'] ?? []);
            foreach ($schema[$groupKey]['fields'] as $key => [$label, $type, $default]) {
                $value = str_replace("\r\n", "\n", trim((string) ($posted[$key] ?? '')));
                $value = mb_substr($type === 'text' || $type === 'inline' ? preg_replace('/\s+/u', ' ', $value) : $value, 0, 10000);
                // Store an empty value when it matches the default, so the default keeps applying
                save_setting('content_' . $key, $value === $default ? '' : $value);
            }
            flash('success', '"' . $schema[$groupKey]['title'] . '" texts saved.');
            redirect(admin_url(['tab' => 'content', 'group' => $groupKey]) . '#group-' . $groupKey);

        // ---------------------------------------------------- Settings
        case 'settings_save':
            $fields = [
                'site_name' => 120, 'meta_title' => 160, 'meta_description' => 320, 'meta_keywords' => 500,
                'canonical_url' => 255, 'og_image' => 255, 'contact_email' => 190, 'notification_email' => 190,
                'contact_phone' => 40, 'whatsapp_number' => 40, 'instagram_url' => 255, 'facebook_url' => 255,
                'pinterest_url' => 255, 'address_city' => 120, 'address_region' => 120, 'address_country' => 2,
                'geo_latitude' => 20, 'geo_longitude' => 20, 'google_site_verification' => 120,
            ];
            $errors = [];
            foreach (['contact_email', 'notification_email'] as $k) {
                $v = trim((string) ($_POST[$k] ?? ''));
                if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Please enter a valid ' . str_replace('_', ' ', $k) . '.';
                }
            }
            foreach (['canonical_url', 'instagram_url', 'facebook_url', 'pinterest_url'] as $k) {
                $v = trim((string) ($_POST[$k] ?? ''));
                if ($v !== '' && !preg_match('#^https?://#i', $v)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $k)) . ' must start with https://';
                }
            }
            foreach (['geo_latitude', 'geo_longitude'] as $k) {
                $v = trim((string) ($_POST[$k] ?? ''));
                if ($v !== '' && !is_numeric($v)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $k)) . ' must be a number.';
                }
            }
            if ($errors) {
                foreach ($errors as $err) {
                    flash('error', $err);
                }
                redirect(admin_url(['tab' => 'settings']));
            }
            foreach ($fields as $key => $max) {
                $value = mb_substr(trim(strip_tags((string) ($_POST[$key] ?? ''))), 0, $max);
                if ($key === 'canonical_url') {
                    $value = rtrim($value, '/');
                }
                if ($key === 'address_country') {
                    $value = strtoupper($value !== '' ? $value : 'NL');
                }
                save_setting($key, $value);
            }
            // Optional OG image upload
            if (!empty($_FILES['og_image_file']) && ($_FILES['og_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $file = store_upload($_FILES['og_image_file'], allowed_image_mimes(), PORTFOLIO_MAX_IMAGE_BYTES, UPLOAD_PORTFOLIO_DIR);
                    save_setting('og_image', 'uploads/portfolio/' . $file);
                } catch (RuntimeException $e) {
                    flash('error', 'Social image: ' . $e->getMessage());
                }
            }
            flash('success', 'Settings saved.');
            redirect(admin_url(['tab' => 'settings']));

        case 'password_change':
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = :id');
            $stmt->execute([':id' => $_SESSION['admin_id']]);
            $hash = (string) $stmt->fetchColumn();
            if (!password_verify($current, $hash)) {
                flash('error', 'Your current password is incorrect.');
            } elseif (strlen($new) < 10) {
                flash('error', 'The new password must be at least 10 characters.');
            } elseif ($new !== $confirm) {
                flash('error', 'The new passwords do not match.');
            } else {
                $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id')
                    ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $_SESSION['admin_id']]);
                session_regenerate_id(true);
                flash('success', 'Password changed successfully.');
            }
            redirect(admin_url(['tab' => 'settings']) . '#password');

        case 'account_update':
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $username)) {
                flash('error', 'Username must be 3–60 characters (letters, numbers, . _ -).');
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Please enter a valid email address.');
            } else {
                try {
                    $pdo->prepare('UPDATE admins SET username = :u, email = :e WHERE id = :id')
                        ->execute([':u' => $username, ':e' => $email !== '' ? $email : null, ':id' => $_SESSION['admin_id']]);
                    $_SESSION['admin_username'] = $username;
                    flash('success', 'Account details updated.');
                } catch (PDOException $e) {
                    flash('error', 'That username is already taken.');
                }
            }
            redirect(admin_url(['tab' => 'settings']) . '#password');
    }

    flash('error', 'Unknown action.');
    redirect(admin_url(['tab' => $tab]));
}

// =====================================================================
//  Views
// =====================================================================
$titles = [
    'dashboard' => 'Dashboard',
    'enquiries' => 'Enquiries',
    'content'   => 'Website Texts',
    'portfolio' => 'Behind the Stitches',
    'settings'  => 'SEO & Site Settings',
];
admin_layout_start($titles[$tab], $tab);

$token = csrf_token();
$categories = project_categories();

// ---------------------------------------------------------------- Dashboard
if ($tab === 'dashboard'):
    $counts = array_fill_keys(enquiry_statuses(), 0);
    foreach ($pdo->query('SELECT status, COUNT(*) c FROM enquiries GROUP BY status') as $r) {
        $counts[$r['status']] = (int) $r['c'];
    }
    $projectCount = (int) $pdo->query('SELECT COUNT(*) FROM portfolio_projects')->fetchColumn();
    $recent = $pdo->query('SELECT id, name, project_type, status, created_at FROM enquiries ORDER BY created_at DESC LIMIT 6')->fetchAll();
    ?>
    <section class="stats">
        <?php foreach ($counts as $status => $c): ?>
        <a class="stat <?= e(status_class($status)) ?>" href="<?= e(admin_url(['tab' => 'enquiries', 'status' => $status])) ?>">
            <span class="stat__num"><?= $c ?></span>
            <span class="stat__label"><?= e($status) ?></span>
        </a>
        <?php endforeach; ?>
        <a class="stat stat--projects" href="<?= e(admin_url(['tab' => 'portfolio'])) ?>">
            <span class="stat__num"><?= $projectCount ?></span>
            <span class="stat__label">Stories</span>
        </a>
    </section>

    <div class="grid-2">
        <section class="panel">
            <div class="panel__head">
                <h2>Latest enquiries</h2>
                <a href="<?= e(admin_url(['tab' => 'enquiries'])) ?>">View all →</a>
            </div>
            <?php if (!$recent): ?>
            <p class="empty">No enquiries yet. They'll appear here as soon as someone shares their story.</p>
            <?php else: ?>
            <ul class="list">
                <?php foreach ($recent as $r): ?>
                <li>
                    <a href="<?= e(admin_url(['tab' => 'enquiries', 'view' => $r['id']])) ?>">
                        <strong><?= e($r['name']) ?></strong>
                        <span><?= e($r['project_type']) ?> · <?= e(fmt_dt($r['created_at'], 'j M, H:i')) ?></span>
                    </a>
                    <span class="status <?= e(status_class($r['status'])) ?>"><?= e($r['status']) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel__head"><h2>Quick actions</h2></div>
            <div class="quick">
                <a class="a-btn a-btn--primary" href="<?= e(admin_url(['tab' => 'portfolio', 'new' => 1])) ?>">+ Add a new story</a>
                <a class="a-btn" href="<?= e(admin_url(['tab' => 'enquiries', 'status' => 'New'])) ?>">Review new enquiries</a>
                <a class="a-btn" href="<?= e(admin_url(['tab' => 'content'])) ?>">Edit website texts</a>
                <a class="a-btn" href="<?= e(admin_url(['tab' => 'settings'])) ?>">Edit SEO settings</a>
                <a class="a-btn" href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener">Open sitemap.xml ↗</a>
            </div>
            <p class="hint">Tip: change the default password in <a href="<?= e(admin_url(['tab' => 'settings'])) ?>#password">Settings</a> if you haven't already.</p>
        </section>
    </div>
<?php

// ---------------------------------------------------------------- Enquiries
elseif ($tab === 'enquiries'):
    $viewId = (int) ($_GET['view'] ?? 0);

    if ($viewId > 0):
        $stmt = $pdo->prepare('SELECT * FROM enquiries WHERE id = :id');
        $stmt->execute([':id' => $viewId]);
        $q = $stmt->fetch();
        if (!$q): ?>
            <div class="alert alert--error">Enquiry not found.</div>
            <a class="a-btn" href="<?= e(admin_url(['tab' => 'enquiries'])) ?>">← Back to enquiries</a>
        <?php else:
            $files = json_decode((string) $q['reference_files_json'], true) ?: [];
            ?>
            <a class="back" href="<?= e(admin_url(['tab' => 'enquiries'])) ?>">← All enquiries</a>
            <div class="grid-2 grid-2--wide">
                <section class="panel">
                    <div class="panel__head">
                        <h2>#<?= (int) $q['id'] ?> · <?= e($q['name']) ?></h2>
                        <span class="status <?= e(status_class($q['status'])) ?>"><?= e($q['status']) ?></span>
                    </div>
                    <dl class="details">
                        <dt>Email</dt><dd><a href="mailto:<?= e($q['email']) ?>?subject=<?= rawurlencode('Your Nena Studio enquiry') ?>"><?= e($q['email']) ?></a></dd>
                        <dt>Project type</dt><dd><?= e($q['project_type']) ?></dd>
                        <dt>Needed by</dt><dd><?= $q['target_date'] ? e(date('l j F Y', strtotime($q['target_date']))) : '—' ?></dd>
                        <dt>Preferred contact</dt>
                        <dd>
                            <?= e($q['preferred_contact'] ?: 'Not specified') ?>
                            <?php if ($q['contact_handle']): ?>
                                · <?php if ($q['preferred_contact'] === 'WhatsApp / Phone'): ?>
                                    <a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $q['contact_handle'])) ?>" target="_blank" rel="noopener"><?= e($q['contact_handle']) ?></a>
                                <?php elseif ($q['preferred_contact'] === 'Instagram'): ?>
                                    <a href="https://instagram.com/<?= e(ltrim($q['contact_handle'], '@')) ?>" target="_blank" rel="noopener"><?= e($q['contact_handle']) ?></a>
                                <?php else: ?><?= e($q['contact_handle']) ?><?php endif; ?>
                            <?php endif; ?>
                        </dd>
                        <dt>Received</dt><dd><?= e(fmt_dt($q['created_at'], 'l j F Y, H:i')) ?></dd>
                    </dl>
                    <h3>What they have in mind</h3>
                    <div class="message"><?= nl2br(e($q['story_details'])) ?></div>

                    <h3>Inspiration &amp; references (<?= count($files) ?>)</h3>
                    <?php if (!$files): ?>
                    <p class="empty">No files attached.</p>
                    <?php else: ?>
                    <ul class="files">
                        <?php foreach ($files as $f):
                            $fname = (string) ($f['file'] ?? '');
                            $src = url('admin/file.php') . '?' . http_build_query(['id' => $q['id'], 'f' => $fname]);
                            $isImg = (bool) preg_match('/\.(jpg|png|webp|gif)$/', $fname);
                            ?>
                        <li class="file">
                            <a href="<?= e($src) ?>" target="_blank" rel="noopener" class="file__thumb">
                                <?php if ($isImg): ?><img src="<?= e($src) ?>" alt="<?= e($f['original'] ?? $fname) ?>" loading="lazy"><?php else: ?><span>PDF</span><?php endif; ?>
                            </a>
                            <span class="file__name" title="<?= e($f['original'] ?? $fname) ?>"><?= e($f['original'] ?? $fname) ?></span>
                            <a class="file__dl" href="<?= e($src . '&download=1') ?>">Download</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <div class="panel__head"><h2>Manage</h2></div>
                    <form method="post" class="form-stack">
                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="enquiry_update">
                        <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                        <label>Status
                            <select name="status">
                                <?php foreach (enquiry_statuses() as $s): ?>
                                <option value="<?= e($s) ?>" <?= $q['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Admin notes <small>(private — quotes, measurements, materials, next steps…)</small>
                            <textarea name="admin_notes" rows="10"><?= e($q['admin_notes']) ?></textarea>
                        </label>
                        <button class="a-btn a-btn--primary" type="submit">Save changes</button>
                    </form>
                    <a class="a-btn a-btn--block reply" href="mailto:<?= e($q['email']) ?>?subject=<?= rawurlencode('Your Nena Studio enquiry') ?>&body=<?= rawurlencode('Dear ' . $q['name'] . ",\n\nThank you so much for sharing your story with Nena Studio.\n\n") ?>">Reply by email</a>
                    <form method="post" data-confirm="Delete this enquiry and all its uploaded files permanently?" class="danger-zone">
                        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="enquiry_delete">
                        <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                        <button class="a-btn a-btn--danger a-btn--block" type="submit">Delete enquiry</button>
                    </form>
                </section>
            </div>
        <?php endif;
    else:
        $statusFilter = (string) ($_GET['status'] ?? '');
        $search = trim((string) ($_GET['q'] ?? ''));
        $where = [];
        $params = [];
        if (in_array($statusFilter, enquiry_statuses(), true)) {
            $where[] = 'status = :status';
            $params[':status'] = $statusFilter;
        } else {
            $statusFilter = '';
        }
        if ($search !== '') {
            $where[] = '(name LIKE :q1 OR email LIKE :q2 OR story_details LIKE :q3)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM enquiries' . $sqlWhere);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $stmt = $pdo->prepare('SELECT id, name, email, project_type, target_date, status, reference_files_json, created_at FROM enquiries'
            . $sqlWhere . ' ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        ?>
        <div class="toolbar">
            <nav class="chips" aria-label="Filter by status">
                <a class="chip <?= $statusFilter === '' ? 'is-active' : '' ?>" href="<?= e(admin_url(['tab' => 'enquiries'])) ?>">All</a>
                <?php foreach (enquiry_statuses() as $s): ?>
                <a class="chip <?= $statusFilter === $s ? 'is-active' : '' ?>" href="<?= e(admin_url(['tab' => 'enquiries', 'status' => $s])) ?>"><?= e($s) ?></a>
                <?php endforeach; ?>
            </nav>
            <form class="search" method="get">
                <input type="hidden" name="tab" value="enquiries">
                <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email or story…" aria-label="Search enquiries">
                <button class="a-btn" type="submit">Search</button>
            </form>
        </div>

        <section class="panel panel--flush">
            <?php if (!$rows): ?>
            <p class="empty">No enquiries found.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Project</th><th>Needed by</th><th>Files</th><th>Received</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): $fc = count(json_decode((string) $r['reference_files_json'], true) ?: []); ?>
                        <tr class="<?= $r['status'] === 'New' ? 'is-new' : '' ?>">
                            <td data-label="#"><?= (int) $r['id'] ?></td>
                            <td data-label="Name"><a href="<?= e(admin_url(['tab' => 'enquiries', 'view' => $r['id']])) ?>"><strong><?= e($r['name']) ?></strong></a><br><small><?= e($r['email']) ?></small></td>
                            <td data-label="Project"><?= e($r['project_type']) ?></td>
                            <td data-label="Needed by"><?= $r['target_date'] ? e(date('j M Y', strtotime($r['target_date']))) : '—' ?></td>
                            <td data-label="Files"><?= $fc ?: '—' ?></td>
                            <td data-label="Received"><?= e(fmt_dt($r['created_at'], 'j M Y')) ?></td>
                            <td data-label="Status">
                                <form method="post" class="inline-status">
                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="action" value="enquiry_status">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                                    <select name="status" class="status-select <?= e(status_class($r['status'])) ?>" onchange="this.form.submit()" aria-label="Status for enquiry <?= (int) $r['id'] ?>">
                                        <?php foreach (enquiry_statuses() as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <noscript><button type="submit">Save</button></noscript>
                                </form>
                            </td>
                            <td data-label=""><a class="a-btn a-btn--sm" href="<?= e(admin_url(['tab' => 'enquiries', 'view' => $r['id']])) ?>">Open</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= e(admin_url(array_filter(['tab' => 'enquiries', 'status' => $statusFilter, 'q' => $search, 'page' => $i]))) ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif;

// ---------------------------------------------------------------- Portfolio
elseif ($tab === 'portfolio'):
    $editId = (int) ($_GET['edit'] ?? 0);
    $isNew = isset($_GET['new']);

    if ($editId > 0 || $isNew):
        $p = [
            'id' => 0, 'title' => '', 'slug' => '', 'category' => 'weddings', 'short_summary' => '', 'full_story' => '',
            'image_alt' => '', 'featured_image' => '', 'video_url' => '', 'media_gallery_json' => '[]', 'is_published' => 1,
            'sort_order' => 0, 'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        if ($editId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM portfolio_projects WHERE id = :id');
            $stmt->execute([':id' => $editId]);
            $found = $stmt->fetch();
            if (!$found) {
                echo '<div class="alert alert--error">Project not found.</div>';
                admin_layout_end();
                exit;
            }
            $p = $found;
        }
        // Restore input after a validation error
        if (!empty($_SESSION['project_form'])) {
            foreach (['title', 'slug', 'category', 'short_summary', 'full_story', 'image_alt', 'video_url', 'sort_order'] as $k) {
                if (isset($_SESSION['project_form'][$k])) {
                    $p[$k] = (string) $_SESSION['project_form'][$k];
                }
            }
            unset($_SESSION['project_form']);
        }
        $gallery = json_list($p['media_gallery_json']);
        $videoIsUpload = str_starts_with((string) $p['video_url'], 'uploads/portfolio/');
        ?>
        <a class="back" href="<?= e(admin_url(['tab' => 'portfolio'])) ?>">← All stories</a>
        <form method="post" enctype="multipart/form-data" class="project-form">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="project_save">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">

            <div class="grid-2 grid-2--wide">
                <section class="panel form-stack">
                    <div class="panel__head"><h2><?= $p['id'] ? 'Edit story' : 'New story' ?></h2>
                        <?php if ($p['id'] && $p['is_published']): ?><a href="<?= e(project_url($p['slug'])) ?>" target="_blank" rel="noopener">View live ↗</a><?php endif; ?>
                    </div>
                    <label>Title *
                        <input type="text" name="title" id="title" value="<?= e($p['title']) ?>" required maxlength="190" data-slug-source="slug">
                    </label>
                    <label>URL slug <small>/stories/<b>your-slug</b> — lowercase, numbers and dashes</small>
                        <input type="text" name="slug" id="slug" value="<?= e($p['slug']) ?>" maxlength="180" pattern="[a-z0-9\-]*">
                    </label>
                    <div class="row-2">
                        <label>Category
                            <select name="category">
                                <?php foreach ($categories as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $p['category'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Date
                            <input type="date" name="created_at" value="<?= e(substr((string) $p['created_at'], 0, 10)) ?>">
                        </label>
                    </div>
                    <label>Short summary <small>(shown on cards & used as the meta description — ideally 120–160 characters)</small>
                        <textarea name="short_summary" rows="3" maxlength="500" data-count="sumCount" data-max="160"><?= e($p['short_summary']) ?></textarea>
                        <span class="counter" id="sumCount"></span>
                    </label>
                    <label>Full story
                        <small>Blank line = new paragraph. Start a line with <code>## </code> for a sub-heading (e.g. <code>## Where the idea came from</code>).</small>
                        <textarea name="full_story" rows="18" class="mono"><?= e($p['full_story']) ?></textarea>
                    </label>
                </section>

                <aside class="form-stack">
                    <section class="panel form-stack">
                        <div class="panel__head"><h2>Publishing</h2></div>
                        <label class="check"><input type="checkbox" name="is_published" value="1" <?= $p['is_published'] ? 'checked' : '' ?>> Published (visible on the website)</label>
                        <label>Sort order <small>(lower numbers appear first)</small>
                            <input type="number" name="sort_order" value="<?= (int) $p['sort_order'] ?>" step="1">
                        </label>
                        <button class="a-btn a-btn--primary a-btn--block" type="submit"><?= $p['id'] ? 'Save changes' : 'Create story' ?></button>
                    </section>

                    <section class="panel form-stack">
                        <div class="panel__head"><h2>Cover photo</h2></div>
                        <?php if ($p['featured_image']): ?>
                        <figure class="cover-preview">
                            <img src="<?= e(media_url($p['featured_image'])) ?>" alt="">
                            <label class="check"><input type="checkbox" name="remove_featured" value="1"> Remove cover</label>
                        </figure>
                        <?php endif; ?>
                        <label>Upload <?= $p['featured_image'] ? 'a new ' : '' ?>cover <small>JPG, PNG, WEBP or GIF · max <?= (int) (PORTFOLIO_MAX_IMAGE_BYTES / 1048576) ?> MB · 4:3 works best</small>
                            <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="coverPreview">
                        </label>
                        <div class="upload-preview" id="coverPreview"></div>
                        <label>SEO alt text <small>(describe the photo for Google & screen readers)</small>
                            <input type="text" name="image_alt" value="<?= e($p['image_alt']) ?>" maxlength="255" placeholder="e.g. Embroidered bridal veil with birth flowers">
                        </label>
                    </section>

                    <section class="panel form-stack">
                        <div class="panel__head"><h2>Gallery</h2></div>
                        <?php if ($gallery): ?>
                        <ul class="gallery-admin">
                            <?php foreach ($gallery as $g): ?>
                            <li>
                                <img src="<?= e(media_url($g)) ?>" alt="" loading="lazy">
                                <label class="check check--sm"><input type="checkbox" name="remove_gallery[]" value="<?= e($g) ?>"> Remove</label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <label>Add gallery photos <small>(select several at once)</small>
                            <input type="file" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" data-preview="galleryPreview">
                        </label>
                        <div class="upload-preview" id="galleryPreview"></div>
                    </section>

                    <section class="panel form-stack">
                        <div class="panel__head"><h2>Process video</h2></div>
                        <?php if ($p['video_url']): ?>
                        <p class="hint">Current: <code><?= e($p['video_url']) ?></code></p>
                        <?php if ($videoIsUpload): ?>
                        <video src="<?= e(media_url($p['video_url'])) ?>" controls preload="metadata" class="video-preview"></video>
                        <?php endif; ?>
                        <?php endif; ?>
                        <label>Upload video <small>MP4 or WEBM · max <?= (int) (PORTFOLIO_MAX_VIDEO_BYTES / 1048576) ?> MB (server limit: <?= e(ini_get('upload_max_filesize')) ?>)</small>
                            <input type="file" name="video_file" accept="video/mp4,video/webm">
                        </label>
                        <label>…or video URL <small>(YouTube, Vimeo or a direct .mp4 link — leave empty to remove)</small>
                            <input type="text" name="video_url" value="<?= e($p['video_url']) ?>" placeholder="https://youtu.be/…">
                        </label>
                    </section>
                </aside>
            </div>
        </form>

        <?php if ($p['id']): ?>
        <form method="post" data-confirm="Delete this story and all its uploaded media permanently?" class="danger-zone panel">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="project_delete">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <div><strong>Delete this story</strong><p class="hint">This removes the story and its uploaded photos and video. It cannot be undone.</p></div>
            <button class="a-btn a-btn--danger" type="submit">Delete story</button>
        </form>
        <?php endif; ?>
    <?php else:
        $rows = $pdo->query('SELECT id, title, slug, category, featured_image, image_alt, video_url, is_published, sort_order, created_at FROM portfolio_projects ORDER BY sort_order ASC, created_at DESC')->fetchAll();
        ?>
        <div class="toolbar">
            <p class="hint">These stories appear in the “Behind the Stitches” section and each has its own SEO page.</p>
            <a class="a-btn a-btn--primary" href="<?= e(admin_url(['tab' => 'portfolio', 'new' => 1])) ?>">+ Add a new story</a>
        </div>
        <?php if (!$rows): ?>
        <section class="panel"><p class="empty">No stories yet. Add your first one!</p></section>
        <?php else: ?>
        <div class="cards">
            <?php foreach ($rows as $r): ?>
            <article class="card <?= $r['is_published'] ? '' : 'is-draft' ?>">
                <a href="<?= e(admin_url(['tab' => 'portfolio', 'edit' => $r['id']])) ?>" class="card__media">
                    <?php if ($r['featured_image']): ?><img src="<?= e(media_url($r['featured_image'])) ?>" alt="<?= e($r['image_alt']) ?>" loading="lazy"><?php endif; ?>
                    <?php if (!$r['is_published']): ?><span class="card__badge">Draft</span><?php endif; ?>
                    <?php if ($r['video_url']): ?><span class="card__badge card__badge--video">Video</span><?php endif; ?>
                </a>
                <div class="card__body">
                    <small><?= e($categories[$r['category']] ?? $r['category']) ?> · #<?= (int) $r['sort_order'] ?></small>
                    <h3><a href="<?= e(admin_url(['tab' => 'portfolio', 'edit' => $r['id']])) ?>"><?= e($r['title']) ?></a></h3>
                    <div class="card__actions">
                        <a class="a-btn a-btn--sm" href="<?= e(admin_url(['tab' => 'portfolio', 'edit' => $r['id']])) ?>">Edit</a>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                            <input type="hidden" name="action" value="project_toggle">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="a-btn a-btn--sm" type="submit"><?= $r['is_published'] ? 'Unpublish' : 'Publish' ?></button>
                        </form>
                        <?php if ($r['is_published']): ?><a class="a-btn a-btn--sm a-btn--ghost" href="<?= e(project_url($r['slug'])) ?>" target="_blank" rel="noopener">View ↗</a><?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif;

// ---------------------------------------------------------------- Website texts
elseif ($tab === 'content'):
    $openGroup = (string) ($_GET['group'] ?? '');
    ?>
    <section class="panel content-help">
        <p>Edit every text on the website here. Each section saves separately.</p>
        <ul class="hint">
            <li>In multi-line fields, leave a <strong>blank line</strong> to start a new paragraph.</li>
            <li>In the main headline, wrap words in <code>*stars*</code> to show them in the copper italic style.</li>
            <li>Empty a field and save to restore its original text.</li>
        </ul>
    </section>
    <?php foreach (content_schema() as $groupKey => $group): ?>
    <details class="panel content-group" id="group-<?= e($groupKey) ?>" <?= $openGroup === $groupKey ? 'open' : '' ?>>
        <summary><h2><?= e($group['title']) ?></h2><span class="content-group__count"><?= count($group['fields']) ?> texts</span></summary>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="content_save">
            <input type="hidden" name="group" value="<?= e($groupKey) ?>">
            <?php foreach ($group['fields'] as $key => [$label, $type, $default]):
                $value = content($key);
                $changed = $value !== $default;
                ?>
            <label>
                <span><?= e($label) ?><?php if ($changed): ?> <small class="content-edited">· edited</small><?php endif; ?></span>
                <?php if ($type === 'text' || $type === 'inline'): ?>
                <input type="text" name="content[<?= e($key) ?>]" value="<?= e($value) ?>" maxlength="10000">
                <?php else: ?>
                <textarea name="content[<?= e($key) ?>]" rows="<?= max(3, min(16, (int) ceil(mb_strlen($value) / 90) + substr_count($value, "\n"))) ?>"><?= e($value) ?></textarea>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
            <div><button class="a-btn a-btn--primary" type="submit">Save “<?= e($group['title']) ?>”</button></div>
        </form>
    </details>
    <?php endforeach; ?>
<?php

// ---------------------------------------------------------------- Settings
elseif ($tab === 'settings'):
    $s = settings(true);
    $v = static fn(string $k): string => e($s[$k] ?? '');
    $acc = $pdo->prepare('SELECT username, email, last_login FROM admins WHERE id = :id');
    $acc->execute([':id' => $_SESSION['admin_id']]);
    $account = $acc->fetch() ?: ['username' => '', 'email' => '', 'last_login' => null];
    ?>
    <form method="post" enctype="multipart/form-data" class="grid-2 grid-2--wide">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="settings_save">

        <section class="panel form-stack">
            <div class="panel__head"><h2>Search engine (SEO)</h2></div>
            <label>Site name <input type="text" name="site_name" value="<?= $v('site_name') ?>" maxlength="120"></label>
            <label>Meta title <small>(50–60 characters is ideal)</small>
                <input type="text" name="meta_title" value="<?= $v('meta_title') ?>" maxlength="160" data-count="mtCount" data-max="60">
                <span class="counter" id="mtCount"></span>
            </label>
            <label>Meta description <small>(140–160 characters is ideal)</small>
                <textarea name="meta_description" rows="3" maxlength="320" data-count="mdCount" data-max="160"><?= $v('meta_description') ?></textarea>
                <span class="counter" id="mdCount"></span>
            </label>
            <label>Keywords <small>(comma separated)</small>
                <textarea name="meta_keywords" rows="3" maxlength="500"><?= $v('meta_keywords') ?></textarea>
            </label>
            <label>Canonical site URL <small>(e.g. https://www.nenastudio.nl — leave empty to auto-detect)</small>
                <input type="url" name="canonical_url" value="<?= $v('canonical_url') ?>" placeholder="https://www.nenastudio.nl">
            </label>
            <label>Social share image path <small>(1200×630 recommended)</small>
                <input type="text" name="og_image" value="<?= $v('og_image') ?>">
            </label>
            <?php if (!empty($s['og_image'])): ?><img class="og-preview" src="<?= e(media_url($s['og_image'])) ?>" alt="Current social share image"><?php endif; ?>
            <label>…or upload a new share image
                <input type="file" name="og_image_file" accept="image/jpeg,image/png,image/webp">
            </label>
            <label>Google Search Console verification code <small>(content value only)</small>
                <input type="text" name="google_site_verification" value="<?= $v('google_site_verification') ?>">
            </label>
            <div class="serp" aria-hidden="true">
                <span class="serp__url"><?= e(site_url()) ?></span>
                <span class="serp__title"><?= $v('meta_title') ?></span>
                <span class="serp__desc"><?= $v('meta_description') ?></span>
            </div>
        </section>

        <div class="form-stack">
            <section class="panel form-stack">
                <div class="panel__head"><h2>Contact &amp; social</h2></div>
                <label>Public contact email <input type="email" name="contact_email" value="<?= $v('contact_email') ?>"></label>
                <label>Send enquiry notifications to <input type="email" name="notification_email" value="<?= $v('notification_email') ?>"></label>
                <div class="row-2">
                    <label>Phone <input type="text" name="contact_phone" value="<?= $v('contact_phone') ?>" placeholder="+31 6 …"></label>
                    <label>WhatsApp number <input type="text" name="whatsapp_number" value="<?= $v('whatsapp_number') ?>" placeholder="+31 6 …"></label>
                </div>
                <label>Instagram URL <input type="url" name="instagram_url" value="<?= $v('instagram_url') ?>" placeholder="https://www.instagram.com/…"></label>
                <label>Facebook URL <input type="url" name="facebook_url" value="<?= $v('facebook_url') ?>"></label>
                <label>Pinterest URL <input type="url" name="pinterest_url" value="<?= $v('pinterest_url') ?>"></label>
            </section>

            <section class="panel form-stack">
                <div class="panel__head"><h2>Location (Local SEO)</h2></div>
                <div class="row-2">
                    <label>City <input type="text" name="address_city" value="<?= $v('address_city') ?>" placeholder="e.g. Utrecht"></label>
                    <label>Region / province <input type="text" name="address_region" value="<?= $v('address_region') ?>"></label>
                </div>
                <div class="row-3">
                    <label>Country code <input type="text" name="address_country" value="<?= $v('address_country') ?>" maxlength="2"></label>
                    <label>Latitude <input type="text" name="geo_latitude" value="<?= $v('geo_latitude') ?>"></label>
                    <label>Longitude <input type="text" name="geo_longitude" value="<?= $v('geo_longitude') ?>"></label>
                </div>
                <button class="a-btn a-btn--primary a-btn--block" type="submit">Save settings</button>
            </section>
        </div>
    </form>

    <div class="grid-2" id="password">
        <form method="post" class="panel form-stack">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="account_update">
            <div class="panel__head"><h2>Admin account</h2></div>
            <label>Username <input type="text" name="username" value="<?= e($account['username']) ?>" required autocomplete="username"></label>
            <label>Email <input type="email" name="email" value="<?= e((string) $account['email']) ?>" autocomplete="email"></label>
            <p class="hint">Last login: <?= e(fmt_dt($account['last_login'])) ?></p>
            <button class="a-btn" type="submit">Update account</button>
        </form>
        <form method="post" class="panel form-stack" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="password_change">
            <div class="panel__head"><h2>Change password</h2></div>
            <label>Current password <input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>New password <small>(min. 10 characters)</small> <input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
            <label>Confirm new password <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password"></label>
            <button class="a-btn a-btn--primary" type="submit">Change password</button>
        </form>
    </div>
<?php endif;

admin_layout_end();

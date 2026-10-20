<?php
/**
 * Nena Studio Admin — shared bootstrap: session guard, flash messages, layout.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/logo.php';

start_secure_session();
send_security_headers();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');

const ADMIN_IDLE_TIMEOUT = 7200; // 2 hours

function admin_logged_in(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    if (time() - (int) ($_SESSION['admin_last_seen'] ?? 0) > ADMIN_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        return false;
    }
    if (($_SESSION['admin_ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        return false;
    }
    $_SESSION['admin_last_seen'] = time();
    return true;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function admin_url(array $params = []): string
{
    return url('admin/index.php') . ($params ? '?' . http_build_query($params) : '');
}

/** Format a UTC database timestamp in the studio's timezone. */
function fmt_dt(?string $utc, string $format = 'j M Y, H:i'): string
{
    if (!$utc) {
        return '—';
    }
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone(APP_TIMEZONE));
        return $d->format($format);
    } catch (Exception $e) {
        return $utc;
    }
}

function status_class(string $status): string
{
    return 'status--' . strtolower(str_replace(' ', '-', $status));
}

function admin_head(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · Nena Studio Admin</title>
    <link rel="icon" type="image/png" href="<?= e(asset('assets/images/favicon-32.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=DM+Sans:opsz,wght@9..40,400..700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
    <?php
}

function admin_layout_start(string $title, string $active): void
{
    admin_head($title);
    $newCount = (int) db()->query("SELECT COUNT(*) FROM enquiries WHERE status = 'New'")->fetchColumn();
    $nav = [
        'dashboard' => ['Dashboard', '<path d="M4 13h6V4H4zm10 7h6V4h-6zM4 20h6v-4H4z" />'],
        'enquiries' => ['Enquiries', '<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>'],
        'portfolio' => ['Behind the Stitches', '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="5" stroke-dasharray="2 2"/><path d="M17 4l3-2"/>'],
        'settings'  => ['SEO & Settings', '<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2m10-10 2-2"/>'],
    ];
    ?>
<body class="admin">
<a class="skip-link" href="#adminMain">Skip to content</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <a class="admin-brand" href="<?= e(admin_url()) ?>">
            <?= nena_logo_svg('#f9f6ec', 'admin-brand__logo') ?>
            <span>Nena <em>Studio</em><small>Atelier admin</small></span>
        </a>
        <nav class="admin-nav" aria-label="Admin navigation">
            <?php foreach ($nav as $key => [$label, $icon]): ?>
            <a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(admin_url(['tab' => $key])) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
                <span><?= e($label) ?></span>
                <?php if ($key === 'enquiries' && $newCount > 0): ?><b class="admin-nav__count"><?= $newCount ?></b><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar__footer">
            <a href="<?= e(url('')) ?>" target="_blank" rel="noopener">View website ↗</a>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="link-btn">Log out (<?= e($_SESSION['admin_username'] ?? 'admin') ?>)</button>
            </form>
        </div>
    </aside>
    <main class="admin-main" id="adminMain">
        <header class="admin-topbar">
            <button class="admin-menu" type="button" aria-controls="adminSidebar" aria-expanded="false" onclick="document.body.classList.toggle('sidebar-open');this.setAttribute('aria-expanded',document.body.classList.contains('sidebar-open'))">
                <span></span><span></span><span></span><span class="visually-hidden">Menu</span>
            </button>
            <h1><?= e($title) ?></h1>
        </header>
        <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert--<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
    <?php
}

function admin_layout_end(): void
{
    ?>
    </main>
</div>
<script>
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) { if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault(); });
});
document.querySelectorAll('[data-slug-source]').forEach(function (src) {
    var target = document.getElementById(src.getAttribute('data-slug-source'));
    if (!target) return;
    var touched = target.value !== '';
    target.addEventListener('input', function () { touched = true; });
    src.addEventListener('input', function () {
        if (touched) return;
        target.value = src.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 180);
    });
});
document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
    var box = document.getElementById(input.getAttribute('data-preview'));
    input.addEventListener('change', function () {
        box.innerHTML = '';
        Array.prototype.forEach.call(input.files, function (f) {
            var el;
            if (f.type.indexOf('image/') === 0) { el = document.createElement('img'); el.src = URL.createObjectURL(f); el.alt = ''; }
            else { el = document.createElement('span'); el.textContent = f.name; }
            box.appendChild(el);
        });
    });
});
document.querySelectorAll('[data-count]').forEach(function (field) {
    var out = document.getElementById(field.getAttribute('data-count'));
    var max = parseInt(field.getAttribute('data-max') || '0', 10);
    var update = function () { out.textContent = field.value.length + (max ? ' / ' + max : '') + ' characters'; out.classList.toggle('is-over', max && field.value.length > max); };
    field.addEventListener('input', update); update();
});
</script>
</body>
</html>
    <?php
}

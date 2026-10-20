<?php
/**
 * Nena Studio Admin — login.
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (admin_logged_in()) {
    redirect(admin_url());
}

const MAX_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

$error = '';
$ip = client_ip();
$pdo = db();

$countAttempts = static function () use ($pdo, $ip): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND attempted_at > (UTC_TIMESTAMP() - INTERVAL ' . LOCKOUT_MINUTES . ' MINUTE)');
    $stmt->execute([':ip' => $ip]);
    return (int) $stmt->fetchColumn();
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($countAttempts() >= MAX_ATTEMPTS) {
        $error = 'Too many failed attempts. Please wait ' . LOCKOUT_MINUTES . ' minutes and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = :u OR email = :e LIMIT 1');
        $stmt->execute([':u' => $username, ':e' => $username]);
        $admin = $stmt->fetch();

        // Constant-ish time: always run password_verify
        $hash = $admin['password_hash'] ?? '$2y$10$K1dyeRmPRC35sy68k1gGvuFJ0sZ4gt31Vs7U5Ie5kYxQ2Od1wLVSW';
        if (password_verify($password, $hash) && $admin) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_last_seen'] = time();
            $_SESSION['admin_ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id')
                    ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $admin['id']]);
            }
            $pdo->prepare('UPDATE admins SET last_login = UTC_TIMESTAMP() WHERE id = :id')->execute([':id' => $admin['id']]);
            $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = :ip')->execute([':ip' => $ip]);
            redirect(admin_url());
        }

        $pdo->prepare('INSERT INTO login_attempts (ip_address, attempted_at) VALUES (:ip, UTC_TIMESTAMP())')->execute([':ip' => $ip]);
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
        $left = MAX_ATTEMPTS - $countAttempts();
        $error = 'Incorrect username or password.' . ($left > 0 ? ' ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.' : '');
    }
}

admin_head('Log in');
?>
<body class="admin admin--login">
<main class="login">
    <div class="login__card">
        <span class="stitch" aria-hidden="true"></span>
        <?= nena_logo_svg('#0a5b59', 'login__logo') ?>
        <h1>Welcome back to the atelier</h1>
        <p class="login__sub">Sign in to manage enquiries, stories and settings.</p>

        <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/login.php')) ?>" class="form-stack" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>Username or email
                <input type="text" name="username" required autocomplete="username" autofocus value="<?= e($_POST['username'] ?? '') ?>">
            </label>
            <label>Password
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="a-btn a-btn--primary a-btn--block">Log in</button>
        </form>
        <a class="login__back" href="<?= e(url('')) ?>">← Back to the website</a>
    </div>
</main>
</body>
</html>

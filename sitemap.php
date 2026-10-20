<?php
/**
 * Nena Studio — dynamic XML sitemap (served as /sitemap.xml via .htaccess).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$home = abs_url('');
$lastProject = db()->query('SELECT MAX(updated_at) FROM portfolio_projects WHERE is_published = 1')->fetchColumn();
$lastSettings = db()->query('SELECT MAX(updated_at) FROM settings')->fetchColumn();
$homeMod = max(strtotime((string) $lastProject) ?: 0, strtotime((string) $lastSettings) ?: 0, filemtime(__DIR__ . '/index.php'));

$projects = db()->query(
    'SELECT slug, title, featured_image, image_alt, updated_at FROM portfolio_projects
     WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC'
)->fetchAll();

$x = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <url>
        <loc><?= $x($home) ?></loc>
        <lastmod><?= gmdate('Y-m-d', $homeMod) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
        <image:image>
            <image:loc><?= $x(abs_url(setting('og_image', 'assets/images/og-image.jpg'))) ?></image:loc>
        </image:image>
    </url>
<?php foreach ($projects as $p): ?>
    <url>
        <loc><?= $x(abs_url('stories/' . $p['slug'])) ?></loc>
        <lastmod><?= gmdate('Y-m-d', strtotime($p['updated_at']) ?: time()) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
<?php if ($p['featured_image']): ?>
        <image:image>
            <image:loc><?= $x(abs_url($p['featured_image'])) ?></image:loc>
        </image:image>
<?php endif; ?>
    </url>
<?php endforeach; ?>
</urlset>

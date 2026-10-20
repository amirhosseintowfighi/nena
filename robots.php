<?php
/**
 * Nena Studio — dynamic robots.txt (served as /robots.txt via .htaccess so the
 * Sitemap line always uses the correct domain). Static robots.txt is the fallback.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');
$base = base_path();
echo "User-agent: *\n";
echo "Allow: {$base}/\n";
echo "Disallow: {$base}/admin/\n";
echo "Disallow: {$base}/api/\n";
echo "Disallow: {$base}/includes/\n";
echo "Disallow: {$base}/uploads/enquiries/\n";
echo "Allow: {$base}/uploads/portfolio/\n\n";
echo 'Sitemap: ' . abs_url('sitemap.xml') . "\n";

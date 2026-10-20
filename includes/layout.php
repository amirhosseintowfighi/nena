<?php
/**
 * Shared layout: SEO <head>, sticky glass header, footer and decorative SVG art.
 */

declare(strict_types=1);

require_once __DIR__ . '/logo.php';

/**
 * Print the full <head> section.
 *
 * @param array $meta title, description, keywords, canonical, image, type, jsonld (array of schema arrays)
 */
function render_head(array $meta = []): void
{
    $siteName    = setting('site_name', 'Nena Studio');
    $title       = $meta['title'] ?? setting('meta_title', $siteName);
    $description = $meta['description'] ?? setting('meta_description');
    $keywords    = $meta['keywords'] ?? setting('meta_keywords');
    $canonical   = $meta['canonical'] ?? abs_url('');
    $image       = abs_url($meta['image'] ?? setting('og_image', 'assets/images/og-image.jpg'));
    $type        = $meta['type'] ?? 'website';
    $lat         = setting('geo_latitude', '52.3676');
    $lng         = setting('geo_longitude', '4.9041');
    $placename   = trim(setting('address_city') . ', ' . setting('address_region', 'Netherlands'), ', ');
    $verify      = setting('google_site_verification');
    ?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= e($keywords) ?>"><?php endif; ?>

    <meta name="robots" content="<?= e($meta['robots'] ?? 'index, follow, max-image-preview:large') ?>">
    <meta name="author" content="<?= e($siteName) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <link rel="alternate" hreflang="en" href="<?= e($canonical) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= e($canonical) ?>">
    <?php if ($verify !== ''): ?><meta name="google-site-verification" content="<?= e($verify) ?>"><?php endif; ?>

    <!-- Geo -->
    <meta name="geo.region" content="NL">
    <meta name="geo.placename" content="<?= e($placename !== '' ? $placename : 'Netherlands') ?>">
    <meta name="geo.position" content="<?= e($lat . ';' . $lng) ?>">
    <meta name="ICBM" content="<?= e($lat . ', ' . $lng) ?>">

    <!-- Open Graph -->
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:locale" content="en_GB">
    <meta property="og:type" content="<?= e($type) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($image) ?>">
    <meta property="og:image:alt" content="<?= e($meta['image_alt'] ?? $siteName . ' — Your Story, One Stitch at a Time.') ?>">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($image) ?>">

    <meta name="theme-color" content="#0a5b59">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/images/favicon-32.png')) ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(asset('assets/images/favicon-512.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('assets/images/apple-touch-icon.png')) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,300..700;1,9..40,400&display=swap">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <?php foreach ($meta['jsonld'] ?? [] as $schema): ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT) ?></script>
    <?php endforeach; ?>
</head>
    <?php
}

/** Brand logo <img> with inline-SVG fallback when the official file isn't uploaded. */
function brand_logo(string $variant = 'MAIN', string $class = 'brand__logo', bool $eager = true): string
{
    $fills = ['MAIN' => '#0a5b59', 'WHITE' => '#ffffff', 'BLACK' => '#000000'];
    $src = logo_file_url($variant);
    if ($src !== null) {
        return '<img class="' . e($class) . '" src="' . e($src) . '" alt="Nena Studio logo" width="56" height="56"'
            . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
    }
    return nena_logo_svg($fills[strtoupper($variant)] ?? '#0a5b59', $class);
}

function render_header(bool $onHome = true): void
{
    $prefix = $onHome ? '' : url('');
    $links = [
        '#home'                => 'Home',
        '#our-story'           => 'Our Story',
        '#what-we-create'      => 'What We Create',
        '#behind-the-stitches' => 'Behind the Stitches',
    ];
    ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" id="siteHeader">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e($onHome ? '#home' : url('')) ?>" aria-label="Nena Studio — home">
            <?= brand_logo('MAIN') ?>
            <span class="brand__name">Nena <em>Studio</em></span>
        </a>
        <button class="nav-toggle" id="navToggle" aria-controls="siteNav" aria-expanded="false" aria-label="Open menu">
            <span></span><span></span><span></span>
        </button>
        <nav class="site-nav" id="siteNav" aria-label="Main navigation">
            <ul>
                <?php foreach ($links as $hash => $label): ?>
                <li><a href="<?= e($prefix . $hash) ?>" data-nav-link><?= e($label) ?></a></li>
                <?php endforeach; ?>
                <li><a class="btn btn--primary btn--nav" href="<?= e($prefix . '#lets-create-together') ?>">Let's Create Together</a></li>
            </ul>
        </nav>
    </div>
</header>
    <?php
}

function render_footer(): void
{
    $email = setting('contact_email');
    $phone = setting('contact_phone');
    $whatsapp = preg_replace('/\D+/', '', setting('whatsapp_number'));
    $socials = array_filter([
        'Instagram' => setting('instagram_url'),
        'Facebook'  => setting('facebook_url'),
        'Pinterest' => setting('pinterest_url'),
    ]);
    $home = url('');
    ?>
<footer class="site-footer">
    <div class="site-footer__vine" aria-hidden="true"><?= vine_divider_svg('#a6b5a0', '#be7042') ?></div>
    <div class="container site-footer__grid">
        <div class="site-footer__brand">
            <?= brand_logo('WHITE', 'site-footer__logo', false) ?>
            <p class="site-footer__tagline">Your Story, One Stitch at a Time.</p>
            <p class="site-footer__meta">Personalised textile creations, embroidery, and thoughtful alterations — made in the Netherlands.</p>
        </div>
        <nav aria-label="Footer navigation">
            <h2 class="site-footer__title">Explore</h2>
            <ul>
                <li><a href="<?= e($home) ?>#our-story">Our Story</a></li>
                <li><a href="<?= e($home) ?>#what-we-create">What We Create</a></li>
                <li><a href="<?= e($home) ?>#behind-the-stitches">Behind the Stitches</a></li>
                <li><a href="<?= e($home) ?>#lets-create-together">Let's Create Together</a></li>
            </ul>
        </nav>
        <div>
            <h2 class="site-footer__title">Say Hello</h2>
            <ul>
                <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
                <?php if ($phone !== ''): ?><li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
                <?php if ($whatsapp !== ''): ?><li><a href="https://wa.me/<?= e($whatsapp) ?>" rel="noopener" target="_blank">WhatsApp</a></li><?php endif; ?>
                <?php foreach ($socials as $label => $href): ?>
                <li><a href="<?= e($href) ?>" rel="noopener me" target="_blank"><?= e($label) ?></a></li>
                <?php endforeach; ?>
                <li><span>Studio based in the Netherlands</span></li>
            </ul>
        </div>
    </div>
    <div class="container site-footer__bottom">
        <p>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'Nena Studio')) ?>. Made by hand, with love.</p>
    </div>
</footer>
    <?php
}

// ---------------------------------------------------------------------
//  Decorative inline SVG art (matches the NENA botanical monogram)
// ---------------------------------------------------------------------
function leaf_path(float $x, float $y, float $rot, float $size, string $fill): string
{
    $s = $size;
    return sprintf(
        '<path transform="translate(%.1f %.1f) rotate(%.1f)" d="M0 0C%.1f %.1f %.1f %.1f %.1f 0C%.1f %.1f %.1f %.1f 0 0Z" fill="%s"/>',
        $x, $y, $rot, $s * .35, -$s * .34, $s * .8, -$s * .3, $s, $s * .8, $s * .3, $s * .35, $s * .34, $fill
    );
}

function vine_divider_svg(string $leaf = '#a6b5a0', string $accent = '#be7042'): string
{
    $out = '<svg class="vine-divider" viewBox="0 0 600 60" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">';
    $out .= '<path class="draw" d="M10 30 C 110 0, 190 60, 300 30 S 490 0, 590 30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
    $leaves = [[80, 18, -40], [140, 36, 30], [220, 40, -20], [380, 22, 200], [450, 16, -150], [520, 30, 160]];
    foreach ($leaves as $i => [$x, $y, $r]) {
        $out .= leaf_path($x, $y, $r, 22, $i % 2 ? $leaf : 'currentColor');
    }
    $out .= '<circle cx="300" cy="30" r="7" fill="' . e($accent) . '"/><circle cx="300" cy="30" r="2.6" fill="#f9f6ec"/>';
    return $out . '</svg>';
}

/** Ornamental corner (as in the logo frame). */
function corner_ornament_svg(string $class = 'corner'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 80 80" aria-hidden="true" focusable="false">'
        . '<path d="M2 2h58C34 10 12 30 2 60Z" fill="currentColor"/>'
        . '<circle cx="20" cy="20" r="5.5" fill="#f9f6ec"/>'
        . '<path d="M2 70 C 14 36, 36 14, 70 2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 4"/>'
        . '</svg>';
}

/** Hero illustration: embroidery hoop holding the NENA monogram, a botanical vine and a threaded needle. */
function hero_hoop_svg(): string
{
    $logo = '';
    foreach (nena_logo_paths() as $d) {
        $logo .= '<path d="' . $d . '"/>';
    }
    $leaves = '';
    $vine = [[118, 400, -120], [150, 448, -60], [196, 488, -140], [252, 512, -30], [318, 520, -160], [390, 506, -20], [440, 474, 160]];
    foreach ($vine as $i => [$x, $y, $r]) {
        $leaves .= leaf_path($x, $y, $r, 34, $i % 3 === 0 ? '#0a5b59' : '#a6b5a0');
    }
    return '<svg class="hero-art__svg" viewBox="0 0 560 600" role="img" aria-labelledby="heroArtTitle">'
        . '<title id="heroArtTitle">The NENA monogram stitched inside a wooden embroidery hoop, surrounded by botanical leaves and a threaded needle</title>'
        . '<defs><radialGradient id="fabric" cx="50%" cy="40%" r="65%"><stop offset="0" stop-color="#fdfbf4"/><stop offset="1" stop-color="#f1ecd9"/></radialGradient>'
        . '<linearGradient id="wood" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#d08a5f"/><stop offset="1" stop-color="#a65d35"/></linearGradient></defs>'
        . '<g class="hero-art__hoop">'
        . '<rect x="250" y="34" width="60" height="34" rx="9" fill="url(#wood)"/><circle cx="280" cy="51" r="7" fill="#f9f6ec"/>'
        . '<circle cx="280" cy="300" r="236" fill="none" stroke="url(#wood)" stroke-width="20"/>'
        . '<circle cx="280" cy="300" r="222" fill="none" stroke="#8f4f2c" stroke-width="4" opacity=".55"/>'
        . '<circle cx="280" cy="300" r="214" fill="url(#fabric)"/>'
        . '<circle class="stitch-ring" cx="280" cy="300" r="190" fill="none" stroke="#a6b5a0" stroke-width="2" stroke-dasharray="9 8"/>'
        . '<g transform="translate(170 188) scale(0.2408)" fill="#0a5b59">' . $logo . '</g>'
        . '</g>'
        . '<path class="draw" d="M86 360 C 100 450, 200 530, 320 522 S 460 470, 474 420" fill="none" stroke="#0a5b59" stroke-width="3.5" stroke-linecap="round"/>'
        . $leaves
        . '<g class="hero-art__flowers">'
        . '<circle cx="474" cy="420" r="11" fill="#be7042"/><circle cx="474" cy="420" r="4" fill="#f9f6ec"/>'
        . '<circle cx="86" cy="360" r="9" fill="#be7042"/><circle cx="86" cy="360" r="3.4" fill="#f9f6ec"/>'
        . '</g>'
        . '<path class="thread" d="M470 120 C 560 160, 540 260, 500 300 S 420 380, 470 420" fill="none" stroke="#be7042" stroke-width="2.5" stroke-dasharray="10 8" stroke-linecap="round"/>'
        . '<g class="hero-art__needle" transform="translate(462 96) rotate(32)"><rect x="-3.5" y="-56" width="7" height="104" rx="3.5" fill="#0a5b59"/><ellipse cx="0" cy="-44" rx="1.8" ry="7" fill="#f9f6ec"/></g>'
        . '</svg>';
}

/** Service card icons. */
function service_icon_svg(string $key): string
{
    $icons = [
        // Bridal veil with flower & rings
        'weddings' => '<path d="M32 10c-8 0-13 6-13 13v4c0 9-4 18-9 27h44c-5-9-9-18-9-27v-4c0-7-5-13-13-13Z" fill="none" stroke="#0a5b59" stroke-width="2.4" stroke-linejoin="round"/>'
            . '<path d="M14 50c6-3 12-3 18 0s12 3 18 0" fill="none" stroke="#a6b5a0" stroke-width="2" stroke-dasharray="3 3"/>'
            . '<circle cx="32" cy="10" r="4.5" fill="#be7042"/><circle cx="32" cy="10" r="1.6" fill="#f9f6ec"/>'
            . '<path d="M32 20c3 4 3 8 0 12-3-4-3-8 0-12Z" fill="#a6b5a0"/>',
        // Needle & thread heart
        'made-for-you' => '<path d="M32 52S12 40 12 26c0-6 5-11 11-11 4 0 7 2 9 5 2-3 5-5 9-5 6 0 11 5 11 11 0 14-20 26-20 26Z" fill="none" stroke="#0a5b59" stroke-width="2.4" stroke-linejoin="round"/>'
            . '<path d="M20 28c6 6 18 6 24 0" fill="none" stroke="#be7042" stroke-width="2" stroke-dasharray="3 3" stroke-linecap="round"/>'
            . '<g transform="translate(48 14) rotate(40)"><rect x="-1.6" y="-12" width="3.2" height="24" rx="1.6" fill="#0a5b59"/><ellipse cx="0" cy="-9" rx=".8" ry="2.4" fill="#f9f6ec"/></g>'
            . '<path d="M26 22c2-3 5-4 8-3-2 3-5 4-8 3Z" fill="#a6b5a0"/>',
        // Scissors & measuring tape
        'alterations' => '<circle cx="18" cy="44" r="7" fill="none" stroke="#0a5b59" stroke-width="2.4"/><circle cx="34" cy="50" r="7" fill="none" stroke="#0a5b59" stroke-width="2.4"/>'
            . '<path d="M23 39 48 12M29 45 52 22" stroke="#0a5b59" stroke-width="2.4" stroke-linecap="round"/>'
            . '<path d="M8 22c10-8 22-8 32-2" fill="none" stroke="#be7042" stroke-width="4" stroke-linecap="round"/>'
            . '<path d="M8 22c10-8 22-8 32-2" fill="none" stroke="#f9f6ec" stroke-width="1" stroke-dasharray="1 4"/>'
            . '<path d="M44 40c4-1 7 1 8 5-4 1-7-1-8-5Z" fill="#a6b5a0"/>',
    ];
    return '<svg class="service-card__icon" viewBox="0 0 64 64" aria-hidden="true" focusable="false">' . ($icons[$key] ?? '') . '</svg>';
}

/** Ornamental framed illustration for the Our Story section. */
function story_frame_svg(): string
{
    $leaves = '';
    // Leaves sprouting from both sides of the vine, as in the NENA monogram
    $points = [[219, 372], [228, 322], [232, 272], [219, 214], [213, 164], [221, 118]];
    foreach ($points as $i => [$x, $y]) {
        $left = $i % 2 === 0;
        $leaves .= leaf_path($x, $y, $left ? -150 - $i * 4 : -30 + $i * 4, 44 - $i * 3, $i % 3 === 1 ? '#a6b5a0' : '#0a5b59');
        $leaves .= leaf_path($x, $y + 14, $left ? -40 : -140, 28 - $i * 2, '#a6b5a0');
    }
    return '<svg class="story-art__svg" viewBox="0 0 450 520" role="img" aria-labelledby="storyArtTitle">'
        . '<title id="storyArtTitle">A botanical vine growing from a spool of thread inside an ornamental frame</title>'
        . '<rect x="10" y="10" width="430" height="500" rx="4" fill="#f9f6ec" stroke="#0a5b59" stroke-width="5"/>'
        . '<rect x="30" y="30" width="390" height="460" fill="none" stroke="#a6b5a0" stroke-width="1.5" stroke-dasharray="7 6"/>'
        . '<g fill="#0a5b59"><path d="M10 10h56C40 18 20 38 10 64Z"/><path d="M440 10h-56c26 8 46 28 56 54Z"/><path d="M10 510h56c-26-8-46-28-56-54Z"/><path d="M440 510h-56c26-8 46-28 56-54Z"/></g>'
        . '<g fill="#f9f6ec"><circle cx="26" cy="26" r="4.5"/><circle cx="424" cy="26" r="4.5"/><circle cx="26" cy="494" r="4.5"/><circle cx="424" cy="494" r="4.5"/></g>'
        . '<path class="draw" d="M225 410 C 200 340, 250 300, 225 240 S 200 140, 225 90" fill="none" stroke="#0a5b59" stroke-width="3.5" stroke-linecap="round"/>'
        . $leaves
        . '<circle cx="225" cy="86" r="12" fill="#be7042"/><circle cx="225" cy="86" r="4.5" fill="#f9f6ec"/>'
        . '<g transform="translate(225 432)"><rect x="-48" y="-22" width="96" height="10" rx="3" fill="#0a5b59"/><rect x="-40" y="-12" width="80" height="34" fill="#be7042"/>'
        . '<path d="M-40 -6h80M-40 2h80M-40 10h80M-40 18h80" stroke="#a65d35" stroke-width="1.4"/><rect x="-48" y="22" width="96" height="10" rx="3" fill="#0a5b59"/></g>'
        . '</svg>';
}

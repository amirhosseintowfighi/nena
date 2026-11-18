<?php
/**
 * Nena Studio — single "Behind the Stitches" story page (/stories/{slug}).
 * Gives every project its own crawlable, shareable URL.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';

send_security_headers();

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
$stmt = db()->prepare('SELECT * FROM portfolio_projects WHERE slug = :slug AND is_published = 1 LIMIT 1');
$stmt->execute([':slug' => $slug]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<?php render_head([
    'title'       => 'Story not found | ' . setting('site_name', 'Nena Studio'),
    'description' => 'This story could not be found. Discover more of Nena Studio\'s projects behind the stitches.',
    'canonical'   => abs_url(''),
    'robots'      => 'noindex, follow',
]); ?>
<body>
<?php render_header(false); ?>
<main id="main" class="section notfound">
    <div class="container notfound__inner glass-card">
        <span class="stitch-border" aria-hidden="true"></span>
        <p class="eyebrow"><?= ct('stitches_eyebrow') ?></p>
        <h1 class="section-title">This thread seems to have come loose.</h1>
        <p>The story you were looking for isn't here anymore — but there are plenty more to discover.</p>
        <a class="btn btn--primary" href="<?= e(url('')) ?>#behind-the-stitches">See All Stories</a>
    </div>
</main>
<?php render_footer(); ?>
<script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
</body>
</html>
    <?php
    exit;
}

$categories = project_categories();
$categoryLabel = $categories[$project['category']] ?? $project['category'];
$gallery = json_list($project['media_gallery_json']);
if ($project['featured_image'] && !in_array($project['featured_image'], $gallery, true)) {
    array_unshift($gallery, $project['featured_image']);
}
$video = video_embed($project['video_url']);
$alt = $project['image_alt'] !== '' ? $project['image_alt'] : $project['title'];
$canonical = abs_url('stories/' . $project['slug']);
$siteName = setting('site_name', 'Nena Studio');

// Related stories
$rel = db()->prepare('SELECT title, slug, featured_image, image_alt, short_summary, category FROM portfolio_projects
                      WHERE is_published = 1 AND id <> :id ORDER BY (category = :cat) DESC, sort_order ASC, created_at DESC LIMIT 3');
$rel->execute([':id' => $project['id'], ':cat' => $project['category']]);
$related = $rel->fetchAll();

$jsonld = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'            => 'CreativeWork',
            '@id'              => $canonical . '#work',
            'name'             => $project['title'],
            'headline'         => $project['title'],
            'description'      => $project['short_summary'],
            'genre'            => $categoryLabel,
            'url'              => $canonical,
            'image'            => array_map(fn($g) => abs_url($g), $gallery),
            'dateCreated'      => date('c', strtotime($project['created_at'])),
            'dateModified'     => date('c', strtotime($project['updated_at'])),
            'creator'          => ['@type' => 'Organization', 'name' => $siteName, 'url' => abs_url('')],
            'publisher'        => ['@id' => abs_url('') . '#business'],
            'inLanguage'       => 'en',
            'mainEntityOfPage' => $canonical,
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => abs_url('')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Behind the Stitches', 'item' => abs_url('') . '#behind-the-stitches'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $project['title'], 'item' => $canonical],
            ],
        ],
    ],
];
if ($video && $video['type'] === 'file') {
    $jsonld['@graph'][] = [
        '@type'        => 'VideoObject',
        'name'         => $project['title'] . ' — process video',
        'description'  => $project['short_summary'],
        'contentUrl'   => abs_url(ltrim((string) $project['video_url'], '/')),
        'thumbnailUrl' => $project['featured_image'] ? abs_url(ltrim($project['featured_image'], '/')) : abs_url('assets/images/og-image.jpg'),
        'uploadDate'   => date('c', strtotime($project['created_at'])),
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<?php render_head([
    'title'       => $project['title'] . ' | Behind the Stitches | ' . $siteName,
    'description' => mb_substr($project['short_summary'], 0, 300),
    'canonical'   => $canonical,
    'image'       => $project['featured_image'] && preg_match('/\.(jpe?g|png|webp|gif)$/i', $project['featured_image']) ? $project['featured_image'] : setting('og_image', 'assets/images/og-image.jpg'),
    'image_alt'   => $alt,
    'type'        => 'article',
    'jsonld'      => [$jsonld],
]); ?>
<body class="page-story">
<?php render_header(false); ?>

<main id="main">
    <article class="section story-page">
        <div class="container">
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?= e(url('')) ?>"><?= ct('nav_home') ?></a></li>
                    <li><a href="<?= e(url('')) ?>#behind-the-stitches"><?= ct('nav_stitches') ?></a></li>
                    <li aria-current="page"><?= e($project['title']) ?></li>
                </ol>
            </nav>

            <header class="story-page__header reveal">
                <span class="tag"><?= e($categoryLabel) ?></span>
                <h1 class="section-title story-page__title"><?= e($project['title']) ?></h1>
                <p class="story-page__date"><time datetime="<?= e(date('Y-m-d', strtotime($project['created_at']))) ?>"><?= e(date('F Y', strtotime($project['created_at']))) ?></time></p>
                <p class="section-intro section-intro--lead"><?= e($project['short_summary']) ?></p>
                <?= vine_divider_svg() ?>
            </header>

            <div class="story-page__grid">
                <div class="story-page__media">
                    <?php foreach ($gallery as $i => $img): ?>
                    <figure class="story-page__figure glass-card">
                        <img src="<?= e(media_url($img)) ?>" alt="<?= e($i === 0 ? $alt : $alt . ' — detail ' . ($i + 1)) ?>" width="1200" height="900" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                    </figure>
                    <?php endforeach; ?>
                    <?php if ($video): ?>
                    <figure class="story-page__figure story-page__video glass-card">
                        <?php if ($video['type'] === 'file'): ?>
                        <video controls preload="none" playsinline <?= $project['featured_image'] && !str_ends_with($project['featured_image'], '.svg') ? 'poster="' . e(media_url($project['featured_image'])) . '"' : '' ?> width="1280" height="720">
                            <source src="<?= e($video['src']) ?>" type="<?= e($video['mime']) ?>">
                            Your browser does not support HTML5 video.
                        </video>
                        <?php else: ?>
                        <iframe src="<?= e($video['src']) ?>" title="<?= e($project['title']) ?> — process video" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>
                        <?php endif; ?>
                        <figcaption><?= ct('storypage_video_caption') ?></figcaption>
                    </figure>
                    <?php endif; ?>
                </div>
                <div class="story-page__content glass-card">
                    <span class="stitch-border" aria-hidden="true"></span>
                    <div class="prose">
                        <?= render_story((string) $project['full_story'], 'h2') ?>
                    </div>
                    <div class="story-page__cta">
                        <p><?= ct('storypage_cta_text') ?></p>
                        <a class="btn btn--primary" href="<?= e(url('')) ?>#lets-create-together"><?= ct('storypage_cta_button') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </article>

    <?php if ($related): ?>
    <section class="section section--alt" aria-labelledby="relatedTitle">
        <div class="container">
            <header class="section-header">
                <p class="eyebrow"><?= ct('stitches_eyebrow') ?></p>
                <h2 class="section-title" id="relatedTitle"><?= ct('storypage_related') ?></h2>
            </header>
            <div class="projects-grid">
                <?php foreach ($related as $r): ?>
                <article class="project-card">
                    <a class="project-card__link" href="<?= e(project_url($r['slug'])) ?>">
                        <figure class="project-card__media">
                            <?php if ($r['featured_image']): ?>
                            <img src="<?= e(media_url($r['featured_image'])) ?>" alt="<?= e($r['image_alt'] !== '' ? $r['image_alt'] : $r['title']) ?>" width="1200" height="900" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </figure>
                        <div class="project-card__body">
                            <span class="tag"><?= e($categories[$r['category']] ?? $r['category']) ?></span>
                            <h3 class="project-card__title"><?= e($r['title']) ?></h3>
                            <p class="project-card__summary"><?= e($r['short_summary']) ?></p>
                            <span class="project-card__more"><?= ct('stitches_read_more') ?> <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php render_footer(); ?>
<script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
</body>
</html>

<?php
/**
 * Nena Studio — public one-page website.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';

send_security_headers();
csrf_token();

$categories = project_categories();
$projects = db()->query(
    'SELECT id, title, slug, category, short_summary, full_story, image_alt, featured_image, video_url, media_gallery_json, created_at
     FROM portfolio_projects WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC'
)->fetchAll();

// Data for the Story Lightbox (rendered server-side, read by main.js)
$storyData = [];
foreach ($projects as $p) {
    $gallery = json_list($p['media_gallery_json']);
    if ($p['featured_image'] && !in_array($p['featured_image'], $gallery, true)) {
        array_unshift($gallery, $p['featured_image']);
    }
    $storyData[$p['slug']] = [
        'title'    => $p['title'],
        'category' => $categories[$p['category']] ?? $p['category'],
        'summary'  => $p['short_summary'],
        'story'    => render_story((string) $p['full_story']),
        'alt'      => $p['image_alt'] !== '' ? $p['image_alt'] : $p['title'],
        'gallery'  => array_map('media_url', $gallery),
        'video'    => video_embed($p['video_url']),
        'url'      => project_url($p['slug']),
        'date'     => date('F Y', strtotime($p['created_at'])),
    ];
}

// ---------------------------------------------------------------------
//  Structured data (JSON-LD)
// ---------------------------------------------------------------------
$siteUrl  = abs_url('');
$bizId    = $siteUrl . '#business';
$sameAs   = array_values(array_filter([setting('instagram_url'), setting('facebook_url'), setting('pinterest_url')]));
$services = [];
foreach ([1, 2, 3] as $n) {
    $services[] = [content("service{$n}_title"), trim(preg_replace('/\s+/u', ' ', content("service{$n}_body")) ?? '')];
}

$address = array_filter([
    '@type'           => 'PostalAddress',
    'addressLocality' => setting('address_city'),
    'addressRegion'   => setting('address_region', 'Netherlands'),
    'addressCountry'  => setting('address_country', 'NL'),
]);

$business = array_filter([
    '@type'       => ['LocalBusiness', 'ProfessionalService'],
    '@id'         => $bizId,
    'name'        => setting('site_name', 'Nena Studio'),
    'alternateName' => 'Nena',
    'slogan'      => str_replace('*', '', content('hero_title')),
    'description' => setting('meta_description'),
    'url'         => $siteUrl,
    'logo'        => abs_url('assets/images/favicon-512.png'),
    'image'       => abs_url(setting('og_image', 'assets/images/og-image.jpg')),
    'email'       => setting('contact_email'),
    'telephone'   => setting('contact_phone'),
    'address'     => $address,
    'geo'         => [
        '@type'     => 'GeoCoordinates',
        'latitude'  => (float) setting('geo_latitude', '52.3676'),
        'longitude' => (float) setting('geo_longitude', '4.9041'),
    ],
    'areaServed'  => ['@type' => 'Country', 'name' => 'Netherlands'],
    'knowsAbout'  => ['Embroidery', 'Bridal accessories', 'Personalised wedding accessories', 'Garment alterations', 'Clothing repair', 'Upcycling', 'Beadwork', 'Sewing'],
    'priceRange'  => '€€',
    'sameAs'      => $sameAs,
    'hasOfferCatalog' => [
        '@type' => 'OfferCatalog',
        'name'  => 'What We Create',
        'itemListElement' => array_map(fn($s, $i) => [
            '@type'       => 'Offer',
            'itemOffered' => ['@id' => $siteUrl . '#service-' . ($i + 1)],
        ], $services, array_keys($services)),
    ],
]);

$graph = [$business];
foreach ($services as $i => [$name, $desc]) {
    $graph[] = [
        '@type'       => 'Service',
        '@id'         => $siteUrl . '#service-' . ($i + 1),
        'name'        => $name,
        'serviceType' => $name,
        'description' => $desc,
        'provider'    => ['@id' => $bizId],
        'areaServed'  => ['@type' => 'Country', 'name' => 'Netherlands'],
        'url'         => $siteUrl . '#what-we-create',
    ];
}
$graph[] = [
    '@type'     => 'WebSite',
    '@id'       => $siteUrl . '#website',
    'url'       => $siteUrl,
    'name'      => setting('site_name', 'Nena Studio'),
    'publisher' => ['@id' => $bizId],
    'inLanguage' => 'en',
];
if ($projects) {
    $graph[] = [
        '@type' => 'ItemList',
        'name'  => 'Behind the Stitches',
        'itemListElement' => array_map(fn($p, $i) => [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'url'      => abs_url('stories/' . $p['slug']),
            'name'     => $p['title'],
        ], $projects, array_keys($projects)),
    ];
}

$firstFeatured = $projects[0]['featured_image'] ?? null;
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<?php render_head([
    'canonical' => $siteUrl,
    'jsonld'    => [['@context' => 'https://schema.org', '@graph' => $graph]],
]); ?>
<body>
<?php render_header(true); ?>

<main id="main">

    <!-- =========================== HERO =========================== -->
    <section class="hero" id="home" aria-labelledby="heroTitle">
        <div class="hero__ambient" aria-hidden="true"></div>
        <?= corner_ornament_svg('corner corner--tl') ?>
        <?= corner_ornament_svg('corner corner--br') ?>
        <div class="container hero__grid">
            <div class="hero__content reveal">
                <p class="eyebrow"><?= ct('hero_eyebrow') ?></p>
                <h1 class="hero__title" id="heroTitle"><?= ct_inline('hero_title') ?></h1>
                <p class="hero__subtitle"><?= ct('hero_subtitle') ?></p>
                <div class="hero__intro">
                    <?= ct_paragraphs('hero_intro') ?>
                </div>
                <div class="hero__actions">
                    <a class="btn btn--primary" href="#lets-create-together"><?= ct('hero_cta_primary') ?></a>
                    <a class="btn btn--ghost" href="#behind-the-stitches"><?= ct('hero_cta_secondary') ?></a>
                </div>
            </div>
            <div class="hero__art reveal reveal--delay">
                <?= hero_hoop_svg() ?>
            </div>
        </div>

        <div class="container">
            <aside class="glass-card about-nena reveal" aria-labelledby="aboutNenaTitle">
                <span class="stitch-border" aria-hidden="true"></span>
                <h2 class="about-nena__title" id="aboutNenaTitle"><?= ct('about_title') ?></h2>
                <?= ct_paragraphs('about_text') ?>
            </aside>
        </div>
    </section>

    <!-- =========================== OUR STORY =========================== -->
    <section class="section section--alt our-story" id="our-story" aria-labelledby="ourStoryTitle">
        <div class="container our-story__grid">
            <div class="our-story__art reveal">
                <?= story_frame_svg() ?>
            </div>
            <div class="our-story__content reveal reveal--delay">
                <p class="eyebrow"><?= ct('story_eyebrow') ?></p>
                <h2 class="section-title" id="ourStoryTitle"><?= ct('story_title') ?></h2>
                <?= vine_divider_svg() ?>
                <div class="prose">
                    <p class="lead"><?= ct('story_lead') ?></p>
                    <?= ct_paragraphs('story_body') ?>
                    <p class="signature"><?= ct('story_signature') ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================== WHAT WE CREATE =========================== -->
    <section class="section services" id="what-we-create" aria-labelledby="servicesTitle">
        <div class="container">
            <header class="section-header reveal">
                <p class="eyebrow"><?= ct('services_eyebrow') ?></p>
                <h2 class="section-title" id="servicesTitle"><?= ct('services_title') ?></h2>
                <?= vine_divider_svg() ?>
                <div class="section-intro">
                    <?= ct_paragraphs('services_intro') ?>
                </div>
            </header>

            <div class="services__grid">
                <?php
                // Card order is fixed; the enquiry project type each button pre-selects stays fixed too.
                $serviceCards = [
                    1 => ['weddings', 'service-weddings', 'Weddings & Special Occasions', ''],
                    2 => ['made-for-you', 'service-made-for-you', 'Made for You', ' reveal--delay'],
                    3 => ['alterations', 'service-alterations', 'Alterations & Reimagining', ' reveal--delay-2'],
                ];
                foreach ($serviceCards as $n => [$icon, $anchor, $enquiryType, $delay]): ?>
                <article class="glass-card service-card reveal<?= $delay ?>" id="<?= e($anchor) ?>">
                    <span class="stitch-border" aria-hidden="true"></span>
                    <div class="service-card__icon-wrap"><?= service_icon_svg($icon) ?></div>
                    <h3 class="service-card__title"><?= ct("service{$n}_title") ?></h3>
                    <p class="service-card__subtitle"><?= ct("service{$n}_subtitle") ?></p>
                    <div class="service-card__body">
                        <?= ct_paragraphs("service{$n}_body") ?>
                    </div>
                    <a class="btn btn--outline service-card__cta" href="#enquiry-form" data-select-service="<?= e($enquiryType) ?>"><?= ct("service{$n}_cta") ?></a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- =========================== BEHIND THE STITCHES =========================== -->
    <section class="section section--alt stitches" id="behind-the-stitches" aria-labelledby="stitchesTitle">
        <div class="container">
            <header class="section-header reveal">
                <p class="eyebrow"><?= ct('stitches_eyebrow') ?></p>
                <h2 class="section-title" id="stitchesTitle"><?= ct('stitches_title') ?></h2>
                <?= vine_divider_svg() ?>
                <p class="section-intro section-intro--lead"><?= ct('stitches_intro') ?></p>
            </header>

            <div class="stitches__narrative glass-card reveal">
                <span class="stitch-border" aria-hidden="true"></span>
                <?= ct_paragraphs('stitches_narrative') ?>
            </div>

            <?php if ($projects): ?>
            <div class="filters reveal" role="toolbar" aria-label="Filter projects by category">
                <button class="filter-pill is-active" type="button" data-filter="all" aria-pressed="true"><?= ct('stitches_filter_all') ?></button>
                <?php foreach ($categories as $key => $label): ?>
                <button class="filter-pill" type="button" data-filter="<?= e($key) ?>" aria-pressed="false"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="projects-grid" id="projectsGrid">
                <?php foreach ($projects as $i => $p): ?>
                <article class="project-card reveal" data-category="<?= e($p['category']) ?>">
                    <a class="project-card__link" href="<?= e(project_url($p['slug'])) ?>" data-story="<?= e($p['slug']) ?>" aria-label="<?= ct('stitches_read_more') ?>: <?= e($p['title']) ?>">
                        <figure class="project-card__media">
                            <?php if ($p['featured_image']): ?>
                            <img src="<?= e(media_url($p['featured_image'])) ?>" alt="<?= e($p['image_alt'] !== '' ? $p['image_alt'] : $p['title']) ?>" width="1200" height="900" <?= $i === 0 ? 'decoding="async"' : 'loading="lazy" decoding="async"' ?>>
                            <?php endif; ?>
                            <?php if ($p['video_url']): ?><span class="badge badge--video"><?= ct('stitches_video_badge') ?></span><?php endif; ?>
                        </figure>
                        <div class="project-card__body">
                            <span class="tag"><?= e($categories[$p['category']] ?? $p['category']) ?></span>
                            <h3 class="project-card__title"><?= e($p['title']) ?></h3>
                            <p class="project-card__summary"><?= e($p['short_summary']) ?></p>
                            <span class="project-card__more"><?= ct('stitches_read_more') ?> <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <p class="projects-empty" id="projectsEmpty" hidden><?= ct('stitches_empty') ?></p>
            <?php else: ?>
            <p class="projects-empty"><?= ct('stitches_empty') ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- =========================== LET'S CREATE TOGETHER =========================== -->
    <section class="section create" id="lets-create-together" aria-labelledby="createTitle">
        <div class="container">
            <header class="section-header reveal">
                <p class="eyebrow"><?= ct('create_eyebrow') ?></p>
                <h2 class="section-title" id="createTitle"><?= ct('create_title') ?></h2>
                <?= vine_divider_svg() ?>
                <div class="section-intro">
                    <?= ct_paragraphs('create_intro') ?>
                </div>
            </header>

            <ol class="process" aria-label="How we work">
                <?php foreach ([1 => '', 2 => ' reveal--delay', 3 => ' reveal--delay-2', 4 => ' reveal--delay-3'] as $n => $delay): ?>
                <li class="process__step glass-card reveal<?= $delay ?>">
                    <span class="process__num" aria-hidden="true"><?= sprintf('%02d', $n) ?></span>
                    <h3 class="process__title"><span class="visually-hidden">Step <?= $n ?>: </span><?= ct("step{$n}_title") ?></h3>
                    <p><?= ct_multiline("step{$n}_text") ?></p>
                </li>
                <?php endforeach; ?>
            </ol>

            <div class="enquiry glass-card reveal" id="enquiry-form">
                <span class="stitch-border" aria-hidden="true"></span>
                <?= corner_ornament_svg('corner corner--card-tl') ?>
                <?= corner_ornament_svg('corner corner--card-br') ?>
                <div class="enquiry__header">
                    <h3 class="enquiry__title"><?= ct('form_title') ?></h3>
                    <p><?= ct_multiline('form_intro') ?></p>
                </div>

                <form class="enquiry-form" id="enquiryForm" action="<?= e(url('api/submit_enquiry.php')) ?>" method="post" enctype="multipart/form-data" novalidate
                      data-submit-label="<?= ct('form_submit') ?>" data-success-title="<?= ct('form_success_title') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="form_started" value="<?= time() ?>">
                    <div class="hp-field" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-row form-row--2">
                        <div class="field">
                            <label for="name"><?= ct('form_label_name') ?> <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="name" name="name" autocomplete="name" maxlength="150" required>
                            <p class="field__error" id="name-error" role="alert"></p>
                        </div>
                        <div class="field">
                            <label for="email"><?= ct('form_label_email') ?> <span class="req" aria-hidden="true">*</span></label>
                            <input type="email" id="email" name="email" autocomplete="email" maxlength="190" required>
                            <p class="field__error" id="email-error" role="alert"></p>
                        </div>
                    </div>

                    <fieldset class="field">
                        <legend><?= ct('form_label_type') ?> <span class="req" aria-hidden="true">*</span></legend>
                        <div class="pills" id="projectTypePills">
                            <?php foreach (enquiry_project_types() as $i => $type): ?>
                            <label class="pill">
                                <input type="radio" name="project_type" value="<?= e($type) ?>" <?= $i === 0 ? 'required' : '' ?>>
                                <span><?= e($type) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="field__error" id="project_type-error" role="alert"></p>
                    </fieldset>

                    <div class="field">
                        <label for="story_details"><?= ct('form_label_details') ?> <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="story_details" name="story_details" rows="6" maxlength="5000" required placeholder="<?= ct('form_placeholder_details') ?>"></textarea>
                        <p class="field__error" id="story_details-error" role="alert"></p>
                    </div>

                    <div class="form-row form-row--2">
                        <div class="field">
                            <label for="target_date"><?= ct('form_label_date') ?></label>
                            <div class="date-wrap">
                                <input type="date" id="target_date" name="target_date" min="<?= e(date('Y-m-d')) ?>">
                                <svg class="date-wrap__icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="15" r="1.6" fill="#be7042"/></svg>
                            </div>
                            <p class="field__hint"><?= ct('form_hint_date') ?></p>
                            <p class="field__error" id="target_date-error" role="alert"></p>
                        </div>

                        <fieldset class="field">
                            <legend><?= ct('form_label_contact') ?> <span class="opt">(optional)</span></legend>
                            <div class="pills pills--small">
                                <?php foreach (contact_methods() as $method): ?>
                                <label class="pill">
                                    <input type="radio" name="preferred_contact" value="<?= e($method) ?>">
                                    <span><?= e($method) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="field field--conditional" id="contactHandleWrap" hidden>
                                <label for="contact_handle" id="contactHandleLabel">Your phone number</label>
                                <input type="text" id="contact_handle" name="contact_handle" maxlength="190">
                                <p class="field__error" id="contact_handle-error" role="alert"></p>
                            </div>
                        </fieldset>
                    </div>

                    <div class="field">
                        <span class="label" id="filesLabel"><?= ct('form_label_files') ?></span>
                        <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-labelledby="filesLabel" aria-describedby="filesHint">
                            <input type="file" id="reference_files" name="reference_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
                            <svg class="dropzone__icon" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="#be7042" stroke-width="3"/><circle cx="32" cy="32" r="17" fill="none" stroke="#a6b5a0" stroke-width="1.5" stroke-dasharray="4 3"/><path d="M32 40V24M25 30l7-7 7 7" fill="none" stroke="#0a5b59" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <p class="dropzone__text"><?= ct('form_drop_text') ?></p>
                            <p class="dropzone__hint" id="filesHint">JPG, PNG, WEBP, GIF or PDF · up to <?= ENQUIRY_MAX_FILES ?> files · max <?= (int) (ENQUIRY_MAX_FILE_BYTES / 1048576) ?> MB each</p>
                        </div>
                        <ul class="previews" id="previews" aria-live="polite"></ul>
                        <p class="field__error" id="reference_files-error" role="alert"></p>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn--primary btn--lg" type="submit" id="enquirySubmit">
                            <span class="btn__label"><?= ct('form_submit') ?></span>
                            <span class="btn__spinner" aria-hidden="true"></span>
                        </button>
                        <p class="form-note"><?= ct('form_note') ?></p>
                    </div>
                    <div class="form-status" id="formStatus" role="status" aria-live="polite" tabindex="-1"></div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php render_footer(); ?>

<!-- =========================== STORY LIGHTBOX =========================== -->
<div class="story-modal" id="storyModal" role="dialog" aria-modal="true" aria-labelledby="storyModalTitle" hidden>
    <div class="story-modal__backdrop" data-close-modal></div>
    <div class="story-modal__dialog" role="document">
        <button class="story-modal__close" type="button" data-close-modal aria-label="Close story">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <div class="story-modal__media">
            <div class="slider" id="storySlider">
                <div class="slider__track" id="sliderTrack"></div>
                <button class="slider__nav slider__nav--prev" type="button" id="sliderPrev" aria-label="Previous image">‹</button>
                <button class="slider__nav slider__nav--next" type="button" id="sliderNext" aria-label="Next image">›</button>
                <div class="slider__dots" id="sliderDots" role="tablist" aria-label="Gallery images"></div>
            </div>
            <div class="story-modal__video" id="storyVideo" hidden></div>
        </div>
        <div class="story-modal__content">
            <span class="tag" id="storyModalCategory"></span>
            <h2 class="story-modal__title" id="storyModalTitle"></h2>
            <p class="story-modal__date" id="storyModalDate"></p>
            <p class="story-modal__summary" id="storyModalSummary"></p>
            <div class="story-modal__story prose" id="storyModalStory"></div>
            <div class="story-modal__actions">
                <a class="btn btn--primary" href="#enquiry-form" data-close-modal data-modal-cta><?= ct('modal_cta') ?></a>
                <a class="btn btn--ghost" id="storyModalPermalink" href="#"><?= ct('modal_permalink') ?></a>
            </div>
        </div>
    </div>
</div>

<script id="storyData" type="application/json"><?= json_encode($storyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
</body>
</html>

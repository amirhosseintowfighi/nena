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
$services = [
    ['Weddings & Special Occasions', 'Embroidered bridal veils, personalised shoes, groom\'s accessories, wedding-party details and meaningful keepsakes with birth flowers, initials, dates and heirloom fabrics.'],
    ['Made for You', 'Embroidered gifts, personalised accessories, unique textile details and creative commissions for celebrations, cultural events and small businesses.'],
    ['Alterations & Reimagining', 'Everyday alterations and repairs plus creative transformations of existing garments — from shortening trousers to giving an old dress a new purpose.'],
];

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
    'slogan'      => 'Your Story, One Stitch at a Time.',
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
                <p class="eyebrow">Nena Studio · The Netherlands</p>
                <h1 class="hero__title" id="heroTitle">Your Story, <em>One Stitch</em> at a Time.</h1>
                <p class="hero__subtitle">Personalised textile creations, embroidery, and thoughtful alterations.</p>
                <div class="hero__intro">
                    <p>At Nena, I bring your ideas to life through sewing, embroidery, and creative craftsmanship.</p>
                    <p>From meaningful details for special occasions to everyday pieces made personal, and beloved garments given a new beginning, every project starts with you.</p>
                </div>
                <div class="hero__actions">
                    <a class="btn btn--primary" href="#lets-create-together">Let's Create Together</a>
                    <a class="btn btn--ghost" href="#behind-the-stitches">Behind the Stitches</a>
                </div>
            </div>
            <div class="hero__art reveal reveal--delay">
                <?= hero_hoop_svg() ?>
            </div>
        </div>

        <div class="container">
            <aside class="glass-card about-nena reveal" aria-labelledby="aboutNenaTitle">
                <span class="stitch-border" aria-hidden="true"></span>
                <h2 class="about-nena__title" id="aboutNenaTitle">A Little More About Nena</h2>
                <p>We believe the things we wear, use, and keep can carry stories. Nena is where I bring my love for craftsmanship and storytelling together, creating pieces inspired by the people they belong to.</p>
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
                <p class="eyebrow">Our Story</p>
                <h2 class="section-title" id="ourStoryTitle">Every Creation Begins with a Story.</h2>
                <?= vine_divider_svg() ?>
                <div class="prose">
                    <p class="lead">Nena began with something I've always loved: making things with my hands.</p>
                    <p>I grew up watching my mother sew, learning from her and gradually discovering my own love for fabrics, colours, textures, and the endless possibilities of creating something from scratch. Over the years, I explored embroidery, beadwork, and different ways of turning ordinary materials into something personal.</p>
                    <p>But what has always fascinated me just as much as making things is the stories people carry.</p>
                    <p>My background in culture, storytelling, and event management has taught me to look beyond objects and appreciate the memories, emotions, and connections behind them.</p>
                    <p>A piece of fabric might be part of a mother's wedding dress, a favourite shirt someone isn't ready to let go of, or an accessory waiting to become part of a special occasion.</p>
                    <p>That's what inspired Nena: a space where people's ideas, memories, and personal styles meet craftsmanship.</p>
                    <p>I don't believe there's one definition of beauty or one right way to create something meaningful. What matters to me is understanding what makes something special to the person it belongs to.</p>
                    <p class="signature">Nena is my way of bringing stories to life through the things we make, wear, and keep.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================== WHAT WE CREATE =========================== -->
    <section class="section services" id="what-we-create" aria-labelledby="servicesTitle">
        <div class="container">
            <header class="section-header reveal">
                <p class="eyebrow">What We Create</p>
                <h2 class="section-title" id="servicesTitle">Your Ideas, Made Tangible.</h2>
                <?= vine_divider_svg() ?>
                <div class="section-intro">
                    <p>Every piece starts with an idea. Sometimes it's a clear vision, sometimes just a feeling, a memory, or a little detail you'd like to make your own.</p>
                    <p>At Nena, I work with you to turn those ideas into something you can wear, use, gift, or keep.</p>
                    <p>Whether you're celebrating something special, personalising an everyday object, or giving a favourite piece a new life, I'd love to hear what you have in mind.</p>
                </div>
            </header>

            <div class="services__grid">
                <article class="glass-card service-card reveal" id="service-weddings">
                    <span class="stitch-border" aria-hidden="true"></span>
                    <div class="service-card__icon-wrap"><?= service_icon_svg('weddings') ?></div>
                    <h3 class="service-card__title">Weddings &amp; Special Occasions</h3>
                    <p class="service-card__subtitle">Little Details, Personal Stories.</p>
                    <div class="service-card__body">
                        <p>From embroidered bridal veils and personalised shoes to groom's accessories, wedding-party details, and meaningful keepsakes, Nena helps you add a personal touch to your special occasions.</p>
                        <p>Birth flowers, initials, meaningful dates, colours, or even a piece of fabric from someone you love can become part of your design.</p>
                        <p>Each project is created around your ideas, your style, and the details that matter to you.</p>
                    </div>
                    <a class="btn btn--outline service-card__cta" href="#enquiry-form" data-select-service="Weddings &amp; Special Occasions">Start a Wedding Project</a>
                </article>

                <article class="glass-card service-card reveal reveal--delay" id="service-made-for-you">
                    <span class="stitch-border" aria-hidden="true"></span>
                    <div class="service-card__icon-wrap"><?= service_icon_svg('made-for-you') ?></div>
                    <h3 class="service-card__title">Made for You</h3>
                    <p class="service-card__subtitle">Have an Idea? Let’s Make It Real.</p>
                    <div class="service-card__body">
                        <p>Not every meaningful creation needs a special occasion.</p>
                        <p>Whether it's an embroidered gift, a personalised accessory, a unique textile detail, or an idea you haven't quite figured out how to bring to life, Nena is a place to explore the possibilities.</p>
                        <p>I also welcome creative commissions for celebrations, cultural events, and small businesses.</p>
                        <p>Have something unusual in mind? Tell me about it. I'd love to explore what we can create together.</p>
                    </div>
                    <a class="btn btn--outline service-card__cta" href="#enquiry-form" data-select-service="Made for You">Share Your Idea</a>
                </article>

                <article class="glass-card service-card reveal reveal--delay-2" id="service-alterations">
                    <span class="stitch-border" aria-hidden="true"></span>
                    <div class="service-card__icon-wrap"><?= service_icon_svg('alterations') ?></div>
                    <h3 class="service-card__title">Alterations &amp; Reimagining</h3>
                    <p class="service-card__subtitle">Some Things Deserve a Second Story.</p>
                    <div class="service-card__body">
                        <p>Some clothes need a better fit. Others need a little repair. And some deserve to become something entirely new.</p>
                        <p>From everyday alterations and repairs to creative transformations of existing garments, I help you make the most of pieces you already own.</p>
                        <p>Whether it's shortening a pair of trousers, repairing a favourite sweater, or finding a new purpose for an old dress, the goal is simple: to help you keep wearing, using, and enjoying the things that matter to you.</p>
                    </div>
                    <a class="btn btn--outline service-card__cta" href="#enquiry-form" data-select-service="Alterations &amp; Reimagining">Book an Alteration</a>
                </article>
            </div>
        </div>
    </section>

    <!-- =========================== BEHIND THE STITCHES =========================== -->
    <section class="section section--alt stitches" id="behind-the-stitches" aria-labelledby="stitchesTitle">
        <div class="container">
            <header class="section-header reveal">
                <p class="eyebrow">Behind the Stitches</p>
                <h2 class="section-title" id="stitchesTitle">Every Piece Has a Story. Here's How It Comes to Life.</h2>
                <?= vine_divider_svg() ?>
                <p class="section-intro section-intro--lead">Every piece has a story. Discover the ideas, memories, and creative processes behind Nena's projects, from the first inspiration to the final stitch.</p>
            </header>

            <div class="stitches__narrative glass-card reveal">
                <span class="stitch-border" aria-hidden="true"></span>
                <p>There's often more to a handmade piece than what you see at first glance.</p>
                <p>Behind every stitch, there's an idea, a conversation, a choice of colour or material, and sometimes a memory that makes the whole creation meaningful.</p>
                <p>Here, I share the stories behind my projects: where the ideas came from, how they developed, the little decisions along the way, and the process of turning them into something real.</p>
                <p>From the first sketch to the final stitch, this is where you can discover what goes into each creation.</p>
            </div>

            <?php if ($projects): ?>
            <div class="filters reveal" role="toolbar" aria-label="Filter projects by category">
                <button class="filter-pill is-active" type="button" data-filter="all" aria-pressed="true">All Stories</button>
                <?php foreach ($categories as $key => $label): ?>
                <button class="filter-pill" type="button" data-filter="<?= e($key) ?>" aria-pressed="false"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="projects-grid" id="projectsGrid">
                <?php foreach ($projects as $i => $p): ?>
                <article class="project-card reveal" data-category="<?= e($p['category']) ?>">
                    <a class="project-card__link" href="<?= e(project_url($p['slug'])) ?>" data-story="<?= e($p['slug']) ?>" aria-label="Read the story: <?= e($p['title']) ?>">
                        <figure class="project-card__media">
                            <?php if ($p['featured_image']): ?>
                            <img src="<?= e(media_url($p['featured_image'])) ?>" alt="<?= e($p['image_alt'] !== '' ? $p['image_alt'] : $p['title']) ?>" width="1200" height="900" <?= $i === 0 ? 'decoding="async"' : 'loading="lazy" decoding="async"' ?>>
                            <?php endif; ?>
                            <?php if ($p['video_url']): ?><span class="badge badge--video">Process video</span><?php endif; ?>
                        </figure>
                        <div class="project-card__body">
                            <span class="tag"><?= e($categories[$p['category']] ?? $p['category']) ?></span>
                            <h3 class="project-card__title"><?= e($p['title']) ?></h3>
                            <p class="project-card__summary"><?= e($p['short_summary']) ?></p>
                            <span class="project-card__more">Read the story <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            <p class="projects-empty" id="projectsEmpty" hidden>New stories in this category are on the embroidery hoop — check back soon.</p>
            <?php else: ?>
            <p class="projects-empty">New stories are on the embroidery hoop — check back soon.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- =========================== LET'S CREATE TOGETHER =========================== -->
    <section class="section create" id="lets-create-together" aria-labelledby="createTitle">
        <div class="container">
            <header class="section-header reveal">
                <p class="eyebrow">Let's Create Together</p>
                <h2 class="section-title" id="createTitle">It All Starts with an Idea.</h2>
                <?= vine_divider_svg() ?>
                <div class="section-intro">
                    <p>Have something in mind? Maybe you know exactly what you want, or perhaps you just have a feeling, a memory, or a little inspiration you'd like to explore.</p>
                    <p>Either way, We'd love to hear about it.</p>
                    <p>Every project is different. Some are simple and straightforward, while others involve more design, discussion, and collaboration. Here's a general idea of how we can work together.</p>
                </div>
            </header>

            <ol class="process" aria-label="How we work">
                <li class="process__step glass-card reveal">
                    <span class="process__num" aria-hidden="true">01</span>
                    <h3 class="process__title"><span class="visually-hidden">Step 1: </span>Tell Us Your Story</h3>
                    <p>Share your idea, the occasion, or the piece you'd like to personalise, repair, or transform. You can send inspiration photos, sketches, colours, or simply describe what you have in mind. You don't need to have everything figured out.</p>
                </li>
                <li class="process__step glass-card reveal reveal--delay">
                    <span class="process__num" aria-hidden="true">02</span>
                    <h3 class="process__title"><span class="visually-hidden">Step 2: </span>Let's Explore the Possibilities</h3>
                    <p>We'll talk about your ideas, materials, preferences, and timeline. For custom projects, I'll help you explore the possibilities and work towards a design that feels right for you. Once we've agreed on the details, We'll provide a quote and an estimated completion date.</p>
                </li>
                <li class="process__step glass-card reveal reveal--delay-2">
                    <span class="process__num" aria-hidden="true">03</span>
                    <h3 class="process__title"><span class="visually-hidden">Step 3: </span>Bringing It to Life</h3>
                    <p>Once everything is agreed, We'll get to work. Depending on the project, this might involve embroidery, sewing, embellishment, or transforming something you already own. We'll take care of the details and keep you updated when needed.</p>
                </li>
                <li class="process__step glass-card reveal reveal--delay-3">
                    <span class="process__num" aria-hidden="true">04</span>
                    <h3 class="process__title"><span class="visually-hidden">Step 4: </span>Made for You, Ready to Keep</h3>
                    <p>When your piece is ready, we'll arrange collection or delivery. Whether it's something to wear, gift, celebrate with, or simply enjoy again, We hope it becomes part of your story for years to come.</p>
                </li>
            </ol>

            <div class="enquiry glass-card reveal" id="enquiry-form">
                <span class="stitch-border" aria-hidden="true"></span>
                <?= corner_ornament_svg('corner corner--card-tl') ?>
                <?= corner_ornament_svg('corner corner--card-br') ?>
                <div class="enquiry__header">
                    <h3 class="enquiry__title">Let's Create Something Together</h3>
                    <p>Have something in mind? Whether it's a fully formed idea or just the beginning of one, We'd love to hear about it.</p>
                </div>

                <form class="enquiry-form" id="enquiryForm" action="<?= e(url('api/submit_enquiry.php')) ?>" method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="form_started" value="<?= time() ?>">
                    <div class="hp-field" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-row form-row--2">
                        <div class="field">
                            <label for="name">Your Name <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="name" name="name" autocomplete="name" maxlength="150" required>
                            <p class="field__error" id="name-error" role="alert"></p>
                        </div>
                        <div class="field">
                            <label for="email">Email Address <span class="req" aria-hidden="true">*</span></label>
                            <input type="email" id="email" name="email" autocomplete="email" maxlength="190" required>
                            <p class="field__error" id="email-error" role="alert"></p>
                        </div>
                    </div>

                    <fieldset class="field">
                        <legend>What Type of Project Is It? <span class="req" aria-hidden="true">*</span></legend>
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
                        <label for="story_details">What Do You Have in Mind? <span class="req" aria-hidden="true">*</span></label>
                        <textarea id="story_details" name="story_details" rows="6" maxlength="5000" required placeholder="Tell me about the occasion, the piece, the memory or the little detail you'd like to make your own…"></textarea>
                        <p class="field__error" id="story_details-error" role="alert"></p>
                    </div>

                    <div class="form-row form-row--2">
                        <div class="field">
                            <label for="target_date">When Do You Need It?</label>
                            <div class="date-wrap">
                                <input type="date" id="target_date" name="target_date" min="<?= e(date('Y-m-d')) ?>">
                                <svg class="date-wrap__icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="15" r="1.6" fill="#be7042"/></svg>
                            </div>
                            <p class="field__hint">Optional — an approximate date is perfect.</p>
                            <p class="field__error" id="target_date-error" role="alert"></p>
                        </div>

                        <fieldset class="field">
                            <legend>Preferred Contact Method <span class="opt">(optional)</span></legend>
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
                        <span class="label" id="filesLabel">Inspiration &amp; References</span>
                        <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-labelledby="filesLabel" aria-describedby="filesHint">
                            <input type="file" id="reference_files" name="reference_files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
                            <svg class="dropzone__icon" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="22" fill="none" stroke="#be7042" stroke-width="3"/><circle cx="32" cy="32" r="17" fill="none" stroke="#a6b5a0" stroke-width="1.5" stroke-dasharray="4 3"/><path d="M32 40V24M25 30l7-7 7 7" fill="none" stroke="#0a5b59" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <p class="dropzone__text"><strong>Drag &amp; drop</strong> photos, sketches or colour swatches here, or <span class="dropzone__browse">browse your files</span></p>
                            <p class="dropzone__hint" id="filesHint">JPG, PNG, WEBP, GIF or PDF · up to <?= ENQUIRY_MAX_FILES ?> files · max <?= (int) (ENQUIRY_MAX_FILE_BYTES / 1048576) ?> MB each</p>
                        </div>
                        <ul class="previews" id="previews" aria-live="polite"></ul>
                        <p class="field__error" id="reference_files-error" role="alert"></p>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn--primary btn--lg" type="submit" id="enquirySubmit">
                            <span class="btn__label">Send My Story</span>
                            <span class="btn__spinner" aria-hidden="true"></span>
                        </button>
                        <p class="form-note">I usually reply within two working days.</p>
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
                <a class="btn btn--primary" href="#enquiry-form" data-close-modal data-modal-cta>Start Your Own Story</a>
                <a class="btn btn--ghost" id="storyModalPermalink" href="#">Open full story page</a>
            </div>
        </div>
    </div>
</div>

<script id="storyData" type="application/json"><?= json_encode($storyData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
</body>
</html>

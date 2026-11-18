<?php
/**
 * Editable website texts.
 *
 * Every text on the public site is defined here with its default value. Admins edit them in
 * Admin > Website Texts; edited values are stored in the `settings` table under "content_<key>".
 * An empty value falls back to the default below.
 *
 * Field types:
 *   text        single line
 *   inline      single line; *words* become <em>words</em> (italic accent)
 *   paragraphs  multi-line; a blank line starts a new paragraph
 *   textarea    multi-line plain text (no paragraph splitting)
 */

declare(strict_types=1);

function content_schema(): array
{
    return [
        'navigation' => [
            'title'  => 'Navigation',
            'fields' => [
                'nav_home'     => ['Menu: Home', 'text', 'Home'],
                'nav_story'    => ['Menu: Our Story', 'text', 'Our Story'],
                'nav_create'   => ['Menu: What We Create', 'text', 'What We Create'],
                'nav_stitches' => ['Menu: Behind the Stitches', 'text', 'Behind the Stitches'],
                'nav_cta'      => ['Menu button', 'text', "Let's Create Together"],
            ],
        ],
        'hero' => [
            'title'  => 'Hero (top of the page)',
            'fields' => [
                'hero_eyebrow'       => ['Small label above the headline', 'text', 'Nena Studio · The Netherlands'],
                'hero_title'         => ['Main headline (H1) — wrap words in *stars* for the copper italic accent', 'inline', 'Your Story, *One Stitch* at a Time.'],
                'hero_subtitle'      => ['Sub-headline', 'text', 'Personalised textile creations, embroidery, and thoughtful alterations.'],
                'hero_intro'         => ['Intro text', 'paragraphs', "At Nena, I bring your ideas to life through sewing, embroidery, and creative craftsmanship.\n\nFrom meaningful details for special occasions to everyday pieces made personal, and beloved garments given a new beginning, every project starts with you."],
                'hero_cta_primary'   => ['Primary button', 'text', "Let's Create Together"],
                'hero_cta_secondary' => ['Secondary button', 'text', 'Behind the Stitches'],
                'about_title'        => ['"A Little More" box: heading', 'text', 'A Little More About Nena'],
                'about_text'         => ['"A Little More" box: text', 'paragraphs', 'We believe the things we wear, use, and keep can carry stories. Nena is where I bring my love for craftsmanship and storytelling together, creating pieces inspired by the people they belong to.'],
            ],
        ],
        'story' => [
            'title'  => 'Our Story',
            'fields' => [
                'story_eyebrow'   => ['Small label', 'text', 'Our Story'],
                'story_title'     => ['Heading (H2)', 'text', 'Every Creation Begins with a Story.'],
                'story_lead'      => ['Opening line (large italic)', 'text', "Nena began with something I've always loved: making things with my hands."],
                'story_body'      => ['Story text', 'paragraphs', "I grew up watching my mother sew, learning from her and gradually discovering my own love for fabrics, colours, textures, and the endless possibilities of creating something from scratch. Over the years, I explored embroidery, beadwork, and different ways of turning ordinary materials into something personal.\n\nBut what has always fascinated me just as much as making things is the stories people carry.\n\nMy background in culture, storytelling, and event management has taught me to look beyond objects and appreciate the memories, emotions, and connections behind them.\n\nA piece of fabric might be part of a mother's wedding dress, a favourite shirt someone isn't ready to let go of, or an accessory waiting to become part of a special occasion.\n\nThat's what inspired Nena: a space where people's ideas, memories, and personal styles meet craftsmanship.\n\nI don't believe there's one definition of beauty or one right way to create something meaningful. What matters to me is understanding what makes something special to the person it belongs to."],
                'story_signature' => ['Closing line (italic)', 'text', 'Nena is my way of bringing stories to life through the things we make, wear, and keep.'],
            ],
        ],
        'services' => [
            'title'  => 'What We Create (services)',
            'fields' => [
                'services_eyebrow'   => ['Small label', 'text', 'What We Create'],
                'services_title'     => ['Heading (H2)', 'text', 'Your Ideas, Made Tangible.'],
                'services_intro'     => ['Intro text', 'paragraphs', "Every piece starts with an idea. Sometimes it's a clear vision, sometimes just a feeling, a memory, or a little detail you'd like to make your own.\n\nAt Nena, I work with you to turn those ideas into something you can wear, use, gift, or keep.\n\nWhether you're celebrating something special, personalising an everyday object, or giving a favourite piece a new life, I'd love to hear what you have in mind."],
                'service1_title'     => ['Card 1: title', 'text', 'Weddings & Special Occasions'],
                'service1_subtitle'  => ['Card 1: subtitle', 'text', 'Little Details, Personal Stories.'],
                'service1_body'      => ['Card 1: text', 'paragraphs', "From embroidered bridal veils and personalised shoes to groom's accessories, wedding-party details, and meaningful keepsakes, Nena helps you add a personal touch to your special occasions.\n\nBirth flowers, initials, meaningful dates, colours, or even a piece of fabric from someone you love can become part of your design.\n\nEach project is created around your ideas, your style, and the details that matter to you."],
                'service1_cta'       => ['Card 1: button', 'text', 'Start a Wedding Project'],
                'service2_title'     => ['Card 2: title', 'text', 'Made for You'],
                'service2_subtitle'  => ['Card 2: subtitle', 'text', 'Have an Idea? Let’s Make It Real.'],
                'service2_body'      => ['Card 2: text', 'paragraphs', "Not every meaningful creation needs a special occasion.\n\nWhether it's an embroidered gift, a personalised accessory, a unique textile detail, or an idea you haven't quite figured out how to bring to life, Nena is a place to explore the possibilities.\n\nI also welcome creative commissions for celebrations, cultural events, and small businesses.\n\nHave something unusual in mind? Tell me about it. I'd love to explore what we can create together."],
                'service2_cta'       => ['Card 2: button', 'text', 'Share Your Idea'],
                'service3_title'     => ['Card 3: title', 'text', 'Alterations & Reimagining'],
                'service3_subtitle'  => ['Card 3: subtitle', 'text', 'Some Things Deserve a Second Story.'],
                'service3_body'      => ['Card 3: text', 'paragraphs', "Some clothes need a better fit. Others need a little repair. And some deserve to become something entirely new.\n\nFrom everyday alterations and repairs to creative transformations of existing garments, I help you make the most of pieces you already own.\n\nWhether it's shortening a pair of trousers, repairing a favourite sweater, or finding a new purpose for an old dress, the goal is simple: to help you keep wearing, using, and enjoying the things that matter to you."],
                'service3_cta'       => ['Card 3: button', 'text', 'Book an Alteration'],
            ],
        ],
        'stitches' => [
            'title'  => 'Behind the Stitches (section texts)',
            'fields' => [
                'stitches_eyebrow'   => ['Small label', 'text', 'Behind the Stitches'],
                'stitches_title'     => ['Heading (H2)', 'text', "Every Piece Has a Story. Here's How It Comes to Life."],
                'stitches_intro'     => ['Short intro (italic)', 'text', "Every piece has a story. Discover the ideas, memories, and creative processes behind Nena's projects, from the first inspiration to the final stitch."],
                'stitches_narrative' => ['Narrative box', 'paragraphs', "There's often more to a handmade piece than what you see at first glance.\n\nBehind every stitch, there's an idea, a conversation, a choice of colour or material, and sometimes a memory that makes the whole creation meaningful.\n\nHere, I share the stories behind my projects: where the ideas came from, how they developed, the little decisions along the way, and the process of turning them into something real.\n\nFrom the first sketch to the final stitch, this is where you can discover what goes into each creation."],
                'stitches_filter_all' => ['Filter button: all', 'text', 'All Stories'],
                'stitches_read_more' => ['Card link', 'text', 'Read the story'],
                'stitches_video_badge' => ['Badge for stories with a video', 'text', 'Process video'],
                'stitches_empty'     => ['Message when there are no stories', 'text', 'New stories are on the embroidery hoop — check back soon.'],
                'modal_cta'          => ['Story popup: main button', 'text', 'Start Your Own Story'],
                'modal_permalink'    => ['Story popup: link to full page', 'text', 'Open full story page'],
                'storypage_cta_text' => ['Story page: closing question', 'text', "Have a story of your own you'd like to bring to life?"],
                'storypage_cta_button' => ['Story page: button', 'text', "Let's Create Together"],
                'storypage_related'  => ['Story page: "more stories" heading', 'text', 'More Stories from the Studio'],
                'storypage_video_caption' => ['Story page: video caption', 'text', 'From the studio: the process behind this piece.'],
            ],
        ],
        'process' => [
            'title'  => "Let's Create Together (how we work)",
            'fields' => [
                'create_eyebrow' => ['Small label', 'text', "Let's Create Together"],
                'create_title'   => ['Heading (H2)', 'text', 'It All Starts with an Idea.'],
                'create_intro'   => ['Intro text', 'paragraphs', "Have something in mind? Maybe you know exactly what you want, or perhaps you just have a feeling, a memory, or a little inspiration you'd like to explore.\n\nEither way, We'd love to hear about it.\n\nEvery project is different. Some are simple and straightforward, while others involve more design, discussion, and collaboration. Here's a general idea of how we can work together."],
                'step1_title'    => ['Step 01: title', 'text', 'Tell Us Your Story'],
                'step1_text'     => ['Step 01: text', 'textarea', "Share your idea, the occasion, or the piece you'd like to personalise, repair, or transform. You can send inspiration photos, sketches, colours, or simply describe what you have in mind. You don't need to have everything figured out."],
                'step2_title'    => ['Step 02: title', 'text', "Let's Explore the Possibilities"],
                'step2_text'     => ['Step 02: text', 'textarea', "We'll talk about your ideas, materials, preferences, and timeline. For custom projects, I'll help you explore the possibilities and work towards a design that feels right for you. Once we've agreed on the details, We'll provide a quote and an estimated completion date."],
                'step3_title'    => ['Step 03: title', 'text', 'Bringing It to Life'],
                'step3_text'     => ['Step 03: text', 'textarea', "Once everything is agreed, We'll get to work. Depending on the project, this might involve embroidery, sewing, embellishment, or transforming something you already own. We'll take care of the details and keep you updated when needed."],
                'step4_title'    => ['Step 04: title', 'text', 'Made for You, Ready to Keep'],
                'step4_text'     => ['Step 04: text', 'textarea', "When your piece is ready, we'll arrange collection or delivery. Whether it's something to wear, gift, celebrate with, or simply enjoy again, We hope it becomes part of your story for years to come."],
            ],
        ],
        'form' => [
            'title'  => 'Enquiry form',
            'fields' => [
                'form_title'          => ['Form heading', 'text', "Let's Create Something Together"],
                'form_intro'          => ['Form intro', 'textarea', "Have something in mind? Whether it's a fully formed idea or just the beginning of one, We'd love to hear about it."],
                'form_label_name'     => ['Label: name', 'text', 'Your Name'],
                'form_label_email'    => ['Label: email', 'text', 'Email Address'],
                'form_label_type'     => ['Label: project type', 'text', 'What Type of Project Is It?'],
                'form_label_details'  => ['Label: details', 'text', 'What Do You Have in Mind?'],
                'form_placeholder_details' => ['Placeholder: details', 'text', "Tell me about the occasion, the piece, the memory or the little detail you'd like to make your own…"],
                'form_label_date'     => ['Label: date', 'text', 'When Do You Need It?'],
                'form_hint_date'      => ['Hint under the date', 'text', 'Optional — an approximate date is perfect.'],
                'form_label_contact'  => ['Label: preferred contact', 'text', 'Preferred Contact Method'],
                'form_label_files'    => ['Label: files', 'text', 'Inspiration & References'],
                'form_drop_text'      => ['Upload box text', 'text', 'Drag & drop photos, sketches or colour swatches here, or browse your files'],
                'form_submit'         => ['Submit button', 'text', 'Send My Story'],
                'form_note'           => ['Note next to the button', 'text', 'I usually reply within two working days.'],
                'form_success_title'  => ['Success message: heading', 'text', 'Thank you — your story is on its way!'],
                'form_success_text'   => ['Success message: text ({name} = visitor\'s name)', 'textarea', "Thank you, {name}! I've received your story and will be in touch soon to explore it together."],
            ],
        ],
        'footer' => [
            'title'  => 'Footer',
            'fields' => [
                'footer_tagline'       => ['Tagline', 'text', 'Your Story, One Stitch at a Time.'],
                'footer_description'   => ['Short description', 'textarea', 'Personalised textile creations, embroidery, and thoughtful alterations — made in the Netherlands.'],
                'footer_explore_title' => ['Column heading: links', 'text', 'Explore'],
                'footer_contact_title' => ['Column heading: contact', 'text', 'Say Hello'],
                'footer_location'      => ['Location line', 'text', 'Studio based in the Netherlands'],
            ],
        ],
    ];
}

/** Flat map: key => [label, type, default]. */
function content_fields(): array
{
    static $fields = null;
    if ($fields === null) {
        $fields = [];
        foreach (content_schema() as $group) {
            $fields += $group['fields'];
        }
    }
    return $fields;
}

/** Raw text value (admin override or default). */
function content(string $key): string
{
    $default = content_fields()[$key][2] ?? '';
    return setting('content_' . $key, $default);
}

/** Escaped single-line text. */
function ct(string $key): string
{
    return e(content($key));
}

/** Escaped text where *words* become <em>words</em>. */
function ct_inline(string $key): string
{
    return preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e(content($key))) ?? e(content($key));
}

/** Escaped multi-line text with line breaks preserved. */
function ct_multiline(string $key): string
{
    return nl2br(e(trim(content($key))), false);
}

/** Paragraph blocks (blank line = new <p>). */
function ct_paragraphs(string $key, string $class = ''): string
{
    $html = '';
    $attr = $class !== '' ? ' class="' . e($class) . '"' : '';
    foreach (preg_split("/\R\s*\R/u", trim(str_replace("\r\n", "\n", content($key)))) ?: [] as $block) {
        $block = trim($block);
        if ($block !== '') {
            $html .= '<p' . $attr . '>' . nl2br(e($block), false) . '</p>';
        }
    }
    return $html;
}

-- =====================================================================
--  Nena Studio — MySQL / MariaDB schema + seed data
--  Import via cPanel > phpMyAdmin > (select your database) > Import
--  Charset: utf8mb4 / Engine: InnoDB
--
--  Default admin login (CHANGE IT right after first login in
--  Admin > Settings > Change Password):
--      Username: admin
--      Password: (not published — ask the project owner)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `enquiries`;
DROP TABLE IF EXISTS `portfolio_projects`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `settings`;

-- ---------------------------------------------------------------------
--  settings — key/value store for SEO, contact info and social links
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name',          'Nena Studio'),
('meta_title',         'Nena Studio | Personalised Bridal Accessories, Embroidery & Alterations in the Netherlands'),
('meta_description',   'Nena Studio is a creative textile studio in the Netherlands creating personalised wedding and event accessories, custom embroidery, and thoughtful garment alterations. Your story, one stitch at a time.'),
('meta_keywords',      'Nena Studio, personalised wedding accessories, embroidered bridal veil, custom embroidery Netherlands, garment alterations Netherlands, bridal accessories NL, embroidered gifts, kleding vermaken, borduren op maat, upcycling clothes'),
('canonical_url',      ''),
('og_image',           'assets/images/og-image.jpg'),
('contact_email',      'hello@nenastudio.nl'),
('notification_email', 'hello@nenastudio.nl'),
('contact_phone',      ''),
('whatsapp_number',    ''),
('instagram_url',      'https://www.instagram.com/nenastudio'),
('facebook_url',       ''),
('pinterest_url',      ''),
('address_city',       ''),
('address_region',     'Netherlands'),
('address_country',    'NL'),
('geo_latitude',       '52.3676'),
('geo_longitude',      '4.9041'),
('google_site_verification', '');

-- ---------------------------------------------------------------------
--  admins — secure password_hash() login
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60)  NOT NULL,
  `email`         VARCHAR(190) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `last_login`    DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password: (not published — ask the project owner)  (bcrypt via PHP password_hash)
INSERT INTO `admins` (`username`, `email`, `password_hash`) VALUES
('admin', 'hello@nenastudio.nl', '$2y$10$WOkSn7gqj9zfw2/OGxIs3eKI43jfF.LBeeLwzo4aalCbQhtznzxqO');

-- ---------------------------------------------------------------------
--  login_attempts — brute-force protection for /admin
-- ---------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address`   VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  portfolio_projects — "Behind the Stitches"
-- ---------------------------------------------------------------------
CREATE TABLE `portfolio_projects` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`              VARCHAR(190) NOT NULL,
  `slug`               VARCHAR(190) NOT NULL,
  `category`           VARCHAR(40)  NOT NULL DEFAULT 'weddings',
  `short_summary`      VARCHAR(500) NOT NULL DEFAULT '',
  `full_story`         MEDIUMTEXT NULL,
  `image_alt`          VARCHAR(255) NOT NULL DEFAULT '',
  `featured_image`     VARCHAR(255) NULL,
  `video_url`          VARCHAR(500) NULL,
  `media_gallery_json` TEXT NULL,
  `is_published`       TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`         INT NOT NULL DEFAULT 0,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_project_slug` (`slug`),
  KEY `idx_project_category` (`category`),
  KEY `idx_project_published` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `portfolio_projects`
(`title`, `slug`, `category`, `short_summary`, `full_story`, `image_alt`, `featured_image`, `video_url`, `media_gallery_json`, `is_published`, `sort_order`, `created_at`) VALUES
(
  'A Veil Embroidered with Her Grandmother''s Lace',
  'veil-embroidered-with-grandmothers-lace',
  'weddings',
  'A cathedral-length bridal veil finished with lace saved from a grandmother''s 1962 wedding dress, hand-embroidered birth flowers and the couple''s initials.',
  '## Where the idea came from
Lotte came to the studio with a small linen bag. Inside was a strip of ivory lace, carefully folded, that had once edged her grandmother''s wedding dress in 1962. She didn''t want to wear the dress — she wanted to carry a piece of it with her down the aisle.

## Finding the design together
We spent our first conversation simply looking at the lace: its scalloped edge, the tiny lily-of-the-valley motif, the way the cotton had softened over sixty years. From there we decided the lace would frame the lower edge of a soft tulle veil, so it would be seen in every photograph without competing with her dress.

To make the veil feel like hers, we added birth flowers for Lotte and her partner Mees — a honeysuckle and a sweet pea — embroidered in tonal ivory silk thread, with a single sprig picked out in a warm copper to echo the autumn wedding colours.

## The little decisions along the way
The lace was too delicate to stitch directly onto tulle, so each section was first stabilised on a fine silk organza, then hand-appliquéd so the original edge stayed visible. The initials "L & M" and the wedding date sit in the corner where only Lotte would find them — a private detail just for her.

## The final stitch
The finished veil took around 40 hours of handwork. On the morning of the wedding, Lotte''s grandmother was the one who placed it in her hair.',
  'Hand-embroidered tulle bridal veil edged with vintage ivory lace, birth flowers and the initials L & M in an embroidery hoop',
  'assets/images/portfolio/lace-veil-cover.svg',
  '',
  '["assets/images/portfolio/lace-veil-cover.svg","assets/images/portfolio/lace-veil-detail.svg"]',
  1, 1, '2026-05-14 10:00:00'
),
(
  'The Tulip Field Denim Jacket',
  'tulip-field-denim-jacket',
  'made-for-you',
  'A favourite denim jacket turned into a wearable love letter to the Dutch bulb fields — with satin-stitch tulips, glass-bead dew drops and a hidden message inside the collar.',
  '## Where the idea came from
Sara moved to the Netherlands five years ago, and every spring she cycles out to the bulb fields with her daughter. She asked whether that feeling — the rows of colour, the cold April wind, the laughter — could live on the back of the denim jacket she wears almost every day.

## Exploring the possibilities
We sketched three directions: a full landscape, a single oversized bloom, and a small cluster of tulips placed across the back yoke. Sara chose the cluster, because it felt "like a bouquet you''d pick on the way home." We matched the thread colours to a photo she took on their last visit: terracotta, apricot and a deep green that almost matches the Nena teal.

## Bringing it to life
Each tulip was worked in long-and-short satin stitch to give the petals a natural shading, with stems in a raised stem stitch so they catch the light. A row of tiny glass beads runs beneath the flowers like morning dew. Inside the collar, in small backstitch, is the date of her daughter''s first visit to the fields.

## Made to keep
The jacket was washed and pressed before delivery so it was ready to wear straight away — and Sara wore it out of the studio.',
  'Denim jacket with hand-embroidered terracotta and apricot tulips and glass bead details on the back yoke',
  'assets/images/portfolio/tulip-jacket-cover.svg',
  '',
  '["assets/images/portfolio/tulip-jacket-cover.svg","assets/images/portfolio/tulip-jacket-hoop.svg"]',
  1, 2, '2026-06-02 10:00:00'
),
(
  'A Mother''s Dress, Given a Second Story',
  'mothers-dress-given-a-second-story',
  'alterations',
  'A 1989 wedding dress reimagined into an elegant two-piece for a daughter''s civil ceremony — keeping the original silk, the buttons and a hand-embroidered vine along the hem.',
  '## Where the idea came from
Noor''s mother married in 1989 in a dress with dramatic sleeves and yards of silk taffeta. Noor loved the fabric and everything it stood for, but the silhouette didn''t feel like her. Her question was simple: "Can it become something I would actually choose to wear?"

## Exploring the possibilities
We started with a fitting in the original dress, pinning and folding to see what the fabric wanted to do. Together we settled on a softly structured cropped top and a fluid midi skirt, so Noor could wear the pieces again — together or separately — long after the ceremony.

## Bringing it to life
The sleeves were carefully unpicked and their silk reused for the new top, so nothing from the original dress was wasted. The covered buttons from the back of her mother''s bodice now close the top. Along the skirt hem, a botanical vine is embroidered in sage and copper, with one small flower for each year between the two weddings.

## Made for you, ready to keep
Final adjustments were made at a second fitting a week before the ceremony. The leftover fabric was turned into a small pouch for her mother — so the dress now has a place in both their wardrobes.',
  'Vintage silk wedding dress reimagined into a two-piece set with a hand-embroidered sage and copper botanical vine along the hem',
  'assets/images/portfolio/mothers-dress-cover.svg',
  '',
  '["assets/images/portfolio/mothers-dress-cover.svg","assets/images/portfolio/mothers-dress-process.svg"]',
  1, 3, '2026-07-21 10:00:00'
);

-- ---------------------------------------------------------------------
--  enquiries — "Let's Create Together" form submissions
-- ---------------------------------------------------------------------
CREATE TABLE `enquiries` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                 VARCHAR(150) NOT NULL,
  `email`                VARCHAR(190) NOT NULL,
  `project_type`         VARCHAR(80)  NOT NULL DEFAULT 'Other',
  `story_details`        TEXT NOT NULL,
  `target_date`          DATE NULL,
  `preferred_contact`    VARCHAR(40)  NULL,
  `contact_handle`       VARCHAR(190) NULL,
  `reference_files_json` TEXT NULL,
  `status`               ENUM('New','In Discussion','In Progress','Completed') NOT NULL DEFAULT 'New',
  `admin_notes`          TEXT NULL,
  `ip_address`           VARCHAR(45) NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enquiry_status` (`status`),
  KEY `idx_enquiry_created` (`created_at`),
  KEY `idx_enquiry_ip` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

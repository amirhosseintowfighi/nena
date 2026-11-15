# Nena Studio — Website & Admin Panel

**Your Story, One Stitch at a Time.**
Production-ready PHP 8.1+ / MySQL website for Nena Studio — a creative textile studio in the Netherlands (personalised wedding & event accessories, embroidery, garment alterations) with a session-protected admin panel.

---

## راهنمای نصب روی cPanel (فارسی)

1. **آپلود فایل‌ها:** کل محتوای این مخزن را در `public_html` (یا یک زیرپوشه) آپلود کنید. فایل‌های مخفی `.htaccess` و `.user.ini` را هم حتماً آپلود کنید.
2. **ساخت دیتابیس:** در cPanel ← *MySQL Databases* یک دیتابیس و یک یوزر بسازید و یوزر را با دسترسی *ALL PRIVILEGES* به دیتابیس اضافه کنید.
3. **ایمپورت:** در *phpMyAdmin* دیتابیس را انتخاب کنید ← *Import* ← فایل `database.sql` را ایمپورت کنید.
4. **اتصال:** فایل `includes/config.php` را باز کنید و `DB_NAME`، `DB_USER`، `DB_PASS` را وارد کنید. (اختیاری: `SITE_URL` را مثلاً `https://www.nenastudio.nl` بگذارید.)
5. **دسترسی پوشه‌ها:** پوشه‌های `uploads/enquiries` و `uploads/portfolio` باید قابل نوشتن باشند (معمولاً 755).
6. **ورود به پنل:** به آدرس `/admin` بروید.
   - نام کاربری و رمز عبورِ پیش‌فرض را از مدیر پروژه بگیرید (عمداً در این مخزنِ عمومی نوشته نشده است).
   - **بلافاصله** از بخش *SEO & Settings* رمز را تغییر دهید.
7. **SSL:** بعد از فعال شدن SSL، خطوط Force HTTPS را در `.htaccess` از حالت کامنت خارج کنید.
8. **لوگوها:** فایل‌های `NENA MAIN.svg`، `NENA WHITE.svg` و `NENA BLACK.svg` در `assets/images/` قرار دارند؛ اگر حذف شوند، نسخه‌ی inline SVG به‌صورت خودکار نمایش داده می‌شود.

---

## Quick start (English)

1. Upload everything to `public_html/` (including `.htaccess` and `.user.ini`).
2. cPanel → **MySQL Databases**: create a database + user, grant ALL PRIVILEGES.
3. **phpMyAdmin** → select the database → **Import** → `database.sql`.
4. Edit `includes/config.php` → `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (optionally `SITE_URL`).
5. Make sure `uploads/enquiries/` and `uploads/portfolio/` are writable (755).
6. Log in at `/admin` with the default credentials (ask the project owner; they are deliberately not written in this public repository) and change the password immediately.
7. Once SSL is active, uncomment the *Force HTTPS* block (and HSTS) in `.htaccess`.
8. In **Admin → SEO & Settings** set your canonical URL, contact email, Instagram, city and coordinates, then submit `/sitemap.xml` in Google Search Console.

## Structure

```
├── database.sql              MySQL schema + seed data (settings, admin, 3 sample stories)
├── index.php                 One-page site: hero, Our Story, services, Behind the Stitches, process + enquiry form
├── project.php               SEO page per story → /stories/{slug}
├── sitemap.php / robots.php  Dynamic /sitemap.xml and /robots.txt (via .htaccess)
├── robots.txt                Static fallback
├── .htaccess / .user.ini     Rewrites, GZIP, caching, security headers / PHP upload limits
├── includes/
│   ├── config.php            PDO connection + helpers (URLs, CSRF, uploads, settings)
│   ├── layout.php            SEO <head>, header, footer, botanical inline SVG art
│   └── logo.php              Inline SVG fallback of the NENA monogram
├── api/submit_enquiry.php    AJAX form handler (validation, MIME-sniffed uploads, rate-limit, email notice)
├── admin/
│   ├── login.php / logout.php
│   ├── index.php             Dashboard · Enquiries · Portfolio CRUD · SEO & Settings · Password
│   └── file.php              Auth-only access to customer reference files
├── assets/css/style.css, admin.css
├── assets/js/main.js         Nav, reveal, filters, story lightbox, uploads preview, AJAX submit
├── assets/images/            Logos, favicons, OG image, sample story illustrations
└── uploads/                  enquiries/ (private) · portfolio/ (public media, no script execution)
```

## Features

- **SEO:** dynamic meta title/description/keywords, canonical, Open Graph, Twitter Card, NL geo tags, JSON-LD (`LocalBusiness` + `ProfessionalService`, 3× `Service`, `WebSite`, `ItemList`, per-story `CreativeWork` + `BreadcrumbList` + `VideoObject`), image sitemap, crawlable story pages.
- **Performance:** lazy images, explicit dimensions/aspect ratios (no CLS), versioned assets with 1-year caching, GZIP, `prefers-reduced-motion` support.
- **Security:** PDO prepared statements, CSRF tokens, `password_hash`, login throttling, session hardening, honeypot + time-trap + IP rate-limit on the form, MIME-sniffed uploads with random names, private enquiry uploads, no script execution in `/uploads`.
- **Admin:** status filters & search, inline status change, notes, reply-by-email, secure file preview/download, story CRUD with cover/gallery/video uploads (MP4/WEBM or YouTube/Vimeo URL), SEO alt text, draft/publish, SERP preview, password & account management.

### Story formatting
In *Full story*: a blank line starts a new paragraph; a line starting with `## ` becomes a sub-heading.

### Upload limits
`.user.ini` raises PHP limits to 128 MB per file for process videos. If your host ignores it, change *upload_max_filesize* / *post_max_size* in cPanel → **Select PHP Version → Options** (or **MultiPHP INI Editor**).

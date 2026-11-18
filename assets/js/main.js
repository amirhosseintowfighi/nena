/* =====================================================================
   Nena Studio — frontend interactions
   Nav · Scroll reveal · Portfolio filter · Story lightbox · Enquiry form
   ===================================================================== */
(() => {
    'use strict';

    document.documentElement.classList.remove('no-js');
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ------------------------------ Header & navigation ------------------------------ */
    const header = $('#siteHeader');
    const navToggle = $('#navToggle');
    const nav = $('#siteNav');

    const onScroll = () => header && header.classList.toggle('is-scrolled', window.scrollY > 24);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const closeNav = () => {
        document.body.classList.remove('nav-open');
        if (navToggle) {
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.setAttribute('aria-label', 'Open menu');
        }
    };

    if (navToggle && nav) {
        navToggle.addEventListener('click', () => {
            const open = !document.body.classList.contains('nav-open');
            document.body.classList.toggle('nav-open', open);
            navToggle.setAttribute('aria-expanded', String(open));
            navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        });
        $$('a', nav).forEach((a) => a.addEventListener('click', closeNav));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeNav(); });
    }

    // Active section highlighting
    const navLinks = $$('[data-nav-link]').filter((a) => a.hash && a.pathname === location.pathname);
    const sections = navLinks.map((a) => document.getElementById(a.hash.slice(1))).filter(Boolean);
    if ('IntersectionObserver' in window && sections.length) {
        const spy = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((a) => a.classList.toggle('is-active', a.hash === '#' + entry.target.id));
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        sections.forEach((s) => spy.observe(s));
    }

    /* ------------------------------ Reveal on scroll ------------------------------ */
    const revealEls = $$('.reveal');
    if ('IntersectionObserver' in window && !prefersReducedMotion) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach((el) => io.observe(el));
    } else {
        revealEls.forEach((el) => el.classList.add('is-visible'));
    }

    /* ------------------------------ Portfolio filter ------------------------------ */
    const filterBtns = $$('[data-filter]');
    const cards = $$('#projectsGrid .project-card');
    const emptyMsg = $('#projectsEmpty');

    filterBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;
            filterBtns.forEach((b) => {
                const active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-pressed', String(active));
            });
            let visible = 0;
            cards.forEach((card) => {
                const show = filter === 'all' || card.dataset.category === filter;
                card.classList.toggle('is-hidden', !show);
                if (show) {
                    visible++;
                    card.classList.add('is-visible');
                }
            });
            if (emptyMsg) emptyMsg.hidden = visible > 0;
        });
    });

    /* ------------------------------ Story lightbox ------------------------------ */
    const modal = $('#storyModal');
    const dataEl = $('#storyData');
    let stories = {};
    try { stories = dataEl ? JSON.parse(dataEl.textContent || '{}') : {}; } catch (err) { stories = {}; }

    if (modal && Object.keys(stories).length) {
        const track = $('#sliderTrack', modal);
        const dots = $('#sliderDots', modal);
        const prev = $('#sliderPrev', modal);
        const next = $('#sliderNext', modal);
        const slider = $('#storySlider', modal);
        const videoBox = $('#storyVideo', modal);
        const dialog = $('.story-modal__dialog', modal);
        let current = 0;
        let total = 0;
        let lastFocus = null;
        let activeSlug = null;

        const goTo = (index) => {
            if (!total) return;
            current = (index + total) % total;
            track.style.transform = `translateX(-${current * 100}%)`;
            $$('.slider__dot', dots).forEach((d, i) => {
                d.classList.toggle('is-active', i === current);
                d.setAttribute('aria-selected', String(i === current));
            });
            $$('.slider__slide', track).forEach((s, i) => s.setAttribute('aria-hidden', String(i !== current)));
        };

        const buildVideo = (video, title) => {
            videoBox.innerHTML = '';
            if (!video) { videoBox.hidden = true; return; }
            if (video.type === 'file') {
                const v = document.createElement('video');
                v.controls = true;
                v.preload = 'metadata';
                v.playsInline = true;
                v.setAttribute('aria-label', `${title} — process video`);
                const src = document.createElement('source');
                src.src = video.src;
                src.type = video.mime || 'video/mp4';
                v.appendChild(src);
                videoBox.appendChild(v);
            } else {
                const f = document.createElement('iframe');
                f.src = video.src;
                f.title = `${title} — process video`;
                f.loading = 'lazy';
                f.allow = 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen';
                f.allowFullscreen = true;
                videoBox.appendChild(f);
            }
            videoBox.hidden = false;
        };

        const openStory = (slug, pushHash = true) => {
            const story = stories[slug];
            if (!story) return;
            activeSlug = slug;
            lastFocus = document.activeElement;

            $('#storyModalTitle', modal).textContent = story.title;
            $('#storyModalCategory', modal).textContent = story.category;
            $('#storyModalDate', modal).textContent = story.date;
            $('#storyModalSummary', modal).textContent = story.summary;
            $('#storyModalStory', modal).innerHTML = story.story; // server-escaped HTML
            $('#storyModalPermalink', modal).href = story.url;

            track.innerHTML = '';
            dots.innerHTML = '';
            const gallery = story.gallery || [];
            total = gallery.length;
            gallery.forEach((src, i) => {
                const fig = document.createElement('figure');
                fig.className = 'slider__slide';
                const img = document.createElement('img');
                img.src = src;
                img.alt = i === 0 ? story.alt : `${story.alt} — detail ${i + 1}`;
                img.width = 1200;
                img.height = 900;
                img.decoding = 'async';
                if (i > 0) img.loading = 'lazy';
                fig.appendChild(img);
                track.appendChild(fig);

                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'slider__dot';
                dot.setAttribute('role', 'tab');
                dot.setAttribute('aria-label', `Show image ${i + 1} of ${gallery.length}`);
                dot.addEventListener('click', () => goTo(i));
                dots.appendChild(dot);
            });
            slider.hidden = total === 0;
            prev.hidden = next.hidden = total < 2;
            dots.hidden = total < 2;
            buildVideo(story.video, story.title);
            goTo(0);

            modal.hidden = false;
            document.body.classList.add('modal-open');
            $('.story-modal__content', modal).scrollTop = 0;
            dialog.scrollTop = 0;
            $('.story-modal__close', modal).focus();
            if (pushHash && history.replaceState) history.replaceState(null, '', `#story-${slug}`);
        };

        const closeStory = (restoreHash = true) => {
            if (modal.hidden) return;
            modal.hidden = true;
            document.body.classList.remove('modal-open');
            videoBox.innerHTML = ''; // stops any playing video
            if (restoreHash && history.replaceState && location.hash.startsWith('#story-')) {
                history.replaceState(null, '', location.pathname + location.search + '#behind-the-stitches');
            }
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            activeSlug = null;
        };

        $$('[data-story]').forEach((link) => {
            link.addEventListener('click', (e) => {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return; // allow open-in-new-tab
                e.preventDefault();
                openStory(link.dataset.story);
            });
        });

        $$('[data-close-modal]', modal).forEach((el) => el.addEventListener('click', (e) => {
            const isCta = el.hasAttribute('data-modal-cta');
            closeStory(!isCta);
            if (isCta) {
                e.preventDefault();
                const target = $('#enquiry-form');
                if (target) target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
                if (history.replaceState) history.replaceState(null, '', '#enquiry-form');
            }
        }));
        prev.addEventListener('click', () => goTo(current - 1));
        next.addEventListener('click', () => goTo(current + 1));

        document.addEventListener('keydown', (e) => {
            if (modal.hidden) return;
            if (e.key === 'Escape') closeStory();
            if (e.key === 'ArrowLeft' && e.target.tagName !== 'VIDEO') goTo(current - 1);
            if (e.key === 'ArrowRight' && e.target.tagName !== 'VIDEO') goTo(current + 1);
            if (e.key === 'Tab') {
                const focusables = $$('a[href], button:not([hidden]), video, iframe, [tabindex]:not([tabindex="-1"])', dialog)
                    .filter((el) => el.offsetParent !== null);
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });

        // Touch swipe
        let touchX = null;
        slider.addEventListener('touchstart', (e) => { touchX = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', (e) => {
            if (touchX === null) return;
            const dx = e.changedTouches[0].clientX - touchX;
            if (Math.abs(dx) > 40) goTo(current + (dx < 0 ? 1 : -1));
            touchX = null;
        });

        // Deep links: /#story-{slug}
        const fromHash = () => {
            const m = location.hash.match(/^#story-([a-z0-9-]+)$/);
            if (m && stories[m[1]]) openStory(m[1], false);
            else if (activeSlug) closeStory(false);
        };
        window.addEventListener('hashchange', fromHash);
        fromHash();
    }

    /* ------------------------------ Enquiry form ------------------------------ */
    const form = $('#enquiryForm');
    if (!form) return;

    const MAX_FILES = 8;
    const MAX_BYTES = 8 * 1024 * 1024;
    const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
    const dropzone = $('#dropzone');
    const fileInput = $('#reference_files');
    const previews = $('#previews');
    const statusBox = $('#formStatus');
    const submitBtn = $('#enquirySubmit');
    const handleWrap = $('#contactHandleWrap');
    const handleInput = $('#contact_handle');
    const handleLabel = $('#contactHandleLabel');
    const submitLabel = form.dataset.submitLabel || 'Send My Story';
    const successTitle = form.dataset.successTitle || 'Thank you — your story is on its way!';
    let files = [];

    const setError = (name, message) => {
        const errEl = $(`#${name}-error`);
        const field = errEl ? errEl.closest('.field') : null;
        if (errEl) errEl.textContent = message || '';
        if (field) field.classList.toggle('has-error', Boolean(message));
        const input = form.elements[name];
        if (input && input.setAttribute && !(input instanceof RadioNodeList)) {
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
            if (message) input.setAttribute('aria-describedby', `${name}-error`);
        }
    };
    const clearErrors = () => $$('.field__error', form).forEach((el) => {
        el.textContent = '';
        const f = el.closest('.field');
        if (f) f.classList.remove('has-error');
    });

    // Service CTA buttons -> scroll to form & pre-select project type
    $$('[data-select-service]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const value = btn.dataset.selectService;
            const radio = $$('input[name="project_type"]', form).find((r) => r.value === value);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
                setError('project_type', '');
            }
            $('#enquiry-form').scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
            setTimeout(() => { const n = $('#name'); if (n) n.focus({ preventScroll: true }); }, prefersReducedMotion ? 0 : 700);
        });
    });

    // Conditional contact handle
    const handleConfig = {
        'WhatsApp / Phone': { label: 'Your phone / WhatsApp number', placeholder: '+31 6 12345678', type: 'tel', autocomplete: 'tel' },
        Instagram: { label: 'Your Instagram handle', placeholder: '@yourname', type: 'text', autocomplete: 'off' },
    };
    $$('input[name="preferred_contact"]', form).forEach((radio) => {
        radio.addEventListener('change', () => {
            const cfg = handleConfig[radio.value];
            if (cfg) {
                handleWrap.hidden = false;
                handleLabel.textContent = cfg.label;
                handleInput.placeholder = cfg.placeholder;
                handleInput.type = cfg.type;
                handleInput.autocomplete = cfg.autocomplete;
                handleInput.required = true;
            } else {
                handleWrap.hidden = true;
                handleInput.required = false;
                handleInput.value = '';
                setError('contact_handle', '');
            }
        });
    });

    // Files: drag & drop + previews
    const formatSize = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

    const renderPreviews = () => {
        previews.querySelectorAll('img[data-url]').forEach((img) => URL.revokeObjectURL(img.dataset.url));
        previews.innerHTML = '';
        files.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'preview';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                const objectUrl = URL.createObjectURL(file);
                img.src = objectUrl;
                img.dataset.url = objectUrl;
                img.alt = `Preview of ${file.name}`;
                li.appendChild(img);
            } else {
                const doc = document.createElement('div');
                doc.className = 'preview__doc';
                const strong = document.createElement('strong');
                strong.textContent = 'PDF';
                doc.appendChild(strong);
                doc.appendChild(document.createTextNode(file.name.length > 22 ? file.name.slice(0, 20) + '…' : file.name));
                li.appendChild(doc);
            }
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'preview__remove';
            remove.setAttribute('aria-label', `Remove ${file.name}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                files.splice(index, 1);
                renderPreviews();
            });
            li.appendChild(remove);
            li.title = `${file.name} (${formatSize(file.size)})`;
            previews.appendChild(li);
        });
    };

    const addFiles = (list) => {
        const problems = [];
        Array.from(list).forEach((file) => {
            if (files.length >= MAX_FILES) { problems.push(`You can add up to ${MAX_FILES} files.`); return; }
            if (!ALLOWED.includes(file.type)) { problems.push(`"${file.name}" isn't a supported file type.`); return; }
            if (file.size > MAX_BYTES) { problems.push(`"${file.name}" is larger than 8 MB.`); return; }
            if (files.some((f) => f.name === file.name && f.size === file.size)) return;
            files.push(file);
        });
        setError('reference_files', [...new Set(problems)].join(' '));
        renderPreviews();
    };

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });
    fileInput.addEventListener('change', () => { addFiles(fileInput.files); fileInput.value = ''; });
    ['dragenter', 'dragover'].forEach((evt) => dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.add('is-dragover');
    }));
    ['dragleave', 'dragend', 'drop'].forEach((evt) => dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.remove('is-dragover');
    }));
    dropzone.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files) addFiles(e.dataTransfer.files); });

    // Validation
    const validate = () => {
        clearErrors();
        let firstInvalid = null;
        const fail = (name, msg, el) => { setError(name, msg); if (!firstInvalid) firstInvalid = el; };
        const name = form.elements.name;
        const email = form.elements.email;
        const details = form.elements.story_details;
        const date = form.elements.target_date;

        if (name.value.trim().length < 2) fail('name', 'Please tell me your name.', name);
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) fail('email', 'Please enter a valid email address.', email);
        if (!form.querySelector('input[name="project_type"]:checked')) fail('project_type', 'Please choose the type of project.', form.querySelector('input[name="project_type"]'));
        if (details.value.trim().length < 10) fail('story_details', 'Tell me a little more about your idea (at least a few words).', details);
        if (date.value && date.min && date.value < date.min) fail('target_date', 'Please choose a date in the future.', date);
        if (!handleWrap.hidden && handleInput.value.trim().length < 3) fail('contact_handle', 'Please add your contact details for this method.', handleInput);

        if (firstInvalid) firstInvalid.focus();
        return !firstInvalid;
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        statusBox.className = 'form-status';
        statusBox.innerHTML = '';
        if (!validate()) return;

        const data = new FormData(form);
        data.delete('reference_files[]');
        files.forEach((f) => data.append('reference_files[]', f, f.name));

        submitBtn.classList.add('is-loading');
        submitBtn.disabled = true;
        $('.btn__label', submitBtn).textContent = 'Sending…';

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });
            let json = {};
            try { json = await res.json(); } catch (err) { json = { success: false, message: 'Unexpected response from the server.' }; }

            if (res.ok && json.success) {
                form.reset();
                files = [];
                renderPreviews();
                handleWrap.hidden = true;
                statusBox.className = 'form-status is-success';
                statusBox.innerHTML = '<h4></h4><p></p>';
                statusBox.querySelector('h4').textContent = successTitle;
                statusBox.querySelector('p').textContent = json.message || "I'll be in touch soon.";
            } else {
                if (json.errors) Object.entries(json.errors).forEach(([field, msg]) => setError(field, msg));
                statusBox.className = 'form-status is-error';
                statusBox.textContent = json.message || 'Something went wrong. Please try again.';
            }
        } catch (err) {
            statusBox.className = 'form-status is-error';
            statusBox.textContent = 'We couldn’t reach the studio. Please check your connection and try again.';
        } finally {
            submitBtn.classList.remove('is-loading');
            submitBtn.disabled = false;
            $('.btn__label', submitBtn).textContent = submitLabel;
            statusBox.focus({ preventScroll: true });
            statusBox.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });
        }
    });
})();

/* Imara Health Initiative AFRIKA — site behaviour
   Based on the Dinenos template's main.js, trimmed to the features this site uses
   and guarded so every page can load it safely. */
(function ($) {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var imara = {
        init: function () {
            this.stickyHeader();
            this.sideMenu();
            this.metismenu();
            this.swipers();
            this.reveal();
            this.counterUp();
            this.isotope();
            this.galleryPopup();
            this.backToTop();
            this.preloader();
            this.quickStart();
            this.liveImpact();
        },

        // One passive, frame-throttled scroll listener for the whole site.
        // The header never changes size: it only gains a shadow once the page has scrolled.
        onScroll: function (fn) {
            this._scrollFns = this._scrollFns || [];
            this._scrollFns.push(fn);
            if (this._scrollBound) return;
            this._scrollBound = true;
            var fns = this._scrollFns, ticking = false;
            window.addEventListener('scroll', function () {
                if (ticking) return;
                ticking = true;
                window.requestAnimationFrame(function () {
                    var y = window.pageYOffset;
                    for (var i = 0; i < fns.length; i++) fns[i](y);
                    ticking = false;
                });
            }, { passive: true });
        },

        stickyHeader: function () {
            var h = document.querySelector('.header--sticky');
            if (!h) return;
            var on = false;
            var update = function (y) {
                var want = y > 10;
                if (want !== on) { on = want; h.classList.toggle('sticky', on); }
            };
            update(window.pageYOffset);
            this.onScroll(update);
        },

        sideMenu: function () {
            var open = function () { $('#side-bar').addClass('show'); $('#anywhere-home').addClass('bgshow'); };
            var close = function () { $('#side-bar').removeClass('show'); $('#anywhere-home').removeClass('bgshow'); };
            $(document).on('click', '#menu-btn', function (e) { e.preventDefault(); open(); });
            $(document).on('click', '.close-icon-menu, #anywhere-home', close);
            $(document).on('keyup', function (e) { if (e.key === 'Escape') close(); });
        },

        metismenu: function () {
            if ($.fn.metisMenu && $('#mobile-menu-active').length) {
                $('#mobile-menu-active').metisMenu();
            }
        },

        swipers: function () {
            if (typeof Swiper === 'undefined') return;
            if ($('.swiper-container').length) {
                new Swiper('.swiper-container', {
                    effect: 'fade',
                    loop: true,
                    pagination: { el: '.swiper-pagination', clickable: true },
                    autoplay: reduceMotion ? false : { delay: 7000 },
                    navigation: false
                });
            }
            if ($('.mySwiper-blog').length) {
                // Swiper 4 breakpoints are MAX widths: "up to 767px wide -> 1 slide"
                new Swiper('.mySwiper-blog', {
                    slidesPerView: 3,
                    spaceBetween: 30,
                    breakpoints: { 1199: { slidesPerView: 2, spaceBetween: 24 }, 767: { slidesPerView: 1.08, spaceBetween: 16 } }
                });
            }
            if ($('.mySwiper-brand').length) {
                new Swiper('.mySwiper-brand', {
                    slidesPerView: 5,
                    spaceBetween: 30,
                    loop: true,
                    speed: 700,
                    autoplay: reduceMotion ? false : { delay: 2800, disableOnInteraction: false },
                    breakpoints: { 1199: { slidesPerView: 4 }, 991: { slidesPerView: 3, spaceBetween: 20 }, 575: { slidesPerView: 2, spaceBetween: 12 } }
                });
            }
        },

        reveal: function () {
            if (typeof sal === 'function') {
                // start revealing a little BEFORE content enters the screen, so text is never blank while scrolling
                sal({ threshold: 0.01, rootMargin: '0px 0px 15% 0px', once: true, disabled: reduceMotion });
            }
            if (typeof AOS !== 'undefined' && document.querySelector('.aos')) {
                AOS.init({ duration: 600, easing: 'ease-out-quart', once: true, offset: 40, disable: reduceMotion });
            }
        },

        counterUp: function () {
            if ($.fn.counterUp && $('.counter').length) {
                $('.counter').counterUp({ delay: 10, time: 1000 });
            }
        },

        // Pillar filter (All / Access / Voice / Leadership). Cards stay in normal page flow,
        // so they can never overlap the next section on any screen size.
        isotope: function () {
            var grid = document.querySelector('.grid');
            if (!grid) return;
            var items = grid.querySelectorAll('.grid-item');
            $('.portfolio-menu').on('click', 'button', function (e) {
                e.preventDefault();
                $(this).addClass('active').attr('aria-pressed', 'true')
                    .siblings().removeClass('active').attr('aria-pressed', 'false');
                var f = this.getAttribute('data-filter');
                items.forEach(function (it) {
                    var show = f === '*' || it.matches(f);
                    if (show && it.hidden) {
                        it.hidden = false;
                        it.classList.add('is-entering');
                        requestAnimationFrame(function () { requestAnimationFrame(function () { it.classList.remove('is-entering'); }); });
                    } else if (!show) {
                        it.hidden = true;
                    }
                });
            });
        },

        // Any link with data-gallery="name" opens all links of that gallery in a popup
        galleryPopup: function () {
            if (!$.fn.magnificPopup) return;
            var groups = {};
            $('[data-gallery]').each(function () { groups[$(this).data('gallery')] = true; });
            Object.keys(groups).forEach(function (g) {
                $('[data-gallery="' + g + '"]').magnificPopup({
                    type: 'image',
                    gallery: { enabled: true },
                    image: { titleSrc: function (item) { return item.el.attr('title') || ''; } }
                });
            });
        },

        backToTop: function () {
            var path = document.querySelector('.progress-wrap path');
            if (!path) return;
            var len = path.getTotalLength();
            path.style.transition = 'none';
            path.style.strokeDasharray = len + ' ' + len;
            path.style.strokeDashoffset = len;
            path.getBoundingClientRect();
            path.style.transition = 'stroke-dashoffset 10ms linear';
            var wrap = document.querySelector('.progress-wrap');
            var max = 1, active = false;
            var measure = function () { max = Math.max(document.documentElement.scrollHeight - window.innerHeight, 1); };
            var update = function (y) {
                path.style.strokeDashoffset = len - (y * len / max);
                var want = y > 50;
                if (want !== active) { active = want; wrap.classList.toggle('active-progress', active); }
            };
            measure();
            update(window.pageYOffset);
            window.addEventListener('resize', measure, { passive: true });
            window.addEventListener('load', measure);
            if ('ResizeObserver' in window) new ResizeObserver(measure).observe(document.body); // lazy images change the page height
            this.onScroll(function (y) { update(y); });
            $(wrap).on('click keyup', function (e) {
                if (e.type === 'keyup' && e.key !== 'Enter') return;
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
            });
        },

        preloader: function () {
            var el = document.getElementById('dinenos-load');
            if (!el) return;
            var done = function () { el.classList.add('loaded'); };
            if (document.readyState === 'complete') done(); else window.addEventListener('load', done);
            setTimeout(done, 1200); // never block a slow connection behind the loader
        },

        // Home page quick-start bar → sends choices to the Get Care form
        quickStart: function () {
            $('#quick-start').on('submit', function (e) {
                e.preventDefault();
                var params = $(this).serialize();
                window.location.href = 'get-care.html?' + params + '#care-form';
            });
        },

        // Fill [data-live] elements from the impact API when the backend is running
        liveImpact: function () {
            if (!$('[data-live], [data-verified]').length) return;
            // without the backend (e.g. the static preview) hide the live panel instead of showing dashes
            var hideLive = function () { $('.live-panel').closest('section').hide(); };
            fetch('api/impact.php', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json().catch(function () { return null; }) : null; })
                .then(function (data) {
                    if (!data || !data.ok) { hideLive(); return; }
                    $('[data-verified]').each(function () {
                        var key = $(this).data('verified');
                        if (data.verified && data.verified[key]) $(this).text(Number(data.verified[key]).toLocaleString('en-US'));
                    });
                    if (data.verified_note) $('[data-verified-note]').text(data.verified_note);
                    $('[data-live]').each(function () {
                        var key = $(this).data('live');
                        if (data.live && data.live[key] !== undefined) {
                            $(this).text(Number(data.live[key]).toLocaleString());
                            $(this).closest('.live-stat').addClass('is-live');
                        }
                    });
                })
                .catch(hideLive); // static figures stay
        }
    };

    $(function () { imara.init(); });
})(jQuery);

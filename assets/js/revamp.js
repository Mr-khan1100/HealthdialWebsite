/* ================================================================
   HealthDial Revamp JS  v1.0
   3D Tilt · Hero Counter · Reveal helper
   ================================================================ */

(function () {
    'use strict';

    /* ——— 3D TILT via event delegation ——— */
    function applyTilt(card, e) {
        const rect = card.getBoundingClientRect();
        const cx   = rect.left + rect.width  / 2;
        const cy   = rect.top  + rect.height / 2;
        const dx   = (e.clientX - cx) / (rect.width  / 2);   // -1 … +1
        const dy   = (e.clientY - cy) / (rect.height / 2);   // -1 … +1
        const maxR = card.classList.contains('home-cat-card') ? 11 : 7;

        card.style.transition = 'transform 0.06s linear';
        card.style.transform  =
            `perspective(900px) rotateX(${-dy * maxR}deg) rotateY(${dx * maxR}deg) translateZ(10px)`;
    }

    function resetTilt(card) {
        card.style.transition = 'transform 0.5s cubic-bezier(0.4,0,0.2,1)';
        card.style.transform  = '';
    }

    /* Use event delegation so dynamically inserted cards are covered */
    document.addEventListener('mousemove', function (e) {
        const card = e.target.closest('.listing-card, .home-cat-card, .card');
        if (!card) return;
        /* Only tilt on desktop (pointer: fine) */
        if (window.matchMedia('(pointer: coarse)').matches) return;
        applyTilt(card, e);
    });

    document.addEventListener('mouseleave', function (e) {
        const card = e.target.closest('.listing-card, .home-cat-card, .card');
        if (card) resetTilt(card);
    }, true);   /* capture phase so we catch bubbled mouseleave */

    /* Also reset when the mouse exits a card boundary */
    document.addEventListener('mouseout', function (e) {
        const card = e.target.closest('.listing-card, .home-cat-card, .card');
        if (card && !card.contains(e.relatedTarget)) resetTilt(card);
    });

    /* ——— HERO STAT COUNTERS ——— */
    function runCounter(el) {
        if (el.dataset.counted) return;
        el.dataset.counted = '1';

        const target = parseInt(el.getAttribute('data-counter'), 10);
        const suffix = el.getAttribute('data-suffix') || '';
        if (!target) return;

        const dur   = 2000;
        const start = performance.now();

        (function tick(now) {
            const p     = Math.min((now - start) / dur, 1);
            /* ease-out quart: fast start, smooth deceleration */
            const eased = 1 - Math.pow(1 - p, 4);
            const value = Math.floor(eased * target);
            el.textContent = value.toLocaleString('en-IN') + (p >= 1 ? suffix : '');
            if (p < 1) requestAnimationFrame(tick);
        })(performance.now());
    }

    function initHeroCounters() {
        const els = document.querySelectorAll('[data-counter]');
        if (!els.length) return;

        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    runCounter(entry.target);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px' });

        els.forEach(function (el) { io.observe(el); });
    }

    /* ——— REVEAL FOR DYNAMICALLY LOADED CARDS ——— */
    /* Called by listings.js after injecting new cards into the DOM */
    window.initRevealForNew = function () {
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry, i) {
                if (entry.isIntersecting) {
                    const el    = entry.target;
                    const delay = i * 55;           /* staggered entrance */
                    setTimeout(function () {
                        el.classList.add('visible');
                    }, delay);
                    io.unobserve(el);
                }
            });
        }, { threshold: 0.06, rootMargin: '0px 0px -30px 0px' });

        document.querySelectorAll('.reveal:not(.visible)').forEach(function (el) {
            io.observe(el);
        });
    };

    /* ——— HERO WORD ROTATE: ensure last span = first for seamless loop ——— */
    function patchWordLoop() {
        const words = document.querySelector('.hero-words');
        if (!words) return;
        const spans = words.querySelectorAll('span');
        /* Last span must duplicate the first for a seamless CSS loop */
        if (spans.length && spans[spans.length - 1].textContent !== spans[0].textContent) {
            const clone = spans[0].cloneNode(true);
            words.appendChild(clone);
        }
    }

    /* ——— DOM READY ——— */
    document.addEventListener('DOMContentLoaded', function () {
        initHeroCounters();
        patchWordLoop();
    });

})();

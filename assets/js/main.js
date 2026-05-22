// ===== HealthDial Main JS =====
document.addEventListener('DOMContentLoaded', () => {
    initPageTransitions();
    initNavbar();
    initMobileNav();
    initScrollReveal();
    initBackToTop();
    initFAQ();
    initTestimonials();
    initCounters();
    initNewsModal();
});

// Page Transitions — simplified (no delay on link click)
function initPageTransitions() {
    const transitionEl = document.getElementById('pageTransition');
    if (!transitionEl) return;
    // Just show enter animation on load
    transitionEl.classList.add('page-enter');
    setTimeout(() => {
        transitionEl.classList.remove('page-enter');
    }, 600);
}

// Back to Top
function initBackToTop() {
    const btn = document.getElementById('backToTop');
    if (!btn) return;
    window.addEventListener('scroll', () => {
        btn.classList.toggle('visible', window.scrollY > 400);
    });
}

// Navbar scroll effect
function initNavbar() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 50);
    });
}

// Mobile nav
function initMobileNav() {
    const hamburger = document.querySelector('.hamburger');
    const mobileNav = document.querySelector('.mobile-nav');
    if (!hamburger || !mobileNav) return;
    const links = mobileNav.querySelectorAll('a');
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        mobileNav.classList.toggle('active');
        document.body.style.overflow = mobileNav.classList.contains('active') ? 'hidden' : '';
    });
    links.forEach(l => l.addEventListener('click', () => {
        hamburger.classList.remove('active');
        mobileNav.classList.remove('active');
        document.body.style.overflow = '';
    }));
}

// Scroll reveal
function initScrollReveal() {
    const els = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');
    if (!els.length) return;
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    els.forEach(el => obs.observe(el));
}

// FAQ accordion
function initFAQ() {
    const items = document.querySelectorAll('.faq-item');
    if (!items.length) return;
    items.forEach(item => {
        const q = item.querySelector('.faq-question');
        const a = item.querySelector('.faq-answer');
        const inner = item.querySelector('.faq-answer-inner');
        q.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            items.forEach(o => { o.classList.remove('active'); o.querySelector('.faq-answer').style.maxHeight = '0'; });
            if (!isActive) { item.classList.add('active'); a.style.maxHeight = inner.scrollHeight + 'px'; }
        });
    });
}

// Testimonials carousel
function initTestimonials() {
    const track = document.querySelector('.testimonials-track');
    const dots = document.querySelectorAll('.dot');
    if (!track || !dots.length) return;
    let current = 0;
    const total = dots.length;
    function goTo(i) {
        current = i;
        track.style.transform = `translateX(-${current * 100}%)`;
        dots.forEach((d, idx) => d.classList.toggle('active', idx === current));
    }
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));
    setInterval(() => goTo((current + 1) % total), 5000);
}

// Animated counters
function initCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    if (!counters.length) return;
    let triggered = false;
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting && !triggered) {
                triggered = true;
                counters.forEach(el => animateCounter(el));
            }
        });
    }, { threshold: 0.3 });
    counters.forEach(el => obs.observe(el));
}

function animateCounter(el) {
    const target = parseInt(el.getAttribute('data-counter'));
    const suffix = el.getAttribute('data-suffix') || '';
    const dur = 2000, start = performance.now();
    function tick(now) {
        const p = Math.min((now - start) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = formatNum(Math.floor(eased * target)) + suffix;
        if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

function formatNum(n) {
    if (n >= 100000) return Math.floor(n / 100000) + ',00,000';
    return n.toLocaleString('en-IN');
}

// News modal init (for overlay click/escape)
function initNewsModal() {
    const overlay = document.getElementById('news-modal-overlay');
    if (!overlay) return;
    overlay.addEventListener('click', e => {
        if (e.target === overlay || e.target.closest('.news-modal-close')) {
            if (typeof closeNewsModal === 'function') closeNewsModal();
        }
    });
    document.addEventListener('keydown', e => { 
        if (e.key === 'Escape' && typeof closeNewsModal === 'function') closeNewsModal(); 
    });
}

<footer class="footer">
    <div class="container">
        <!-- Google Translate (hidden, controlled by our custom UI) -->
        <div id="google_translate_element" style="display:none;"></div>
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="assets/images/logo.png" alt="HealthDial" />
                </div>
                <p class="footer-desc">Your trusted health partner for finding verified medical services instantly
                    across India.</p>
                <div class="footer-app-badges">
                    <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank">
                        <img src="assets/images/google-play.svg" alt="Google Play" class="store-badge" />
                    </a>
                    <a href="https://apps.apple.com/app/healthdial" target="_blank">
                        <img src="assets/images/app-store.svg" alt="App Store" class="store-badge" />
                    </a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Quick Links</h4>
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="listings.php">Listings</a>
                    <a href="categories.php">Categories</a>
                    <a href="news.php">News</a>
                    <a href="download.php">Download App</a>
                    <a href="contact.php">Contact</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">For Providers</h4>
                <div class="footer-links">
                    <a href="contact.php">List Your Business</a>
                    <a href="contact.php">HealthDial for Business</a>
                    <a href="contact.php">Advertising</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Legal & Support</h4>
                <div class="footer-links">
                    <a href="about.php">About Us</a>
                    <a href="contact.php">Contact Us</a>
                    <a href="terms-and-conditions.php">Terms & Conditions</a>
                    <a href="privacy-policy.php">Privacy Policy</a>
                    <a href="refund-policy.php">Refund & Cancellation</a>
                    <a href="shipping-policy.php">Shipping & Delivery</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 HEALTHDIAL PRIVATE LIMITED. All rights reserved.</p>
            <p class="developer-credit">Developed by <a href="https://ramaforgestudio.cloud" target="_blank"
                    rel="noopener noreferrer">RamaForge Studio</a></p>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})"
    aria-label="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- ===== FLOATING LANGUAGE SELECTOR ===== -->
<div class="lang-fab" id="langFab" onclick="toggleLangPanel()">
    <i class="fas fa-globe"></i>
</div>
<div class="lang-panel" id="langPanel">
    <div class="lang-panel-header">
        <span><i class="fas fa-language"></i> Select Language</span>
        <button onclick="toggleLangPanel()" class="lang-panel-close">&times;</button>
    </div>
    <div class="lang-panel-grid">
        <button class="lang-option active" data-lang="en" onclick="changeLanguage('en',this)">
            <span class="lang-flag">🇬🇧</span><span class="lang-name">English</span>
        </button>
        <button class="lang-option" data-lang="hi" onclick="changeLanguage('hi',this)">
            <span class="lang-flag">🇮🇳</span><span class="lang-name">हिंदी</span>
        </button>
        <button class="lang-option" data-lang="bn" onclick="changeLanguage('bn',this)">
            <span class="lang-flag">🇮🇳</span><span class="lang-name">বাংলা</span>
        </button>
        <button class="lang-option" data-lang="ta" onclick="changeLanguage('ta',this)">
            <span class="lang-flag">🇮🇳</span><span class="lang-name">தமிழ்</span>
        </button>
        <button class="lang-option" data-lang="te" onclick="changeLanguage('te',this)">
            <span class="lang-flag">🇮🇳</span><span class="lang-name">తెలుగు</span>
        </button>
        <button class="lang-option" data-lang="mr" onclick="changeLanguage('mr',this)">
            <span class="lang-flag">🇮🇳</span><span class="lang-name">मराठी</span>
        </button>
        <button class="lang-option" data-lang="gu" onclick="changeLanguage('gu',this)">
            <span class="lang-flag">🇮🇳</span><span class="lang-name">ગુજરાતી</span>
        </button>
        <button class="lang-option" data-lang="kn" onclick="changeLanguage('kn',this)">
            <span class="lang-flag">🇮🇳</span><span class="lang-name">ಕನ್ನಡ</span>
        </button>
    </div>
</div>

<!-- Google Translate Engine -->
<script>
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        autoDisplay: false
    }, 'google_translate_element');
}

function toggleLangPanel() {
    const panel = document.getElementById('langPanel');
    const fab = document.getElementById('langFab');
    panel.classList.toggle('open');
    fab.classList.toggle('active');
}

function changeLanguage(lang, btn) {
    // Update active state
    document.querySelectorAll('.lang-option').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    localStorage.setItem('hd_lang', lang);

    // Trigger Google Translate
    const select = document.querySelector('#google_translate_element select');
    if (select) {
        select.value = lang;
        select.dispatchEvent(new Event('change'));
    } else {
        // If Google Translate hasn't loaded yet, set a cookie
        document.cookie = 'googtrans=/en/' + lang + ';path=/';
        document.cookie = 'googtrans=/en/' + lang + ';path=/;domain=' + location.hostname;
        location.reload();
    }

    // Close panel
    setTimeout(() => toggleLangPanel(), 300);
}

// Restore saved language on load
(function() {
    const saved = localStorage.getItem('hd_lang');
    if (saved && saved !== 'en') {
        const btn = document.querySelector(`.lang-option[data-lang="${saved}"]`);
        if (btn) {
            document.querySelectorAll('.lang-option').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
    }
})();

// Close panel on outside click
document.addEventListener('click', function(e) {
    const panel = document.getElementById('langPanel');
    const fab = document.getElementById('langFab');
    if (panel.classList.contains('open') && !panel.contains(e.target) && !fab.contains(e.target)) {
        toggleLangPanel();
    }
});
</script>
<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<script src="assets/js/revamp.js?v=2.4.0"></script>
<script src="assets/js/main.js?v=2.4.0"></script>
</body>

</html>
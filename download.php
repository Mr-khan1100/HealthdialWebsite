<?php
$currentPage = 'download';
$pageTitle = 'Download  | Labs & Pharmacies Across India | Medicine Store Near Me | Health Dial';
$pageDesc = 'Find verified hospitals, clinics, labs, pharmacies & emergency services near you — with live GPS navigation, 1-tap calling, and medication reminders. All in one beautiful app.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<!-- ===== HERO SECTION ===== -->
<section class="dl-hero">
    <div class="dl-hero-bg">
        <div class="dl-blob dl-blob-1"></div>
        <div class="dl-blob dl-blob-2"></div>
        <div class="dl-blob dl-blob-3"></div>
    </div>
    <div class="container dl-hero-grid">
        <div class="dl-hero-text">
            <div class="hero-badge" style="margin: 0 0 24px;">
                <span class="pulse-dot"></span> Available on Android & iOS
            </div>
            <h1 class="dl-hero-title">
                Your Health,<br>
                <span class="gradient-text">One Tap Away</span>
            </h1>
            <p class="dl-hero-desc">
                Find verified hospitals, clinics, labs, pharmacies & emergency services near you — with live GPS navigation, 1-tap calling, and medication reminders. All in one beautiful app.
            </p>
            <div class="dl-store-buttons">
                <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank" class="dl-store-btn" aria-label="Google Play">
                    <img src="assets/images/google-play.svg" alt="Get it on Google Play" height="52" />
                </a>
                <a href="https://apps.apple.com/app/healthdial" target="_blank" class="dl-store-btn" aria-label="App Store">
                    <img src="assets/images/app-store.svg" alt="Download on App Store" height="52" />
                </a>
            </div>
            <div class="dl-trust-row">
                <div class="dl-trust-avatars">
                    <div class="dl-avatar" style="background:#3b82f6;">R</div>
                    <div class="dl-avatar" style="background:#10b981;">A</div>
                    <div class="dl-avatar" style="background:#f59e0b;">P</div>
                    <div class="dl-avatar" style="background:#ef4444;">S</div>
                </div>
                <span>Join <strong>50,000+</strong> users across India</span>
            </div>
        </div>
        <div class="dl-hero-visual">
            <div class="dl-phone-mockup">
                <div class="dl-phone-screen">
                    <div class="dl-screen-header">
                        <div class="dl-screen-logo">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                        </div>
                        <span>HealthDial</span>
                    </div>
                    <div class="dl-screen-search">
                        <i class="fas fa-search"></i> Search hospitals near you...
                    </div>
                    <div class="dl-screen-card">
                        <div class="dl-screen-card-img"></div>
                        <div class="dl-screen-card-text">
                            <div class="dl-line" style="width:80%"></div>
                            <div class="dl-line short" style="width:50%"></div>
                            <div class="dl-stars">⭐⭐⭐⭐⭐</div>
                        </div>
                    </div>
                    <div class="dl-screen-card">
                        <div class="dl-screen-card-img"></div>
                        <div class="dl-screen-card-text">
                            <div class="dl-line" style="width:70%"></div>
                            <div class="dl-line short" style="width:60%"></div>
                            <div class="dl-stars">⭐⭐⭐⭐</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dl-float-badge dl-float-1">
                <i class="fas fa-map-marker-alt"></i> GPS Navigation
            </div>
            <div class="dl-float-badge dl-float-2">
                <i class="fas fa-pills"></i> Med Reminders
            </div>
            <div class="dl-float-badge dl-float-3">
                <i class="fas fa-phone-alt"></i> 1-Tap Calls
            </div>
        </div>
    </div>
</section>

<!-- ===== STATS SECTION ===== -->
<section class="dl-stats">
    <div class="container">
        <div class="dl-stats-grid">
            <div class="dl-stat-item">
                <div class="dl-stat-number" data-count="10000">10,000+</div>
                <div class="dl-stat-label">Verified Listings</div>
            </div>
            <div class="dl-stat-item">
                <div class="dl-stat-number" data-count="500">500+</div>
                <div class="dl-stat-label">Cities Covered</div>
            </div>
            <div class="dl-stat-item">
                <div class="dl-stat-number" data-count="50000">50,000+</div>
                <div class="dl-stat-label">Happy Users</div>
            </div>
            <div class="dl-stat-item">
                <div class="dl-stat-number">4.8 ★</div>
                <div class="dl-stat-label">App Rating</div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FEATURES SECTION ===== -->
<section class="dl-features">
    <div class="container">
        <div class="section-header" style="text-align:center;margin-bottom:48px;">
            <h2>Everything You Need for <span class="gradient-text">Your Health</span></h2>
            <p style="color:var(--text-muted);max-width:560px;margin:12px auto 0;">Powerful features designed to make healthcare accessible, fast, and reliable for every Indian.</p>
        </div>
        <div class="dl-features-grid">
            <div class="dl-feature-card" style="--delay:0s">
                <div class="dl-feature-icon" style="background:rgba(43,125,233,0.1);color:var(--blue);">
                    <i class="fas fa-location-arrow"></i>
                </div>
                <h3>Live GPS Navigation</h3>
                <p>Get real-time directions to the nearest hospital, clinic, or pharmacy. Never waste time searching for an address.</p>
            </div>
            <div class="dl-feature-card" style="--delay:0.1s">
                <div class="dl-feature-icon" style="background:rgba(67,182,73,0.1);color:var(--green);">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <h3>1-Tap Direct Calling</h3>
                <p>Call any hospital or doctor instantly with a single tap. No middlemen, no waiting — direct connection.</p>
            </div>
            <div class="dl-feature-card" style="--delay:0.2s">
                <div class="dl-feature-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;">
                    <i class="fas fa-pills"></i>
                </div>
                <h3>Medication Reminders</h3>
                <p>Never miss a dose. Set smart reminders for your medications with custom schedules and sound alerts.</p>
            </div>
            <div class="dl-feature-card" style="--delay:0.3s">
                <div class="dl-feature-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;">
                    <i class="fas fa-ambulance"></i>
                </div>
                <h3>Emergency Services</h3>
                <p>Find 24×7 ambulance services, blood banks, and emergency rooms near you instantly when every second counts.</p>
            </div>
            <div class="dl-feature-card" style="--delay:0.4s">
                <div class="dl-feature-icon" style="background:rgba(124,58,237,0.1);color:#7c3aed;">
                    <i class="fas fa-star"></i>
                </div>
                <h3>Verified Reviews</h3>
                <p>Read genuine reviews from real patients. Make informed decisions about your healthcare providers.</p>
            </div>
            <div class="dl-feature-card" style="--delay:0.5s">
                <div class="dl-feature-icon" style="background:rgba(14,116,144,0.1);color:#0e7490;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>100% Free & Safe</h3>
                <p>HealthDial is completely free with no hidden charges. Your data is encrypted and never shared with third parties.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section class="dl-how" style="background:var(--bg-alt);">
    <div class="container">
        <div class="section-header" style="text-align:center;margin-bottom:48px;">
            <h2>Get Started in <span class="gradient-text">3 Simple Steps</span></h2>
        </div>
        <div class="dl-steps-grid">
            <div class="dl-step">
                <div class="dl-step-num">1</div>
                <div class="dl-step-icon"><i class="fas fa-download"></i></div>
                <h3>Download the App</h3>
                <p>Get it free from Google Play or the App Store. Takes less than 30 seconds.</p>
            </div>
            <div class="dl-step-arrow"><i class="fas fa-chevron-right"></i></div>
            <div class="dl-step">
                <div class="dl-step-num">2</div>
                <div class="dl-step-icon"><i class="fas fa-search-location"></i></div>
                <h3>Search Nearby</h3>
                <p>Allow location access and find hospitals, clinics, and doctors sorted by distance.</p>
            </div>
            <div class="dl-step-arrow"><i class="fas fa-chevron-right"></i></div>
            <div class="dl-step">
                <div class="dl-step-num">3</div>
                <div class="dl-step-icon"><i class="fas fa-hands-helping"></i></div>
                <h3>Get Help Instantly</h3>
                <p>Call directly, navigate with GPS, or set a medication reminder — all in one app.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA SECTION ===== -->
<section class="dl-cta">
    <div class="container" style="text-align:center;">
        <h2 style="color:white;font-size:2rem;margin-bottom:12px;">Ready to Take Control of Your Health?</h2>
        <p style="color:rgba(255,255,255,0.8);max-width:480px;margin:0 auto 32px;font-size:1.05rem;">Download HealthDial today and join thousands of Indians who trust us for their healthcare needs.</p>
        <div class="dl-store-buttons" style="justify-content:center;">
            <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank" class="dl-store-btn">
                <img src="assets/images/google-play.svg" alt="Google Play" height="52" />
            </a>
            <a href="https://apps.apple.com/app/healthdial" target="_blank" class="dl-store-btn">
                <img src="assets/images/app-store.svg" alt="App Store" height="52" />
            </a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>

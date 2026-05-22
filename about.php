<?php
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us | Trusted  Medical Stores | Top Clinics Near You In Faridabad | Health Dial</title>
<meta name="keywords" content="Trusted  Medical Stores In Faridabad, Top Clinics Near You In Faridabad"/>
<meta name="description" content="HEALTHDIAL is India's trusted healthcare discovery platform, connecting patients with verified hospitals, clinics, laboratories, and medical professionals across 500+ cities in India.">

<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Serif:ital,wght@0,400;0,600;1,400&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ink: #0f1923;
    --ink-mid: #3a4a57;
    --ink-light: #7a8c99;
    --rule: #d6dde3;
    --accent: #0066cc;
    --accent-soft: #e8f2ff;
    --bg: #f7f9fb;
    --paper: #ffffff;
    --green: #0d9488;
    --green-soft: #f0fdf4;
    --green-border: #bbf7d0;
  }

  body {
    background: var(--bg);
    color: var(--ink);
    font-family: 'IBM Plex Sans', sans-serif;
    font-size: 13px;
    font-weight: 300;
    line-height: 1.65;
    min-height: 100vh;
    padding: 48px 20px 80px;
  }

  .doc {
    max-width: 780px;
    margin: 0 auto;
    background: var(--paper);
    border: 1px solid var(--rule);
    border-top: 4px solid var(--accent);
    box-shadow: 0 2px 24px rgba(0,0,0,.06);
  }

  .doc-header {
    padding: 32px 40px 24px;
    border-bottom: 1px solid var(--rule);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
  }

  .brand { display: flex; align-items: center; gap: 10px; }

  .brand-icon {
    width: 36px; height: 36px;
    background: var(--accent);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
  }

  .brand-icon svg { fill: #fff; }

  .brand-name {
    font-family: 'IBM Plex Serif', serif;
    font-size: 20px;
    font-weight: 600;
    color: var(--ink);
    letter-spacing: -.3px;
  }

  .doc-meta {
    text-align: right;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10px;
    color: var(--ink-light);
    line-height: 1.8;
  }

  .doc-meta strong {
    display: block;
    color: var(--ink-mid);
    font-size: 11px;
  }

  .doc-title-block {
    padding: 28px 40px 20px;
    border-bottom: 1px solid var(--rule);
    background: var(--accent-soft);
  }

  .doc-title-block h1 {
    font-family: 'IBM Plex Serif', serif;
    font-size: 22px;
    font-weight: 600;
    color: var(--ink);
    letter-spacing: -.4px;
    margin-bottom: 6px;
  }

  .doc-title-block p {
    font-size: 12px;
    color: var(--ink-mid);
    max-width: 600px;
  }

  .doc-body { padding: 0 40px 40px; }

  .section {
    border-bottom: 1px solid var(--rule);
    padding: 22px 0;
  }

  .section:last-child { border-bottom: none; }

  .section-head {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 10px;
  }

  .section-num {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    font-weight: 500;
    letter-spacing: .1em;
    color: var(--accent);
    background: var(--accent-soft);
    border: 1px solid rgba(0,102,204,.15);
    padding: 2px 6px;
    border-radius: 3px;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .section h2 {
    font-family: 'IBM Plex Sans', sans-serif;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--ink);
  }

  .clauses { display: grid; gap: 6px; }

  .clause {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 7px 10px;
    border-radius: 4px;
    border: 1px solid transparent;
    transition: background .15s, border-color .15s;
  }

  .clause:hover { background: var(--bg); border-color: var(--rule); }

  .clause-id {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    color: var(--ink-light);
    padding-top: 2px;
    flex-shrink: 0;
    width: 28px;
  }

  .clause-text {
    font-size: 12px;
    color: var(--ink-mid);
    line-height: 1.6;
  }

  .clause-text strong { font-weight: 500; color: var(--ink); }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    margin-top: 12px;
  }

  .stat-item {
    background: var(--green-soft);
    border: 1px solid var(--green-border);
    border-radius: 6px;
    padding: 14px;
    text-align: center;
  }

  .stat-number {
    font-family: 'IBM Plex Serif', serif;
    font-size: 22px;
    font-weight: 600;
    color: var(--green);
  }

  .stat-label {
    font-size: 11px;
    color: var(--ink-mid);
    margin-top: 2px;
  }

  .contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px;
    margin-top: 10px;
  }

  .contact-item {
    border: 1px solid var(--rule);
    padding: 10px 14px;
    border-radius: 4px;
  }

  .contact-label {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--ink-light);
    margin-bottom: 3px;
  }

  .contact-value {
    font-size: 12px;
    color: var(--accent);
    word-break: break-all;
  }

  .doc-footer {
    margin: 0 40px;
    padding: 16px 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid var(--rule);
  }

  .doc-footer p {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10px;
    color: var(--ink-light);
  }

  .badge {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--accent);
    border: 1px solid rgba(0,102,204,.3);
    padding: 3px 8px;
    border-radius: 3px;
  }

  @media (max-width: 600px) {
    body { padding: 0 0 60px; }
    .doc { border-left: none; border-right: none; }
    .doc-header, .doc-title-block, .doc-body, .doc-footer { padding-left: 20px; padding-right: 20px; }
    .doc-footer { margin: 0 20px; }
    .doc-header { flex-direction: column; gap: 12px; }
    .doc-meta { text-align: left; }
    .contact-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>
</head>
<body>

<div class="doc">

  <!-- Header -->
  <div class="doc-header">
    <div class="brand">
      <div class="brand-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
          <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 14.93V15a1 1 0 0 0-2 0v1.93A8 8 0 0 1 4.07 13H5a1 1 0 0 0 0-2h-.93A8 8 0 0 1 11 4.07V5a1 1 0 0 0 2 0v-.93A8 8 0 0 1 19.93 11H19a1 1 0 0 0 0 2h.93A8 8 0 0 1 13 16.93zM12 10a2 2 0 1 0 2 2 2 2 0 0 0-2-2z"/>
        </svg>
      </div>
      <span class="brand-name">HealthDial</span>
    </div>
    <div class="doc-meta">
      <strong>ABOUT US</strong>
      Company Overview<br>
      Registered in India
    </div>
  </div>

  <!-- Title Block -->
  <div class="doc-title-block">
    <h1>About HealthDial</h1>
    <p><strong>HEALTHDIAL PRIVATE LIMITED</strong> (trading as "HealthDial") is India's trusted healthcare discovery platform, connecting patients with verified hospitals, clinics, laboratories, and medical professionals across 500+ cities.</p>
  </div>

  <!-- Body -->
  <div class="doc-body">

    <!-- §1 Who We Are -->
    <div class="section" id="s1">
      <div class="section-head">
        <span class="section-num">§ 01</span>
        <h2>Who We Are</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">1.1</span>
          <span class="clause-text"><strong>HEALTHDIAL PRIVATE LIMITED</strong> (trading as "HealthDial") is a healthcare listing and discovery platform operated from India. We help patients find the right medical care by providing a comprehensive, searchable directory of hospitals, clinics, pharmacies, labs, and doctors.</span>
        </div>
        <div class="clause">
          <span class="clause-id">1.2</span>
          <span class="clause-text">Our platform is available as a <strong>mobile application</strong> (Android & iOS) and a <strong>responsive website</strong> at healthdial.com, ensuring accessibility across all devices.</span>
        </div>
        <div class="clause">
          <span class="clause-id">1.3</span>
          <span class="clause-text">HealthDial operates as an <strong>information directory only</strong>. We do not provide medical advice, diagnoses, treatments, or emergency medical services.</span>
        </div>
      </div>
    </div>

    <!-- §2 Our Mission -->
    <div class="section" id="s2">
      <div class="section-head">
        <span class="section-num">§ 02</span>
        <h2>Our Mission</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">2.1</span>
          <span class="clause-text">To <strong>improve healthcare accessibility</strong> across India by providing an easy-to-use platform that connects patients with verified, nearby healthcare providers.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.2</span>
          <span class="clause-text">To <strong>empower patients</strong> with accurate, up-to-date information so they can make informed decisions about their healthcare needs.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.3</span>
          <span class="clause-text">To <strong>support healthcare providers</strong> by giving them a digital presence that reaches thousands of patients searching for medical services every day.</span>
        </div>
      </div>
    </div>

    <!-- §3 Our Vision -->
    <div class="section" id="s3">
      <div class="section-head">
        <span class="section-num">§ 03</span>
        <h2>Our Vision</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">3.1</span>
          <span class="clause-text">To become India's <strong>most comprehensive and reliable digital healthcare directory</strong>, bridging the gap between patients and quality healthcare providers.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.2</span>
          <span class="clause-text">To build a <strong>transparent healthcare ecosystem</strong> where verified reviews and ratings help patients choose the best care available in their area.</span>
        </div>
      </div>
    </div>

    <!-- §4 What We Offer -->
    <div class="section" id="s4">
      <div class="section-head">
        <span class="section-num">§ 04</span>
        <h2>What We Offer</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">4.1</span>
          <span class="clause-text"><strong>Location-based search</strong> — Find hospitals, clinics, labs, and pharmacies near your current location with real-time distance calculations.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.2</span>
          <span class="clause-text"><strong>Verified listings</strong> — All healthcare providers are reviewed and verified before being listed on our platform.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.3</span>
          <span class="clause-text"><strong>Patient reviews & ratings</strong> — Real feedback from patients helps you choose the best healthcare providers.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.4</span>
          <span class="clause-text"><strong>Direct contact</strong> — Call, WhatsApp, or get directions to any listed provider directly from the app.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.5</span>
          <span class="clause-text"><strong>Listing promotion</strong> — Healthcare providers can promote their listings for enhanced visibility through our secure online payment system.</span>
        </div>
      </div>
    </div>

    <!-- §5 Platform Stats -->
    <div class="section" id="s5">
      <div class="section-head">
        <span class="section-num">§ 05</span>
        <h2>Our Reach</h2>
      </div>
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-number">50,000+</div>
          <div class="stat-label">Healthcare Listings</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">500+</div>
          <div class="stat-label">Cities Covered</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">1,000+</div>
          <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">10+</div>
          <div class="stat-label">Categories</div>
        </div>
      </div>
    </div>

    <!-- §6 Contact -->
    <div class="section" id="s6">
      <div class="section-head">
        <span class="section-num">§ 06</span>
        <h2>Contact Us</h2>
      </div>
      <p style="font-size:12px; color:var(--ink-mid); margin-bottom:10px;">
        For business inquiries, partnership opportunities, or customer support, reach us through any channel below.
      </p>
      <div class="contact-grid">
        <div class="contact-item">
          <div class="contact-label">Email</div>
          <div class="contact-value">healthdialofficial@gmail.com</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Phone</div>
          <div class="contact-value">+91 99116 60669</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Website</div>
          <div class="contact-value">https://healthdial.com</div>
        </div>
      </div>
    </div>

  </div><!-- /doc-body -->

  <!-- Footer -->
  <div class="doc-footer">
    <p>&copy; <?php echo date("Y"); ?> HEALTHDIAL PRIVATE LIMITED &middot; All rights reserved</p>
    <span class="badge">About · <?php echo date("M Y"); ?></span>
  </div>

</div><!-- /doc -->
</body>
</html>

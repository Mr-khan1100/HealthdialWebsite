<?php
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy - HealthDial</title>
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
    --red: #c0392b;
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

  /* ─── Document Shell ─── */
  .doc {
    max-width: 780px;
    margin: 0 auto;
    background: var(--paper);
    border: 1px solid var(--rule);
    border-top: 4px solid var(--accent);
    box-shadow: 0 2px 24px rgba(0,0,0,.06);
  }

  /* ─── Header Band ─── */
  .doc-header {
    padding: 32px 40px 24px;
    border-bottom: 1px solid var(--rule);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 10px;
  }

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

  /* ─── Title Block ─── */
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
    max-width: 580px;
  }

  /* ─── Table of Contents ─── */
  .toc {
    margin: 0 40px;
    border-bottom: 1px solid var(--rule);
    padding: 18px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 4px 0;
  }

  .toc-label {
    width: 100%;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    font-weight: 500;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--ink-light);
    margin-bottom: 8px;
  }

  .toc a {
    font-size: 11px;
    color: var(--accent);
    text-decoration: none;
    padding: 2px 10px 2px 0;
    white-space: nowrap;
  }

  .toc a:hover { text-decoration: underline; }
  .toc a::after { content: " ·"; color: var(--rule); margin-left: 10px; }
  .toc a:last-child::after { content: ""; }

  /* ─── Body Content ─── */
  .doc-body {
    padding: 0 40px 40px;
  }

  /* ─── Section ─── */
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

  /* ─── Clause Grid ─── */
  .clauses {
    display: grid;
    gap: 6px;
  }

  .clause {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 7px 10px;
    border-radius: 4px;
    border: 1px solid transparent;
    transition: background .15s, border-color .15s;
  }

  .clause:hover {
    background: var(--bg);
    border-color: var(--rule);
  }

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

  .clause-text strong {
    font-weight: 500;
    color: var(--ink);
  }

  /* ─── Sub-sections ─── */
  .sub-section {
    margin-top: 10px;
    padding-left: 16px;
    border-left: 2px solid var(--rule);
  }

  .sub-label {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    font-weight: 500;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--ink-light);
    margin-bottom: 6px;
  }

  /* ─── Pills / Tags ─── */
  .tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
  }

  .tag {
    font-size: 11px;
    font-family: 'IBM Plex Mono', monospace;
    color: var(--ink-mid);
    background: var(--bg);
    border: 1px solid var(--rule);
    padding: 3px 8px;
    border-radius: 3px;
  }

  /* ─── Alert / Notice ─── */
  .notice {
    display: flex;
    gap: 10px;
    background: #fff8e1;
    border: 1px solid #f0c040;
    border-left: 3px solid #f0c040;
    padding: 8px 12px;
    border-radius: 4px;
    margin-top: 10px;
  }

  .notice-icon { font-size: 13px; flex-shrink: 0; }

  .notice-text {
    font-size: 11px;
    color: #7a6000;
  }

  /* ─── Contact Block ─── */
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
    font-weight: 400;
    word-break: break-all;
  }

  /* ─── Footer ─── */
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

  /* ─── Responsive ─── */
  @media (max-width: 600px) {
    body { padding: 0 0 60px; }
    .doc { border-left: none; border-right: none; }
    .doc-header, .doc-title-block, .toc, .doc-body, .doc-footer { padding-left: 20px; padding-right: 20px; }
    .toc { margin: 0 20px; }
    .doc-footer { margin: 0 20px; }
    .doc-header { flex-direction: column; gap: 12px; }
    .doc-meta { text-align: left; }
    .contact-grid { grid-template-columns: 1fr; }
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
      <strong>PRIVACY POLICY</strong>
      Document Version 1.0<br>
      Effective: <?php echo date("d F Y"); ?><br>
      Jurisdiction: India
    </div>
  </div>

  <!-- Title Block -->
  <div class="doc-title-block">
    <h1>Privacy Policy &amp; Data Protection Terms</h1>
    <p>This document governs how <strong>HEALTHDIAL PRIVATE LIMITED</strong> (trading as "HealthDial") collects, processes, stores, and discloses information obtained through use of the HealthDial mobile application and associated services.</p>
  </div>

  <!-- TOC -->
  <div class="toc">
    <div class="toc-label">Contents</div>
    <a href="#s1">Information Collected</a>
    <a href="#s2">Purpose of Use</a>
    <a href="#s3">Third-Party Services</a>
    <a href="#s4">Data Sharing</a>
    <a href="#s5">Data Security</a>
    <a href="#s6">Retention</a>
    <a href="#s7">Your Rights</a>
    <a href="#s8">Children</a>
    <a href="#s9">Policy Changes</a>
    <a href="#s10">Contact</a>
  </div>

  <!-- Body -->
  <div class="doc-body">

    <!-- §1 -->
    <div class="section" id="s1">
      <div class="section-head">
        <span class="section-num">§ 01</span>
        <h2>Information We Collect</h2>
      </div>

      <div class="sub-section">
        <div class="sub-label">A · Personal Information</div>
        <div class="tag-row">
          <span class="tag">Full Name</span>
          <span class="tag">Email Address</span>
          <span class="tag">Phone Number</span>
          <span class="tag">Account Credentials</span>
        </div>
      </div>

      <div class="sub-section" style="margin-top:12px">
        <div class="sub-label">B · Location Information</div>
        <div class="clauses">
          <div class="clause">
            <span class="clause-id">1.b.1</span>
            <span class="clause-text">With your explicit permission, we collect <strong>precise or approximate location data</strong> to surface nearby healthcare providers and refine search results.</span>
          </div>
          <div class="clause">
            <span class="clause-id">1.b.2</span>
            <span class="clause-text">Location access may be <strong>revoked at any time</strong> through your device's application settings without affecting other app functionality.</span>
          </div>
        </div>
      </div>

      <div class="sub-section" style="margin-top:12px">
        <div class="sub-label">C · Device &amp; Usage Data</div>
        <div class="tag-row">
          <span class="tag">Device Model</span>
          <span class="tag">OS Version</span>
          <span class="tag">IP Address</span>
          <span class="tag">App Usage Patterns</span>
          <span class="tag">Crash Logs (Firebase)</span>
          <span class="tag">Diagnostic Reports</span>
        </div>
      </div>
    </div>

    <!-- §2 -->
    <div class="section" id="s2">
      <div class="section-head">
        <span class="section-num">§ 02</span>
        <h2>How We Use Your Information</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">2.1</span>
          <span class="clause-text">To <strong>deliver healthcare listings</strong> and location-based search results tailored to your query.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.2</span>
          <span class="clause-text">To enable <strong>secure authentication</strong> and protect account integrity.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.3</span>
          <span class="clause-text">To <strong>monitor, debug, and improve</strong> app performance, stability, and user experience.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.4</span>
          <span class="clause-text">To conduct <strong>aggregate analytics</strong> via Firebase to understand usage patterns — no individual profiling is performed.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.5</span>
          <span class="clause-text">To <strong>detect and prevent fraud</strong>, abuse, and unauthorized access to our systems.</span>
        </div>
      </div>
    </div>

    <!-- §3 -->
    <div class="section" id="s3">
      <div class="section-head">
        <span class="section-num">§ 03</span>
        <h2>Third-Party Services</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">3.1</span>
          <span class="clause-text">We engage <strong>Firebase</strong> (Google LLC) for analytics, user authentication, and crash reporting services.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.2</span>
          <span class="clause-text">We engage <strong>cloud infrastructure providers</strong> for secure data storage and application hosting.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.3</span>
          <span class="clause-text">Each third-party processor operates under its own privacy policy and data processing agreements. We encourage you to review those policies independently.</span>
        </div>
      </div>
    </div>

    <!-- §4 -->
    <div class="section" id="s4">
      <div class="section-head">
        <span class="section-num">§ 04</span>
        <h2>Data Sharing &amp; Disclosure</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">4.1</span>
          <span class="clause-text"><strong>We do not sell, rent, or trade</strong> your personal data to any third party for commercial purposes.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.2</span>
          <span class="clause-text">Data may be disclosed to <strong>authorised service providers</strong> strictly to operate, maintain, and improve our application.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.3</span>
          <span class="clause-text">Disclosure may occur when <strong>required by applicable law</strong>, court order, or regulatory directive.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.4</span>
          <span class="clause-text">Disclosure may occur to <strong>protect the rights, property, or safety</strong> of HealthDial, its users, or the public.</span>
        </div>
      </div>
    </div>

    <!-- §5 -->
    <div class="section" id="s5">
      <div class="section-head">
        <span class="section-num">§ 05</span>
        <h2>Data Security</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">5.1</span>
          <span class="clause-text">All data transmissions between your device and our servers are <strong>encrypted using industry-standard TLS protocols</strong>.</span>
        </div>
        <div class="clause">
          <span class="clause-id">5.2</span>
          <span class="clause-text">We implement <strong>access controls, firewalls, and monitoring</strong> to prevent unauthorised access, alteration, or disclosure of stored data.</span>
        </div>
        <div class="clause">
          <span class="clause-id">5.3</span>
          <span class="clause-text">While we employ reasonable safeguards, <strong>no transmission over the internet is 100% secure</strong>. Use of the application is at your own discretion.</span>
        </div>
      </div>
    </div>

    <!-- §6 -->
    <div class="section" id="s6">
      <div class="section-head">
        <span class="section-num">§ 06</span>
        <h2>Data Retention</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">6.1</span>
          <span class="clause-text">User data is retained <strong>only as long as necessary</strong> to deliver services and satisfy applicable legal, accounting, or regulatory obligations.</span>
        </div>
        <div class="clause">
          <span class="clause-id">6.2</span>
          <span class="clause-text">You may <strong>request permanent account deletion</strong> at any time by contacting us. Upon verification, data will be erased within 30 days except where retention is legally required.</span>
        </div>
      </div>
    </div>

    <!-- §7 -->
    <div class="section" id="s7">
      <div class="section-head">
        <span class="section-num">§ 07</span>
        <h2>Your Rights</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">7.1</span>
          <span class="clause-text"><strong>Right of Access —</strong> Request a copy of the personal data we hold about you.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.2</span>
          <span class="clause-text"><strong>Right of Rectification —</strong> Request correction of inaccurate or incomplete personal data.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.3</span>
          <span class="clause-text"><strong>Right of Erasure —</strong> Request deletion of your personal data, subject to legal retention obligations.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.4</span>
          <span class="clause-text"><strong>Right to Withdraw Consent —</strong> Withdraw previously given consent at any time; this will not affect the lawfulness of prior processing.</span>
        </div>
      </div>
      <div class="notice">
        <span class="notice-icon">ℹ️</span>
        <span class="notice-text">To exercise any of the above rights, contact us at <strong>healthdialofficial@gmail.com</strong>. We will respond within 30 days of receiving a verifiable request.</span>
      </div>
    </div>

    <!-- §8 -->
    <div class="section" id="s8">
      <div class="section-head">
        <span class="section-num">§ 08</span>
        <h2>Children's Privacy</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">8.1</span>
          <span class="clause-text">HealthDial is <strong>not directed at children under the age of 13</strong>. We do not knowingly solicit or collect data from minors.</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.2</span>
          <span class="clause-text">If we become aware that a child under 13 has provided personal data, we will <strong>delete such data promptly</strong>. Parents may contact us to request removal.</span>
        </div>
      </div>
    </div>

    <!-- §9 -->
    <div class="section" id="s9">
      <div class="section-head">
        <span class="section-num">§ 09</span>
        <h2>Changes to This Policy</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">9.1</span>
          <span class="clause-text">We reserve the right to <strong>update this Privacy Policy</strong> at any time. Material changes will be communicated via in-app notification or email.</span>
        </div>
        <div class="clause">
          <span class="clause-id">9.2</span>
          <span class="clause-text">The <strong>"Last Updated"</strong> date at the top of this document reflects the most recent revision. Continued use of the application following any change constitutes acceptance.</span>
        </div>
      </div>
    </div>

    <!-- §10 -->
    <div class="section" id="s10">
      <div class="section-head">
        <span class="section-num">§ 10</span>
        <h2>Contact Us</h2>
      </div>
      <p style="font-size:12px; color:var(--ink-mid); margin-bottom:10px;">
        For privacy-related inquiries, data requests, or complaints, reach our data team through any of the channels below.
      </p>
      <div class="contact-grid">
        <div class="contact-item">
          <div class="contact-label">Email</div>
          <div class="contact-value">healthdialofficial@gmail.com</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Website</div>
          <div class="contact-value">https://healthdial.com</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Phone</div>
          <div class="contact-value">+91 99116 60669</div>
        </div>
      </div>
    </div>

  </div><!-- /doc-body -->

  <!-- Footer -->
  <div class="doc-footer">
    <p>&copy; <?php echo date("Y"); ?> HEALTHDIAL PRIVATE LIMITED &middot; All rights reserved</p>
    <span class="badge">v1.0 · <?php echo date("M Y"); ?></span>
  </div>

</div><!-- /doc -->
</body>
</html>
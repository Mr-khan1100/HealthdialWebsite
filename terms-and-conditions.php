<?php
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms & Conditions - HealthDial</title>
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
    --warn: #fff8e1;
    --warn-border: #f0c040;
    --warn-text: #7a6000;
    --red-soft: #fff0f0;
    --red-border: #f5c6c6;
    --red-text: #8b1a1a;
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

  /* ─── Header ─── */
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
    max-width: 600px;
  }

  /* ─── TOC ─── */
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

  /* ─── Body ─── */
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

  /* ─── Clauses ─── */
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

  .clause-text strong {
    font-weight: 500;
    color: var(--ink);
  }

  /* ─── Prohibited list ─── */
  .prohibited {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 6px;
    margin-top: 8px;
  }

  .prohibited-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: var(--red-soft);
    border: 1px solid var(--red-border);
    padding: 7px 10px;
    border-radius: 4px;
    font-size: 11px;
    color: var(--red-text);
  }

  .prohibited-item::before {
    content: "✕";
    font-size: 9px;
    font-weight: 700;
    color: #c0392b;
    padding-top: 1px;
    flex-shrink: 0;
  }

  /* ─── Notice / Alert ─── */
  .notice {
    display: flex;
    gap: 10px;
    background: var(--warn);
    border: 1px solid var(--warn-border);
    border-left: 3px solid var(--warn-border);
    padding: 8px 12px;
    border-radius: 4px;
    margin-top: 10px;
  }

  .notice-icon { font-size: 13px; flex-shrink: 0; }

  .notice-text { font-size: 11px; color: var(--warn-text); }

  /* ─── Tags ─── */
  .tag-row { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }

  .tag {
    font-size: 11px;
    font-family: 'IBM Plex Mono', monospace;
    color: var(--ink-mid);
    background: var(--bg);
    border: 1px solid var(--rule);
    padding: 3px 8px;
    border-radius: 3px;
  }

  /* ─── Contact ─── */
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
    .toc, .doc-footer { margin: 0 20px; }
    .doc-header { flex-direction: column; gap: 12px; }
    .doc-meta { text-align: left; }
    .prohibited { grid-template-columns: 1fr; }
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
      <strong>TERMS &amp; CONDITIONS</strong>
      Document Version 1.0<br>
      Effective: <?php echo date("d F Y"); ?><br>
      Jurisdiction: India
    </div>
  </div>

  <!-- Title Block -->
  <div class="doc-title-block">
    <h1>Terms &amp; Conditions of Use</h1>
    <p>These Terms govern your access to and use of the HealthDial mobile application and all associated services. By using HealthDial, you acknowledge that you have read, understood, and agree to be bound by these Terms.</p>
  </div>

  <!-- TOC -->
  <div class="toc">
    <div class="toc-label">Contents</div>
    <a href="#s1">Acceptance</a>
    <a href="#s2">Service Description</a>
    <a href="#s3">Medical Disclaimer</a>
    <a href="#s4">User Accounts</a>
    <a href="#s5">Acceptable Use</a>
    <a href="#s6">Intellectual Property</a>
    <a href="#s7">Limitation of Liability</a>
    <a href="#s8">Payment &amp; Subscriptions</a>
    <a href="#s9">Refund &amp; Cancellation</a>
    <a href="#s10">Third-Party Links</a>
    <a href="#s11">Modifications</a>
    <a href="#s12">Governing Law</a>
    <a href="#s13">Company Information</a>
    <a href="#s14">Contact</a>
  </div>

  <!-- Body -->
  <div class="doc-body">

    <!-- §1 -->
    <div class="section" id="s1">
      <div class="section-head">
        <span class="section-num">§ 01</span>
        <h2>Acceptance of Terms</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">1.1</span>
          <span class="clause-text">By downloading, installing, or using HealthDial, you <strong>agree to be legally bound</strong> by these Terms and Conditions and our Privacy Policy.</span>
        </div>
        <div class="clause">
          <span class="clause-id">1.2</span>
          <span class="clause-text">If you <strong>do not agree</strong> to any part of these Terms, you must immediately discontinue use of the application.</span>
        </div>
        <div class="clause">
          <span class="clause-id">1.3</span>
          <span class="clause-text">These Terms constitute a <strong>legally binding agreement</strong> between you ("User") and <strong>HEALTHDIAL PRIVATE LIMITED</strong>, operating as HealthDial ("Company", "we", "us").</span>
        </div>
      </div>
    </div>

    <!-- §2 -->
    <div class="section" id="s2">
      <div class="section-head">
        <span class="section-num">§ 02</span>
        <h2>Service Description</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">2.1</span>
          <span class="clause-text">HealthDial is a <strong>healthcare listing platform</strong> that enables users to discover hospitals, clinics, and medical professionals in their vicinity.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.2</span>
          <span class="clause-text">HealthDial acts solely as an <strong>information directory</strong>. We do not provide medical consultations, diagnoses, treatments, or emergency services.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.3</span>
          <span class="clause-text">We do not guarantee the <strong>accuracy, completeness, or availability</strong> of any listed healthcare provider's information.</span>
        </div>
      </div>
    </div>

    <!-- §3 -->
    <div class="section" id="s3">
      <div class="section-head">
        <span class="section-num">§ 03</span>
        <h2>Medical Disclaimer</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">3.1</span>
          <span class="clause-text">All content accessible through HealthDial is provided <strong>for informational purposes only</strong> and does not constitute professional medical advice.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.2</span>
          <span class="clause-text">You must <strong>always consult a qualified and registered healthcare professional</strong> before making any medical decisions or taking any action based on information found in the application.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.3</span>
          <span class="clause-text">In a <strong>medical emergency</strong>, contact emergency services immediately. Do not rely on this application for urgent medical situations.</span>
        </div>
      </div>
      <div class="notice">
        <span class="notice-icon">⚠️</span>
        <span class="notice-text"><strong>Important:</strong> HealthDial is not a substitute for professional medical consultation, emergency care, or clinical diagnosis.</span>
      </div>
    </div>

    <!-- §4 -->
    <div class="section" id="s4">
      <div class="section-head">
        <span class="section-num">§ 04</span>
        <h2>User Accounts</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">4.1</span>
          <span class="clause-text">You are solely responsible for <strong>maintaining the confidentiality</strong> of your account credentials and for all activities that occur under your account.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.2</span>
          <span class="clause-text">You agree to provide <strong>accurate, current, and complete</strong> information during registration and to keep your information up to date.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.3</span>
          <span class="clause-text">You must <strong>notify us immediately</strong> at healthdialofficial@gmail.com upon becoming aware of any unauthorised use of your account.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.4</span>
          <span class="clause-text">HealthDial reserves the right to <strong>suspend or terminate accounts</strong> that violate these Terms or engage in fraudulent activity.</span>
        </div>
      </div>
    </div>

    <!-- §5 -->
    <div class="section" id="s5">
      <div class="section-head">
        <span class="section-num">§ 05</span>
        <h2>Acceptable Use</h2>
      </div>
      <p style="font-size:12px; color:var(--ink-mid); margin-bottom:10px;">The following activities are strictly prohibited when using HealthDial:</p>
      <div class="prohibited">
        <div class="prohibited-item">Using the app for any unlawful or fraudulent purpose</div>
        <div class="prohibited-item">Attempting unauthorised access to any system or data</div>
        <div class="prohibited-item">Interfering with or disrupting app functionality or servers</div>
        <div class="prohibited-item">Submitting false, misleading, or defamatory information</div>
        <div class="prohibited-item">Scraping, copying, or reproducing content without consent</div>
        <div class="prohibited-item">Impersonating any person or entity</div>
        <div class="prohibited-item">Transmitting malware, viruses, or harmful code</div>
        <div class="prohibited-item">Engaging in spam, unsolicited messaging, or abuse</div>
      </div>
      <div class="notice" style="margin-top:12px;">
        <span class="notice-icon">ℹ️</span>
        <span class="notice-text">Violations may result in immediate account suspension and may be reported to relevant legal authorities.</span>
      </div>
    </div>

    <!-- §6 -->
    <div class="section" id="s6">
      <div class="section-head">
        <span class="section-num">§ 06</span>
        <h2>Intellectual Property</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">6.1</span>
          <span class="clause-text">All content, design, software, trademarks, logos, and databases within HealthDial are the <strong>exclusive property of HealthDial</strong> and are protected under applicable intellectual property laws.</span>
        </div>
        <div class="clause">
          <span class="clause-id">6.2</span>
          <span class="clause-text">You are granted a <strong>limited, non-exclusive, non-transferable licence</strong> to access and use the application solely for personal, non-commercial purposes.</span>
        </div>
        <div class="clause">
          <span class="clause-id">6.3</span>
          <span class="clause-text"><strong>Reproduction, redistribution, or commercial exploitation</strong> of any application content without prior written consent is strictly prohibited.</span>
        </div>
      </div>
    </div>

    <!-- §7 -->
    <div class="section" id="s7">
      <div class="section-head">
        <span class="section-num">§ 07</span>
        <h2>Limitation of Liability</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">7.1</span>
          <span class="clause-text">HealthDial shall not be liable for any <strong>indirect, incidental, special, consequential, or punitive damages</strong> arising out of or in connection with your use of the application.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.2</span>
          <span class="clause-text">We do not warrant that the application will be <strong>uninterrupted, error-free, or free of harmful components</strong> at all times.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.3</span>
          <span class="clause-text">To the maximum extent permitted by law, our <strong>total aggregate liability</strong> to you shall not exceed the amount paid by you, if any, to access the application in the preceding 12 months.</span>
        </div>
      </div>
    </div>

    <!-- §8 Payment & Subscriptions -->
    <div class="section" id="s8">
      <div class="section-head">
        <span class="section-num">§ 08</span>
        <h2>Payment &amp; Subscription Terms</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">8.1</span>
          <span class="clause-text">HealthDial offers <strong>paid promotional listing services</strong> ("Promotion Plans") that allow healthcare providers to increase the visibility of their listings on the platform.</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.2</span>
          <span class="clause-text">All prices for Promotion Plans are displayed in <strong>Indian Rupees (INR)</strong> and are inclusive of applicable taxes unless otherwise stated.</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.3</span>
          <span class="clause-text">Payments are processed securely through <strong>Cashfree Payments</strong>, a PCI DSS-compliant payment gateway. HealthDial does not store your credit/debit card details or banking credentials on its servers.</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.4</span>
          <span class="clause-text">Upon successful payment, your selected Promotion Plan will be <strong>activated immediately</strong> and will remain active for the duration specified in the plan.</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.5</span>
          <span class="clause-text">Promotion Plans are <strong>non-recurring</strong> and do not auto-renew. You must manually purchase a new plan upon expiry if you wish to continue promotion.</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.6</span>
          <span class="clause-text">HealthDial reserves the right to <strong>modify pricing</strong> of Promotion Plans at any time. Existing active plans will not be affected by price changes.</span>
        </div>
      </div>
      <div class="notice">
        <span class="notice-icon">🔒</span>
        <span class="notice-text"><strong>Secure Payments:</strong> All transactions are encrypted and processed via Cashfree Payments with bank-grade security. We never store your payment credentials.</span>
      </div>
    </div>

    <!-- §9 Refund & Cancellation -->
    <div class="section" id="s9">
      <div class="section-head">
        <span class="section-num">§ 09</span>
        <h2>Refund &amp; Cancellation Policy</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">9.1</span>
          <span class="clause-text">Refunds for Promotion Plans are governed by our <strong><a href="refund-policy.php" style="color:var(--accent);">Refund & Cancellation Policy</a></strong>, which forms an integral part of these Terms.</span>
        </div>
        <div class="clause">
          <span class="clause-id">9.2</span>
          <span class="clause-text">Refund requests must be submitted within <strong>48 hours</strong> of purchase. Once a promotion has been actively displayed for more than 48 hours, it is considered a <strong>delivered service</strong> and is non-refundable.</span>
        </div>
        <div class="clause">
          <span class="clause-id">9.3</span>
          <span class="clause-text">In case of a <strong>failed payment</strong> where the amount is debited but the order is not confirmed, the amount will be automatically refunded to your original payment method within <strong>5–7 business days</strong>.</span>
        </div>
        <div class="clause">
          <span class="clause-id">9.4</span>
          <span class="clause-text">Cancellation of a Promotion Plan before its expiry date will <strong>not</strong> entitle the user to a pro-rated refund unless explicitly approved by HealthDial support.</span>
        </div>
      </div>
    </div>

    <!-- §10 -->
    <div class="section" id="s10">
      <div class="section-head">
        <span class="section-num">§ 10</span>
        <h2>Third-Party Links &amp; Services</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">10.1</span>
          <span class="clause-text">The application may contain links to <strong>third-party websites or services</strong> that are not owned or controlled by HealthDial.</span>
        </div>
        <div class="clause">
          <span class="clause-id">10.2</span>
          <span class="clause-text">We assume <strong>no responsibility</strong> for the content, privacy policies, practices, or availability of any third-party websites.</span>
        </div>
        <div class="clause">
          <span class="clause-id">10.3</span>
          <span class="clause-text">Access to third-party content is <strong>at your own risk</strong>. We encourage you to review the terms and policies of any external site you visit.</span>
        </div>
      </div>
    </div>

    <!-- §11 -->
    <div class="section" id="s11">
      <div class="section-head">
        <span class="section-num">§ 11</span>
        <h2>Modifications to Terms</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">11.1</span>
          <span class="clause-text">We reserve the right to <strong>revise these Terms</strong> at any time without prior notice. Changes take effect immediately upon posting.</span>
        </div>
        <div class="clause">
          <span class="clause-id">11.2</span>
          <span class="clause-text"><strong>Continued use</strong> of the application after any modification constitutes your acceptance of the revised Terms.</span>
        </div>
        <div class="clause">
          <span class="clause-id">11.3</span>
          <span class="clause-text">It is your responsibility to <strong>review these Terms periodically</strong>. The "Effective" date at the top indicates when changes last occurred.</span>
        </div>
      </div>
    </div>

    <!-- §12 -->
    <div class="section" id="s12">
      <div class="section-head">
        <span class="section-num">§ 12</span>
        <h2>Governing Law &amp; Dispute Resolution</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">12.1</span>
          <span class="clause-text">These Terms shall be governed by and construed in accordance with the <strong>laws of India</strong>, including the Information Technology Act, 2000 and the Consumer Protection Act, 2019, without regard to conflict of law provisions.</span>
        </div>
        <div class="clause">
          <span class="clause-id">12.2</span>
          <span class="clause-text">Any disputes arising out of or relating to these Terms shall be subject to the <strong>exclusive jurisdiction of the courts of India</strong>.</span>
        </div>
        <div class="clause">
          <span class="clause-id">12.3</span>
          <span class="clause-text">We encourage users to first attempt resolution through <strong>direct communication</strong> with our support team before pursuing formal legal proceedings.</span>
        </div>
      </div>
    </div>

    <!-- §13 Company Information -->
    <div class="section" id="s13">
      <div class="section-head">
        <span class="section-num">§ 13</span>
        <h2>Company Information</h2>
      </div>
      <p style="font-size:12px; color:var(--ink-mid); margin-bottom:10px;">
        HealthDial is a healthcare listing and discovery platform owned and operated by <strong>HEALTHDIAL PRIVATE LIMITED</strong>, a company incorporated under the laws of India.
      </p>
      <div class="contact-grid">
        <div class="contact-item">
          <div class="contact-label">Legal Entity Name</div>
          <div class="contact-value" style="color:var(--ink); font-weight:600;">HEALTHDIAL PRIVATE LIMITED</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Trading As</div>
          <div class="contact-value" style="color:var(--ink);">HealthDial</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Nature of Business</div>
          <div class="contact-value" style="color:var(--ink);">Healthcare Directory &amp; Listing Platform</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Services Offered</div>
          <div class="contact-value" style="color:var(--ink);">Healthcare provider listings, promotional plans for providers, health information</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Website</div>
          <div class="contact-value">https://healthdial.com</div>
        </div>
      </div>
      <div class="notice" style="margin-top:12px;">
        <span class="notice-icon">ℹ️</span>
        <span class="notice-text">HealthDial is a digital services platform. No physical goods are shipped or delivered. All services are provided digitally through the website and mobile application.</span>
      </div>
    </div>

    <!-- §14 -->
    <div class="section" id="s14">
      <div class="section-head">
        <span class="section-num">§ 14</span>
        <h2>Contact Us</h2>
      </div>
      <p style="font-size:12px; color:var(--ink-mid); margin-bottom:10px;">
        For legal queries, Terms-related questions, payment disputes, or to report violations, reach our team through the channels below.
      </p>
      <div class="contact-grid">
        <div class="contact-item">
          <div class="contact-label">Support Email</div>
          <div class="contact-value">healthdialofficial@gmail.com</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Website</div>
          <div class="contact-value">https://healthdial.com</div>
        </div>
        <div class="contact-item">
          <div class="contact-label">Support Page</div>
          <div class="contact-value"><a href="contact.php" style="color:var(--accent);">healthdial.com/contact.php</a></div>
        </div>
      </div>
    </div>

  </div><!-- /doc-body -->

  <!-- Footer -->
  <div class="doc-footer">
    <p>© <?php echo date("Y"); ?> HealthDial · All rights reserved</p>
    <span class="badge">v1.0 · <?php echo date("M Y"); ?></span>
  </div>

</div>
</body>
</html>
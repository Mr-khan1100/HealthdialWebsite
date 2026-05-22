<?php
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Refund & Cancellation Policy - HealthDial</title>
<meta name="description" content="HealthDial's refund and cancellation policy for listing promotion payments processed via Cashfree payment gateway.">
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

  .doc-meta strong { display: block; color: var(--ink-mid); font-size: 11px; }

  .doc-title-block {
    padding: 28px 40px 20px;
    border-bottom: 1px solid var(--rule);
    background: var(--accent-soft);
  }

  .doc-title-block h1 {
    font-family: 'IBM Plex Serif', serif;
    font-size: 22px; font-weight: 600;
    color: var(--ink); letter-spacing: -.4px;
    margin-bottom: 6px;
  }

  .doc-title-block p { font-size: 12px; color: var(--ink-mid); max-width: 600px; }

  .toc {
    margin: 0 40px;
    border-bottom: 1px solid var(--rule);
    padding: 18px 0;
    display: flex; flex-wrap: wrap; gap: 4px 0;
  }

  .toc-label {
    width: 100%;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px; font-weight: 500;
    letter-spacing: .12em; text-transform: uppercase;
    color: var(--ink-light); margin-bottom: 8px;
  }

  .toc a {
    font-size: 11px; color: var(--accent);
    text-decoration: none; padding: 2px 10px 2px 0;
    white-space: nowrap;
  }

  .toc a:hover { text-decoration: underline; }
  .toc a::after { content: " ·"; color: var(--rule); margin-left: 10px; }
  .toc a:last-child::after { content: ""; }

  .doc-body { padding: 0 40px 40px; }

  .section { border-bottom: 1px solid var(--rule); padding: 22px 0; }
  .section:last-child { border-bottom: none; }

  .section-head { display: flex; align-items: baseline; gap: 12px; margin-bottom: 10px; }

  .section-num {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px; font-weight: 500;
    letter-spacing: .1em; color: var(--accent);
    background: var(--accent-soft);
    border: 1px solid rgba(0,102,204,.15);
    padding: 2px 6px; border-radius: 3px;
    white-space: nowrap; flex-shrink: 0;
  }

  .section h2 {
    font-family: 'IBM Plex Sans', sans-serif;
    font-size: 12px; font-weight: 500;
    letter-spacing: .06em; text-transform: uppercase;
    color: var(--ink);
  }

  .clauses { display: grid; gap: 6px; }

  .clause {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 7px 10px; border-radius: 4px;
    border: 1px solid transparent;
    transition: background .15s, border-color .15s;
  }

  .clause:hover { background: var(--bg); border-color: var(--rule); }

  .clause-id {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px; color: var(--ink-light);
    padding-top: 2px; flex-shrink: 0; width: 28px;
  }

  .clause-text { font-size: 12px; color: var(--ink-mid); line-height: 1.6; }
  .clause-text strong { font-weight: 500; color: var(--ink); }

  .notice {
    display: flex; gap: 10px;
    background: var(--warn);
    border: 1px solid var(--warn-border);
    border-left: 3px solid var(--warn-border);
    padding: 8px 12px; border-radius: 4px;
    margin-top: 10px;
  }

  .notice-icon { font-size: 13px; flex-shrink: 0; }
  .notice-text { font-size: 11px; color: var(--warn-text); }

  .notice-red {
    background: var(--red-soft);
    border-color: var(--red-border);
    border-left-color: var(--red-border);
  }

  .notice-red .notice-text { color: var(--red-text); }

  .contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 8px; margin-top: 10px;
  }

  .contact-item { border: 1px solid var(--rule); padding: 10px 14px; border-radius: 4px; }

  .contact-label {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px; letter-spacing: .1em;
    text-transform: uppercase; color: var(--ink-light);
    margin-bottom: 3px;
  }

  .contact-value { font-size: 12px; color: var(--accent); word-break: break-all; }

  .doc-footer {
    margin: 0 40px;
    padding: 16px 0 24px;
    display: flex; align-items: center;
    justify-content: space-between;
    border-top: 1px solid var(--rule);
  }

  .doc-footer p { font-family: 'IBM Plex Mono', monospace; font-size: 10px; color: var(--ink-light); }

  .badge {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px; font-weight: 500;
    letter-spacing: .08em; text-transform: uppercase;
    color: var(--accent);
    border: 1px solid rgba(0,102,204,.3);
    padding: 3px 8px; border-radius: 3px;
  }

  @media (max-width: 600px) {
    body { padding: 0 0 60px; }
    .doc { border-left: none; border-right: none; }
    .doc-header, .doc-title-block, .doc-body, .doc-footer { padding-left: 20px; padding-right: 20px; }
    .toc, .doc-footer { margin: 0 20px; }
    .doc-header { flex-direction: column; gap: 12px; }
    .doc-meta { text-align: left; }
    .contact-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<div class="doc">

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
      <strong>REFUND & CANCELLATION</strong>
      Document Version 1.0<br>
      Effective: <?php echo date("d F Y"); ?><br>
      Jurisdiction: India
    </div>
  </div>

  <div class="doc-title-block">
    <h1>Refund & Cancellation Policy</h1>
    <p>This policy outlines the terms and conditions governing refunds, cancellations, and adjustments for listing promotion payments made through <strong>HEALTHDIAL PRIVATE LIMITED</strong> (trading as "HealthDial") via the Cashfree payment gateway.</p>
  </div>

  <div class="toc">
    <div class="toc-label">Contents</div>
    <a href="#s1">Scope</a>
    <a href="#s2">Services Covered</a>
    <a href="#s3">Cancellation Policy</a>
    <a href="#s4">Refund Eligibility</a>
    <a href="#s5">Non-Refundable Cases</a>
    <a href="#s6">Refund Process</a>
    <a href="#s7">Refund Timeline</a>
    <a href="#s8">Dispute Resolution</a>
    <a href="#s9">Contact</a>
  </div>

  <div class="doc-body">

    <!-- §1 -->
    <div class="section" id="s1">
      <div class="section-head">
        <span class="section-num">§ 01</span>
        <h2>Scope of This Policy</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">1.1</span>
          <span class="clause-text">This Refund & Cancellation Policy applies to all <strong>listing promotion payments</strong> made through the HealthDial website or application using the Cashfree payment gateway.</span>
        </div>
        <div class="clause">
          <span class="clause-id">1.2</span>
          <span class="clause-text">By making a payment on our platform, you acknowledge that you have <strong>read, understood, and agreed</strong> to the terms outlined in this policy.</span>
        </div>
      </div>
    </div>

    <!-- §2 -->
    <div class="section" id="s2">
      <div class="section-head">
        <span class="section-num">§ 02</span>
        <h2>Services Covered</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">2.1</span>
          <span class="clause-text">HealthDial is a <strong>digital healthcare directory platform</strong>. The only paid service currently offered is <strong>listing promotion</strong> — boosting the visibility of a healthcare provider's listing in search results.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.2</span>
          <span class="clause-text">HealthDial <strong>does not sell or deliver physical products</strong>. All transactions are for digital advertising and promotion services only.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.3</span>
          <span class="clause-text">Promotion plans include features such as <strong>top search positioning, "Sponsored" badges, and extended visibility</strong> for a defined duration as selected during purchase.</span>
        </div>
      </div>
    </div>

    <!-- §3 -->
    <div class="section" id="s3">
      <div class="section-head">
        <span class="section-num">§ 03</span>
        <h2>Cancellation Policy</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">3.1</span>
          <span class="clause-text">Cancellation requests may be submitted <strong>within 24 hours</strong> of purchase, provided the promotion has not yet been activated or delivered.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.2</span>
          <span class="clause-text">Once a listing promotion is <strong>activated and live</strong> on the platform, cancellation is not possible as the service has been delivered.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.3</span>
          <span class="clause-text">To request a cancellation, email us at <strong>healthdialofficial@gmail.com</strong> with your order ID, listing name, and reason for cancellation.</span>
        </div>
      </div>
    </div>

    <!-- §4 -->
    <div class="section" id="s4">
      <div class="section-head">
        <span class="section-num">§ 04</span>
        <h2>Refund Eligibility</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">4.1</span>
          <span class="clause-text"><strong>Full refund</strong> will be issued if: the payment was processed but the promotion was never activated due to a system error on our end.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.2</span>
          <span class="clause-text"><strong>Full refund</strong> will be issued if: duplicate or erroneous charges were made to your payment method.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.3</span>
          <span class="clause-text"><strong>Partial refund</strong> may be considered if: the promotion was active for less than 50% of the purchased duration and a valid complaint is raised about service quality.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.4</span>
          <span class="clause-text"><strong>Full refund</strong> will be issued if: the listing associated with the promotion was removed or rejected by HealthDial before the promotion period began.</span>
        </div>
      </div>
    </div>

    <!-- §5 -->
    <div class="section" id="s5">
      <div class="section-head">
        <span class="section-num">§ 05</span>
        <h2>Non-Refundable Cases</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">5.1</span>
          <span class="clause-text">Promotions that have been <strong>fully delivered</strong> (the promotion period has elapsed or is currently active) are non-refundable.</span>
        </div>
        <div class="clause">
          <span class="clause-id">5.2</span>
          <span class="clause-text">Refunds will not be issued if the listing was <strong>removed or suspended due to policy violations</strong> by the listing owner during the promotion period.</span>
        </div>
        <div class="clause">
          <span class="clause-id">5.3</span>
          <span class="clause-text">Dissatisfaction with promotion results (e.g., <strong>"not enough clicks"</strong>) does not qualify for a refund, as HealthDial does not guarantee specific performance metrics.</span>
        </div>
      </div>
      <div class="notice notice-red" style="margin-top:12px;">
        <span class="notice-icon">⚠️</span>
        <span class="notice-text"><strong>Important:</strong> HealthDial does not guarantee specific traffic, call volume, or patient conversion rates from promoted listings. Promotion increases visibility only.</span>
      </div>
    </div>

    <!-- §6 -->
    <div class="section" id="s6">
      <div class="section-head">
        <span class="section-num">§ 06</span>
        <h2>Refund Process</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">6.1</span>
          <span class="clause-text">To initiate a refund, send an email to <strong>healthdialofficial@gmail.com</strong> with the subject line "Refund Request" and include: your order ID, payment amount, date of purchase, and reason for refund.</span>
        </div>
        <div class="clause">
          <span class="clause-id">6.2</span>
          <span class="clause-text">Our team will <strong>review your request within 3–5 business days</strong> and respond with a decision.</span>
        </div>
        <div class="clause">
          <span class="clause-id">6.3</span>
          <span class="clause-text">Approved refunds will be processed to the <strong>original payment method</strong> used during purchase via the Cashfree payment gateway.</span>
        </div>
      </div>
    </div>

    <!-- §7 -->
    <div class="section" id="s7">
      <div class="section-head">
        <span class="section-num">§ 07</span>
        <h2>Refund Timeline</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">7.1</span>
          <span class="clause-text">Once a refund is approved, the amount will be <strong>credited within 5–10 business days</strong>, depending on your bank or payment provider.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.2</span>
          <span class="clause-text">UPI and wallet refunds are typically processed <strong>within 2–3 business days</strong>.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.3</span>
          <span class="clause-text">Credit/debit card refunds may take <strong>5–10 business days</strong> to reflect in your statement.</span>
        </div>
        <div class="clause">
          <span class="clause-id">7.4</span>
          <span class="clause-text">Net banking refunds may take <strong>7–10 business days</strong> to process.</span>
        </div>
      </div>
      <div class="notice">
        <span class="notice-icon">ℹ️</span>
        <span class="notice-text">Refund processing times may vary based on your bank's policies. HealthDial is not responsible for delays caused by third-party financial institutions.</span>
      </div>
    </div>

    <!-- §8 -->
    <div class="section" id="s8">
      <div class="section-head">
        <span class="section-num">§ 08</span>
        <h2>Dispute Resolution</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">8.1</span>
          <span class="clause-text">If you are not satisfied with the refund decision, you may escalate your complaint by writing to <strong>healthdialofficial@gmail.com</strong> with subject "Escalation".</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.2</span>
          <span class="clause-text">Escalated disputes will be reviewed by <strong>senior management within 7 business days</strong>.</span>
        </div>
        <div class="clause">
          <span class="clause-id">8.3</span>
          <span class="clause-text">All disputes are subject to the <strong>exclusive jurisdiction of the courts of India</strong>.</span>
        </div>
      </div>
    </div>

    <!-- §9 Contact -->
    <div class="section" id="s9">
      <div class="section-head">
        <span class="section-num">§ 09</span>
        <h2>Contact Us</h2>
      </div>
      <p style="font-size:12px; color:var(--ink-mid); margin-bottom:10px;">
        For all refund and cancellation inquiries, reach our support team through the channels below.
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

  <div class="doc-footer">
    <p>&copy; <?php echo date("Y"); ?> HEALTHDIAL PRIVATE LIMITED &middot; All rights reserved</p>
    <span class="badge">v1.0 · <?php echo date("M Y"); ?></span>
  </div>

</div><!-- /doc -->
</body>
</html>

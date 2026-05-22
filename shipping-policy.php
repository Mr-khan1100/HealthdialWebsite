<?php
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shipping & Delivery Policy - HealthDial</title>
<meta name="description" content="HealthDial's shipping and delivery policy — clarifying that HealthDial is a digital platform with no physical goods delivery.">
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
    --green-text: #166534;
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
    padding: 8px 12px; border-radius: 4px;
    margin-top: 10px;
  }

  .notice-green {
    background: var(--green-soft);
    border: 1px solid var(--green-border);
    border-left: 3px solid var(--green-border);
  }

  .notice-green .notice-text { color: var(--green-text); }

  .notice-icon { font-size: 13px; flex-shrink: 0; }
  .notice-text { font-size: 11px; }

  .delivery-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    font-size: 12px;
  }

  .delivery-table th {
    background: var(--bg);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 9px;
    font-weight: 500;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--ink-light);
    text-align: left;
    padding: 8px 12px;
    border: 1px solid var(--rule);
  }

  .delivery-table td {
    padding: 8px 12px;
    border: 1px solid var(--rule);
    color: var(--ink-mid);
  }

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
    .doc-footer { margin: 0 20px; }
    .doc-header { flex-direction: column; gap: 12px; }
    .doc-meta { text-align: left; }
    .contact-grid { grid-template-columns: 1fr; }
    .delivery-table { font-size: 11px; }
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
      <strong>SHIPPING & DELIVERY</strong>
      Document Version 1.0<br>
      Effective: <?php echo date("d F Y"); ?><br>
      Jurisdiction: India
    </div>
  </div>

  <div class="doc-title-block">
    <h1>Shipping & Delivery Policy</h1>
    <p>This document outlines the delivery terms for services purchased through the HealthDial platform. HealthDial is a digital-only platform and does not sell or ship physical products.</p>
  </div>

  <div class="doc-body">

    <!-- §1 -->
    <div class="section" id="s1">
      <div class="section-head">
        <span class="section-num">§ 01</span>
        <h2>Nature of Services</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">1.1</span>
          <span class="clause-text">HealthDial is a <strong>digital healthcare listing and discovery platform</strong>. All services provided through our platform are entirely digital in nature.</span>
        </div>
        <div class="clause">
          <span class="clause-id">1.2</span>
          <span class="clause-text"><strong>No physical products are sold, shipped, or delivered</strong> by HealthDial. There are no physical goods involved in any transaction on our platform.</span>
        </div>
        <div class="clause">
          <span class="clause-id">1.3</span>
          <span class="clause-text">The paid services offered by HealthDial consist of <strong>listing promotion services</strong> — digital advertising that enhances the visibility of healthcare provider listings on the platform.</span>
        </div>
      </div>
      <div class="notice notice-green" style="margin-top:12px;">
        <span class="notice-icon">✅</span>
        <span class="notice-text"><strong>Key Point:</strong> Since HealthDial is a 100% digital platform, no physical shipping or delivery logistics are involved. All paid services are delivered digitally.</span>
      </div>
    </div>

    <!-- §2 -->
    <div class="section" id="s2">
      <div class="section-head">
        <span class="section-num">§ 02</span>
        <h2>Digital Service Delivery</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">2.1</span>
          <span class="clause-text">Upon successful payment for a listing promotion plan, the promotion is <strong>activated automatically</strong> on the HealthDial platform.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.2</span>
          <span class="clause-text">Promotion activation typically occurs <strong>within minutes</strong> of payment confirmation from the Cashfree payment gateway.</span>
        </div>
        <div class="clause">
          <span class="clause-id">2.3</span>
          <span class="clause-text">The promoted listing will receive enhanced visibility including <strong>top search positioning</strong> and a <strong>"Sponsored" badge</strong> for the duration of the purchased plan.</span>
        </div>
      </div>

      <table class="delivery-table">
        <thead>
          <tr>
            <th>Service</th>
            <th>Delivery Method</th>
            <th>Delivery Timeline</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Listing Promotion (Basic)</td>
            <td>Automatic digital activation</td>
            <td>Within minutes of payment</td>
          </tr>
          <tr>
            <td>Listing Promotion (Standard)</td>
            <td>Automatic digital activation</td>
            <td>Within minutes of payment</td>
          </tr>
          <tr>
            <td>Listing Promotion (Premium)</td>
            <td>Automatic digital activation</td>
            <td>Within minutes of payment</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- §3 -->
    <div class="section" id="s3">
      <div class="section-head">
        <span class="section-num">§ 03</span>
        <h2>Delivery Confirmation</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">3.1</span>
          <span class="clause-text">Upon successful activation, you will be redirected to a <strong>payment confirmation page</strong> on the HealthDial website confirming your promotion details.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.2</span>
          <span class="clause-text">Your promotion status can be verified by <strong>searching for your listing</strong> on the HealthDial platform and checking for the "Sponsored" badge.</span>
        </div>
        <div class="clause">
          <span class="clause-id">3.3</span>
          <span class="clause-text">If your promotion is not activated within <strong>30 minutes</strong> of a successful payment, please contact our support team at healthdialofficial@gmail.com.</span>
        </div>
      </div>
    </div>

    <!-- §4 -->
    <div class="section" id="s4">
      <div class="section-head">
        <span class="section-num">§ 04</span>
        <h2>Service Availability</h2>
      </div>
      <div class="clauses">
        <div class="clause">
          <span class="clause-id">4.1</span>
          <span class="clause-text">HealthDial services are available <strong>24 hours a day, 7 days a week</strong>, subject to scheduled maintenance and unforeseen technical issues.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.2</span>
          <span class="clause-text">In the event of scheduled maintenance that may affect promotion delivery, we will endeavour to <strong>notify users in advance</strong> via the platform.</span>
        </div>
        <div class="clause">
          <span class="clause-id">4.3</span>
          <span class="clause-text">If a paid promotion cannot be delivered due to technical issues on our end, a <strong>full refund</strong> will be issued as per our Refund & Cancellation Policy.</span>
        </div>
      </div>
    </div>

    <!-- §5 Contact -->
    <div class="section" id="s5">
      <div class="section-head">
        <span class="section-num">§ 05</span>
        <h2>Contact Us</h2>
      </div>
      <p style="font-size:12px; color:var(--ink-mid); margin-bottom:10px;">
        For any questions about service delivery or to report delivery issues, contact us through any channel below.
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
    <p>© <?php echo date("Y"); ?> HealthDial · All rights reserved</p>
    <span class="badge">v1.0 · <?php echo date("M Y"); ?></span>
  </div>

</div><!-- /doc -->
</body>
</html>

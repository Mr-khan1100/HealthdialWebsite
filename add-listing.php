<?php
$currentPage = 'add-listing';
$pageTitle = 'Add Your Medical Listing | HealthDial';
$pageDesc = 'List your hospital, clinic, pharmacy, lab or medical facility on HealthDial for free. Reach thousands of patients searching for healthcare near them.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/website_banner.php';

$categories = [];
$catData = fetch_api_data(API_BASE . 'get_categories.php');
if ($catData && !empty($catData['success']) && !empty($catData['data'])) {
    $categories = $catData['data'];
}
?>

<style>
    /* ===== ADD LISTING BANNER ===== */
    .al-hero {
        max-width: 1200px;
        margin: 64px auto 0;
        padding: 0 20px;
        box-sizing: border-box;
    }

    .al-hero-img {
        width: 100%;
        height: 360px;
        display: block;
        object-fit: fill;
        object-position: center;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10);
    }

    @media (max-width: 640px) {
        .al-hero {
            margin-top: 56px;
            padding: 0 12px;
        }

        .al-hero-img {
            height: 130px;
            border-radius: 12px;
        }
    }

    /* ===== STEP GUIDE ===== */
    .al-guide {
        background: var(--bg, #060d1f);
        padding: 15px 0 30px;
        border-top: 1px solid rgba(255, 255, 255, 0.07);
    }

    .al-guide-heading {
        text-align: center;
        margin-bottom: 48px;
    }

    .al-guide-heading h2 {
        font-size: clamp(22px, 3vw, 32px);
        font-weight: 800;
        color: var(--text, #f0f6ff);
        margin-bottom: 10px;
    }

    .al-guide-heading p {
        font-size: 15px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.6));
        max-width: 520px;
        margin: 0 auto;
    }

    /* Steps */
    .al-steps {
        display: flex;
        flex-direction: column;
        gap: 0;
        margin-bottom: 56px;
    }

    .al-step {
        display: grid;
        grid-template-columns: 120px 1fr;
        align-items: center;
        gap: 28px;
        padding: 28px 0;
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.07));
    }

    .al-step:last-child {
        border-bottom: none;
    }

    .al-step-illus {
        width: 120px;
        height: 100px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
    }

    .al-step-illus i {
        font-size: 42px;
        position: relative;
        z-index: 1;
    }

    .al-step-illus::before {
        content: '';
        position: absolute;
        inset: 0;
        opacity: .15;
        background: currentColor;
    }

    .al-step-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .6px;
        text-transform: uppercase;
        color: var(--text-secondary, rgba(240, 246, 255, 0.5));
        margin-bottom: 5px;
    }

    .al-step-text h3 {
        font-size: 17px;
        font-weight: 700;
        color: var(--text, #f0f6ff);
        margin-bottom: 6px;
    }

    .al-step-text p {
        font-size: 14px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.6));
        line-height: 1.55;
        margin: 0;
    }

    @media (max-width: 480px) {
        .al-step {
            grid-template-columns: 80px 1fr;
            gap: 16px;
        }

        .al-step-illus {
            width: 80px;
            height: 72px;
            border-radius: 12px;
        }

        .al-step-illus i {
            font-size: 30px;
        }
    }

    /* QR promo section */
    .al-qr-section {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0ea5e9 100%);
        border-radius: 24px;
        padding: 40px 36px;
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 36px;
    }

    .al-qr-section-text h3 {
        font-size: clamp(20px, 2.5vw, 28px);
        font-weight: 800;
        color: #fff;
        margin-bottom: 10px;
    }

    .al-qr-section-text p {
        font-size: 14px;
        color: rgba(255, 255, 255, .82);
        max-width: 440px;
        line-height: 1.6;
        margin-bottom: 20px;
    }

    .al-qr-steps {
        list-style: none;
        padding: 0;
        margin: 0 0 24px;
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .al-qr-steps li {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: rgba(255, 255, 255, .88);
    }

    .al-qr-steps li i {
        color: #fbbf24;
        font-size: 13px;
        flex-shrink: 0;
    }

    .al-promote-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        color: #1e3a8a;
        font-weight: 700;
        font-size: 14px;
        padding: 12px 24px;
        border-radius: 100px;
        text-decoration: none;
        box-shadow: 0 4px 16px rgba(0, 0, 0, .2);
        transition: transform .18s, box-shadow .18s;
    }

    .al-promote-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, .28);
    }

    .al-qr-box {
        background: #fff;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 8px 32px rgba(0, 0, 0, .3);
        min-width: 148px;
    }

    .al-qr-box img {
        width: 108px;
        height: 108px;
        display: block;
        margin: 0 auto 10px;
        border-radius: 6px;
    }

    .al-qr-box-label {
        font-size: 11px;
        font-weight: 700;
        color: #1e3a8a;
        letter-spacing: .3px;
    }

    .al-qr-box-sub {
        font-size: 10px;
        color: #64748b;
        margin-top: 3px;
    }

    /* After success section */
    .al-after-success {
        margin-top: 48px;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(5, 150, 105, 0.06));
        border: 1px solid rgba(16, 185, 129, 0.25);
        border-radius: 24px;
        padding: 36px 32px;
    }

    .al-after-success h3 {
        font-size: 20px;
        font-weight: 800;
        color: var(--text, #f0f6ff);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .al-after-success>p {
        font-size: 14px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.65));
        margin-bottom: 28px;
        max-width: 560px;
    }

    .al-after-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }

    .al-after-card {
        background: var(--glass, rgba(255, 255, 255, 0.055));
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
        border-radius: 16px;
        padding: 20px 18px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .al-after-card i {
        font-size: 22px;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .al-after-card strong {
        font-size: 14px;
        font-weight: 700;
        color: var(--text, #f0f6ff);
        display: block;
        margin-bottom: 4px;
    }

    .al-after-card span {
        font-size: 12px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.6));
        line-height: 1.5;
    }

    @media (max-width: 768px) {
        .al-qr-section {
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .al-qr-box {
            display: none;
        }
    }

    @media (max-width: 640px) {
        .al-guide {
            padding: 30px 0 30px;
        }

        .al-qr-section {
            padding: 28px 20px;
        }

        .al-after-success {
            padding: 24px 18px;
        }
    }

    /* Top banner slot — always provides header clearance */
    .al-banner-wrap { margin-top: 64px; }
    @media (max-width: 640px) { .al-banner-wrap { margin-top: 56px; } }

    /* Wrapper */
    .al-page {
        background: var(--bg, #060d1f);
        padding: 40px 0 15px;
    }

    .al-form-wrap {
        max-width: 740px;
        margin: 0 auto;
    }

    /* Tips */
    .al-tips {
        background: var(--glass, rgba(255, 255, 255, 0.055));
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
        border-radius: 16px;
        padding: 20px 22px;
        margin-bottom: 28px;
        backdrop-filter: blur(16px);
    }

    .al-tips-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--text, #f0f6ff);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .al-tip {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 13px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.66));
        margin-bottom: 8px;
    }

    .al-tip:last-child {
        margin-bottom: 0;
    }

    .al-tip i {
        color: #3b82f6;
        margin-top: 1px;
        flex-shrink: 0;
    }

    /* Error banner */
    .al-error {
        display: none;
        align-items: center;
        gap: 10px;
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.35);
        border-radius: 12px;
        padding: 14px 18px;
        color: #f87171;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 20px;
    }

    .al-error.show {
        display: flex;
    }

    /* Cards */
    .al-card {
        background: var(--glass, rgba(255, 255, 255, 0.055));
        border-radius: 20px;
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
        box-shadow: 0 2px 12px rgba(0, 0, 0, .3);
        margin-bottom: 20px;
        overflow: hidden;
        backdrop-filter: blur(20px);
        transition: box-shadow .2s, border-color .2s;
    }

    .al-card:focus-within {
        box-shadow: 0 4px 24px rgba(37, 99, 235, .2);
        border-color: rgba(59, 130, 246, 0.5);
    }

    .al-card-head {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px 22px;
        border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
    }

    .al-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        flex-shrink: 0;
    }

    .al-card-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--text, #f0f6ff);
        margin: 0;
    }

    .al-card-sub {
        font-size: 12px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.66));
        margin: 2px 0 0;
    }

    .al-card-body {
        padding: 22px;
    }

    /* Fields */
    .al-f {
        margin-bottom: 18px;
    }

    .al-f:last-child {
        margin-bottom: 0;
    }

    .al-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    label.al-lbl {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary, rgba(240, 246, 255, 0.66));
        margin-bottom: 7px;
    }

    label.al-lbl .req {
        color: #ef4444;
        margin-left: 2px;
    }

    .al-input,
    .al-sel,
    .al-txt {
        width: 100%;
        padding: 11px 14px;
        border: 1.5px solid var(--border-hover, rgba(255, 255, 255, 0.18));
        border-radius: 11px;
        font-family: inherit;
        font-size: 14px;
        color: var(--text, #f0f6ff);
        background: var(--glass, rgba(255, 255, 255, 0.055));
        transition: border-color .18s, box-shadow .18s, background .18s;
        outline: none;
        box-sizing: border-box;
        backdrop-filter: blur(8px);
    }

    .al-input:focus,
    .al-sel:focus,
    .al-txt:focus {
        border-color: #3b82f6;
        background: var(--glass-hover, rgba(255, 255, 255, 0.10));
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
    }

    .al-input::placeholder,
    .al-txt::placeholder {
        color: var(--text-muted, rgba(240, 246, 255, 0.36));
    }

    .al-sel {
        appearance: none;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 40px;
    }

    .al-sel option {
        background: #0f172a;
        color: #f0f6ff;
    }

    .al-txt {
        resize: vertical;
        min-height: 96px;
    }

    .al-char {
        font-size: 11px;
        color: var(--text-muted, rgba(240, 246, 255, 0.36));
        text-align: right;
        margin-top: 4px;
    }

    /* GPS row */
    .al-gps-row {
        display: flex;
        gap: 10px;
    }

    .al-gps-row .al-input {
        flex: 1;
    }

    .al-gps-btn {
        flex-shrink: 0;
        height: 44px;
        padding: 0 14px;
        background: var(--glass, rgba(255, 255, 255, 0.055));
        border: 1.5px solid var(--border-hover, rgba(255, 255, 255, 0.18));
        border-radius: 11px;
        color: #60a5fa;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all .2s;
        white-space: nowrap;
    }

    .al-gps-btn:hover {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    .al-gps-btn:disabled {
        opacity: .6;
        cursor: default;
    }

    .al-gps-status {
        font-size: 12px;
        margin-top: 6px;
        display: none;
    }

    /* 24x7 toggle */
    .al-24-toggle {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: var(--glass, rgba(255, 255, 255, 0.055));
        border: 1.5px solid var(--border-hover, rgba(255, 255, 255, 0.18));
        border-radius: 11px;
        cursor: pointer;
        margin-bottom: 14px;
        user-select: none;
        transition: border-color .2s;
    }

    .al-24-toggle:hover {
        border-color: #a855f7;
    }

    .al-24-toggle input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #9333ea;
        cursor: pointer;
        flex-shrink: 0;
    }

    .al-24-lbl strong {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--text, #f0f6ff);
    }

    .al-24-lbl small {
        font-size: 12px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.66));
    }

    /* Photo upload */
    .al-drop {
        border: 2px dashed var(--border-hover, rgba(255, 255, 255, 0.18));
        border-radius: 14px;
        padding: 32px 20px;
        text-align: center;
        background: var(--glass, rgba(255, 255, 255, 0.055));
        cursor: pointer;
        position: relative;
        transition: border-color .2s, background .2s;
    }

    .al-drop:hover,
    .al-drop.over {
        border-color: #3b82f6;
        background: var(--glass-hover, rgba(255, 255, 255, 0.10));
    }

    .al-drop input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }

    .al-drop-icon {
        width: 54px;
        height: 54px;
        background: rgba(37, 99, 235, 0.18);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px;
        color: #60a5fa;
        font-size: 22px;
    }

    .al-drop h4 {
        font-size: 15px;
        font-weight: 700;
        color: var(--text, #f0f6ff);
        margin-bottom: 6px;
    }

    .al-drop p {
        font-size: 13px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.66));
        margin: 0;
    }

    .al-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 10px;
        margin-top: 18px;
    }

    .al-photo-thumb {
        position: relative;
        aspect-ratio: 1;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
        transition: border-color .2s;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
    }

    .al-photo-thumb.cover {
        border-color: #2563eb;
    }

    .al-photo-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .al-cover-badge {
        position: absolute;
        top: 5px;
        left: 5px;
        background: #2563eb;
        color: #fff;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .5px;
        padding: 2px 7px;
        border-radius: 100px;
    }

    .al-rm {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 22px;
        height: 22px;
        background: rgba(0, 0, 0, .55);
        color: #fff;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        transition: background .18s;
    }

    .al-rm:hover {
        background: #ef4444;
    }

    .al-photo-hint {
        font-size: 12px;
        color: var(--text-muted, rgba(240, 246, 255, 0.36));
        text-align: center;
        margin-top: 10px;
    }

    /* Submit */
    .al-sub-card {
        background: var(--glass, rgba(255, 255, 255, 0.055));
        border-radius: 20px;
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
        padding: 28px 22px;
        text-align: center;
        backdrop-filter: blur(20px);
    }

    .al-sub-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: linear-gradient(135deg, #2563eb, #0ea5e9);
        color: #fff;
        border: none;
        border-radius: 13px;
        padding: 15px 48px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        min-width: 220px;
        transition: transform .2s, box-shadow .2s;
    }

    .al-sub-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(37, 99, 235, .32);
    }

    .al-sub-btn:disabled {
        opacity: .6;
        cursor: default;
        transform: none;
        box-shadow: none;
    }

    .al-sub-note {
        font-size: 13px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.66));
        margin-top: 12px;
    }

    /* Success overlay */
    .al-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .65);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        padding: 24px;
        backdrop-filter: blur(8px);
    }

    .al-overlay.show {
        display: flex;
    }

    .al-success-box {
        background: rgba(8, 16, 40, 0.96);
        border: 1px solid var(--border-hover, rgba(255, 255, 255, 0.18));
        backdrop-filter: blur(28px);
        border-radius: 24px;
        padding: 48px 36px;
        text-align: center;
        max-width: 420px;
        width: 100%;
        animation: alPop .38s cubic-bezier(.34, 1.56, .64, 1);
    }

    [data-theme="light"] .al-success-box {
        background: rgba(255, 255, 255, 0.97);
    }

    @keyframes alPop {
        from {
            transform: scale(.75);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .al-success-icon {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.18);
        color: #10b981;
        font-size: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 22px;
    }

    .al-success-box h2 {
        font-size: 24px;
        font-weight: 800;
        color: var(--text, #f0f6ff);
        margin-bottom: 10px;
    }

    .al-success-box p {
        font-size: 15px;
        color: var(--text-secondary, rgba(240, 246, 255, 0.66));
        margin-bottom: 28px;
        line-height: 1.6;
    }

    .al-success-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: center;
    }

    @media (max-width: 640px) {
        .al-row {
            grid-template-columns: 1fr;
        }

        .al-card-body {
            padding: 18px 16px;
        }

        .al-drop {
            padding: 24px 16px;
        }

        .al-sub-card {
            padding: 22px 16px;
        }
    }

    /* ===== QR UPSELL POPUP ===== */
    .al-qr-popup {
        background: linear-gradient(145deg, #07101f 0%, #0c1e3e 100%);
        border: 1px solid rgba(37, 99, 235, 0.3);
        border-radius: 28px;
        width: 92%;
        max-width: 760px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 32px 80px rgba(0, 0, 0, 0.65);
        animation: alSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .al-qr-popup::before {
        content: '';
        position: absolute;
        top: -100px;
        right: -100px;
        width: 360px;
        height: 360px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.18), transparent 65%);
        pointer-events: none;
    }

    .al-qr-popup-close {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.15);
        background: rgba(255, 255, 255, 0.06);
        color: rgba(255, 255, 255, 0.6);
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s, color 0.2s;
        z-index: 5;
    }

    .al-qr-popup-close:hover {
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
    }

    .al-qr-popup-inner {
        display: grid;
        grid-template-columns: 200px 1fr;
    }

    .al-qr-popup-visual {
        background: linear-gradient(160deg, #1d4ed8 0%, #0284c7 100%);
        padding: 44px 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 14px;
        border-radius: 28px 0 0 28px;
    }

    .al-qr-popup-icon-wrap {
        width: 108px;
        height: 108px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 52px;
        color: #fff;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
    }

    .al-qr-popup-badge {
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1.2px;
        padding: 4px 12px;
        border-radius: 100px;
    }

    .al-qr-popup-price {
        font-size: 42px;
        font-weight: 900;
        color: #fff;
        line-height: 1;
    }

    .al-qr-popup-price-sub {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.72);
        text-align: center;
    }

    .al-qr-popup-content {
        padding: 36px 36px 30px;
    }

    .al-qr-popup-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #10b981;
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.28);
        padding: 5px 12px;
        border-radius: 100px;
        margin-bottom: 14px;
    }

    .al-qr-popup-title {
        font-size: clamp(19px, 2.5vw, 25px);
        font-weight: 900;
        color: #f0f6ff;
        margin-bottom: 10px;
        line-height: 1.3;
    }

    .al-qr-popup-desc {
        font-size: 13.5px;
        color: rgba(240, 246, 255, 0.62);
        line-height: 1.6;
        margin-bottom: 18px;
        max-width: 380px;
    }

    .al-qr-popup-features {
        list-style: none;
        padding: 0;
        margin: 0 0 24px;
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .al-qr-popup-features li {
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 13px;
        color: rgba(240, 246, 255, 0.83);
    }

    .al-qr-popup-features li i {
        font-size: 11px;
        color: #60a5fa;
        width: 20px;
        height: 20px;
        background: rgba(59, 130, 246, 0.14);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .al-qr-popup-actions {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .al-qr-buy-btn {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        background: linear-gradient(135deg, #1d4ed8 0%, #0ea5e9 100%);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        padding: 13px 26px;
        border-radius: 14px;
        border: none;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 22px rgba(37, 99, 235, 0.55);
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .al-qr-buy-btn::after {
        content: '';
        position: absolute;
        top: 0;
        left: -80%;
        width: 55%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
        animation: alShimmerSweep 2s ease infinite;
    }

    .al-qr-buy-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(37, 99, 235, 0.7);
    }

    .al-qr-buy-btn:active {
        transform: scale(0.98);
    }

    .al-qr-buy-btn:disabled {
        opacity: 0.6;
        pointer-events: none;
    }

    .al-qr-skip-btn {
        background: none;
        border: none;
        color: rgba(240, 246, 255, 0.42);
        font-size: 13.5px;
        cursor: pointer;
        padding: 6px 4px;
        transition: color 0.2s;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .al-qr-skip-btn:hover {
        color: rgba(240, 246, 255, 0.75);
    }

    .al-qr-popup-secure {
        margin-top: 14px;
        font-size: 11px;
        color: rgba(240, 246, 255, 0.3);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    @media (max-width: 620px) {
        .al-qr-popup {
            border-radius: 20px;
        }

        .al-qr-popup-inner {
            grid-template-columns: 1fr;
        }

        .al-qr-popup-visual {
            padding: 24px 20px;
            flex-direction: row;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            border-radius: 20px 20px 0 0;
        }

        .al-qr-popup-icon-wrap {
            width: 64px;
            height: 64px;
            font-size: 30px;
        }

        .al-qr-popup-price {
            font-size: 28px;
        }

        .al-qr-popup-price-sub,
        .al-qr-popup-badge {
            display: none;
        }

        .al-qr-popup-content {
            padding: 22px 20px 20px;
        }
    }

    /* ===== PROGRESSIVE FORM REVEAL ===== */
    .al-form-step-hidden {
        display: none;
    }

    @keyframes alSlideIn {
        from {
            opacity: 0;
            transform: translateY(18px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .al-reveal {
        animation: alSlideIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .al-continue-wrap {
        margin-top: 20px;
    }

    .al-continue-btn {
        width: 100%;
        padding: 15px 24px;
        border-radius: 14px;
        border: none;
        background: linear-gradient(135deg, #1d4ed8 0%, #0ea5e9 100%);
        color: #fff;
        font-size: 16px;
        font-weight: 700;
        cursor: default;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        opacity: 0.32;
        pointer-events: none;
        transition: opacity 0.3s, transform 0.15s;
        position: relative;
        overflow: hidden;
    }

    .al-continue-btn.active {
        opacity: 1;
        pointer-events: auto;
        cursor: pointer;
        animation: alPulseGlow 2s ease infinite;
    }

    .al-continue-btn.active::after {
        content: '';
        position: absolute;
        top: 0;
        left: -80%;
        width: 55%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        animation: alShimmerSweep 2.2s ease infinite;
    }

    .al-continue-btn:active {
        transform: scale(0.98);
    }

    @keyframes alPulseGlow {

        0%,
        100% {
            box-shadow: 0 4px 22px rgba(37, 99, 235, 0.5);
        }

        50% {
            box-shadow: 0 4px 40px rgba(37, 99, 235, 0.85), 0 0 0 5px rgba(37, 99, 235, 0.18);
        }
    }

    @keyframes alShimmerSweep {
        0% {
            left: -80%;
        }

        100% {
            left: 160%;
        }
    }

    /* ===== CATEGORY PILLS ===== */
    .al-cat-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .al-cat-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 10px 18px;
        border-radius: 99px;
        border: 1.5px solid var(--border-hover, rgba(255,255,255,0.18));
        background: var(--glass, rgba(255,255,255,0.055));
        color: var(--text-muted, rgba(240,246,255,0.55));
        font-size: 13px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: border-color .18s, background .18s, color .18s, box-shadow .18s;
        backdrop-filter: blur(8px);
    }

    .al-cat-pill i { font-size: 13px; }

    .al-cat-pill:hover {
        border-color: #3b82f6;
        color: #93c5fd;
        background: rgba(59,130,246,0.09);
    }

    .al-cat-pill.active {
        background: linear-gradient(135deg, #2563eb, #10b981);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 4px 14px rgba(37,99,235,0.35);
    }

    @media (max-width: 480px) {
        .al-cat-pill { padding: 9px 14px; font-size: 12px; }
    }
</style>

<div class="al-banner-wrap">
<?php render_website_banner('add_listing', 'top'); ?>
</div>

<!-- FORM -->
<div class="al-page" id="listingForm">
    <div class="container">
        <div class="al-form-wrap">

            <!-- Error -->
            <div class="al-error" id="alError">
                <i class="fas fa-exclamation-circle" style="flex-shrink:0;"></i>
                <span id="alErrorText"></span>
            </div>

            <form id="alForm" novalidate>

                <!-- 1. Basic Info -->
                <div class="al-card">
                    <div class="al-card-head">
                        <div class="al-card-icon" style="background:rgba(37,99,235,0.18);color:#60a5fa;"><i
                                class="fas fa-clinic-medical"></i></div>
                        <div>
                            <div class="al-card-title">Basic Information</div>
                            <div class="al-card-sub">About your facility</div>
                        </div>
                    </div>
                    <div class="al-card-body">
                        <div class="al-f">
                            <label class="al-lbl">Category <span class="req">*</span></label>
                            <?php
                            $alCatIconMap = [
                                'Hospital'      => 'fa-hospital',
                                'Clinic'        => 'fa-stethoscope',
                                'Labs'          => 'fa-flask',
                                'Medical store' => 'fa-pills',
                                'Ambulance'     => 'fa-truck-medical',
                                'Blood bank'    => 'fa-droplet',
                            ];
                            ?>
                            <div class="al-cat-pills" id="alCatPills">
                                <?php foreach ($categories as $cat): ?>
                                <button type="button" class="al-cat-pill" data-value="<?= intval($cat['id']) ?>">
                                    <i class="fas <?= $alCatIconMap[$cat['name']] ?? 'fa-hospital' ?>"></i>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="alCat" name="category_id">
                        </div>
                        <div class="al-continue-wrap" id="alContinueWrap">
                            <button type="button" class="al-continue-btn" id="alContinueBtn" onclick="alContinueForm()">
                                <i class="fas fa-arrow-right"></i> Continue
                            </button>
                        </div>
                        <div id="alBasicFields" class="al-form-step-hidden">
                            <div class="al-f" style="margin-top:20px;">
                                <label class="al-lbl" for="alName">Facility / Business Name <span
                                        class="req">*</span></label>
                                <input class="al-input" type="text" id="alName" name="name"
                                    placeholder="e.g. Apollo Multi-Speciality Hospital" required maxlength="200" />
                            </div>
                            <div class="al-f">
                                <label class="al-lbl" for="alDesc">Description <span class="req">*</span></label>
                                <textarea class="al-txt" id="alDesc" name="description"
                                    placeholder="Describe your services, specialties, available facilities, doctors, equipment..."
                                    required rows="4" maxlength="2000"></textarea>
                                <div class="al-char"><span id="alDescCount">0</span>/2000</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Location -->
                <div class="al-card al-form-step-hidden" id="alCardLocation">
                    <div class="al-card-head">
                        <div class="al-card-icon" style="background:rgba(16,185,129,0.18);color:#34d399;"><i
                                class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <div class="al-card-title">Location</div>
                            <div class="al-card-sub">Where are you located?</div>
                        </div>
                    </div>
                    <div class="al-card-body">
                        <div class="al-f">
                            <label class="al-lbl" for="alAddr">Full Address <span class="req">*</span></label>
                            <input class="al-input" type="text" id="alAddr" name="address"
                                placeholder="Building, Street, Area, Landmark" required maxlength="400" />
                        </div>
                        <div class="al-f al-row">
                            <div>
                                <label class="al-lbl" for="alCity">City <span class="req">*</span></label>
                                <input class="al-input" type="text" id="alCity" name="city" placeholder="e.g. Mumbai"
                                    required maxlength="100" />
                            </div>
                            <div>
                                <label class="al-lbl" for="alState">State</label>
                                <input class="al-input" type="text" id="alState" name="state"
                                    placeholder="e.g. Maharashtra" maxlength="100" />
                            </div>
                        </div>
                        <div class="al-f">
                            <label class="al-lbl">GPS Coordinates <span style="font-weight:400;color:#64748b;">(optional
                                    — improves navigation)</span></label>
                            <div class="al-gps-row">
                                <input class="al-input" type="text" id="alLat" name="latitude" placeholder="Latitude"
                                    readonly />
                                <input class="al-input" type="text" id="alLng" name="longitude" placeholder="Longitude"
                                    readonly />
                                <button type="button" class="al-gps-btn" id="alGpsBtn" onclick="detectGPS()">
                                    <i class="fas fa-location-crosshairs"></i> Detect
                                </button>
                            </div>
                            <div class="al-gps-status" id="alGpsStatus"></div>
                        </div>
                    </div>
                </div>

                <!-- 3. Contact -->
                <div class="al-card al-form-step-hidden" id="alCardContact">
                    <div class="al-card-head">
                        <div class="al-card-icon" style="background:rgba(234,88,12,0.18);color:#fb923c;"><i
                                class="fas fa-phone-alt"></i></div>
                        <div>
                            <div class="al-card-title">Contact Details</div>
                            <div class="al-card-sub">How patients can reach you</div>
                        </div>
                    </div>
                    <div class="al-card-body">
                        <div class="al-f al-row">
                            <div>
                                <label class="al-lbl" for="alMobile">Phone Number <span class="req">*</span></label>
                                <input class="al-input" type="tel" id="alMobile" name="mobile"
                                    placeholder="+91 98765 43210" required maxlength="15" />
                            </div>
                            <div>
                                <label class="al-lbl" for="alWA">WhatsApp Number</label>
                                <input class="al-input" type="tel" id="alWA" name="whatsapp"
                                    placeholder="Same as phone?" maxlength="15" />
                            </div>
                        </div>
                        <div class="al-f">
                            <label class="al-lbl" for="alEmail">Email Address</label>
                            <input class="al-input" type="email" id="alEmail" name="email"
                                placeholder="info@yourhospital.com" maxlength="200" />
                        </div>
                    </div>
                </div>

                <!-- 4. Hours -->
                <div class="al-card al-form-step-hidden" id="alCardHours">
                    <div class="al-card-head">
                        <div class="al-card-icon" style="background:rgba(147,51,234,0.18);color:#c084fc;"><i
                                class="fas fa-clock"></i></div>
                        <div>
                            <div class="al-card-title">Business Hours</div>
                            <div class="al-card-sub">When are you open?</div>
                        </div>
                    </div>
                    <div class="al-card-body">
                        <label class="al-24-toggle" for="al24">
                            <input type="checkbox" id="al24" name="is_24x7" value="1" onchange="toggle24(this)" />
                            <div class="al-24-lbl">
                                <strong><i class="fas fa-infinity" style="color:#9333ea;margin-right:6px;"></i>Open 24
                                    Hours / 7 Days</strong>
                                <small>Tick for 24x7 hospitals, emergency services, pharmacies</small>
                            </div>
                        </label>
                        <div class="al-row" id="alHoursRow">
                            <div>
                                <label class="al-lbl" for="alOpen">Opening Time</label>
                                <input class="al-input" type="time" id="alOpen" name="open_time" value="09:00" />
                            </div>
                            <div>
                                <label class="al-lbl" for="alClose">Closing Time</label>
                                <input class="al-input" type="time" id="alClose" name="close_time" value="18:00" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Photos -->
                <div class="al-card al-form-step-hidden" id="alCardPhotos">
                    <div class="al-card-head">
                        <div class="al-card-icon" style="background:rgba(225,29,72,0.18);color:#fb7185;"><i
                                class="fas fa-camera"></i></div>
                        <div>
                            <div class="al-card-title">Photos <span class="req">*</span></div>
                            <div class="al-card-sub">Up to 5 photos — first is cover image</div>
                        </div>
                    </div>
                    <div class="al-card-body">
                        <div class="al-drop" id="alDrop" ondragover="event.preventDefault();this.classList.add('over')"
                            ondragleave="this.classList.remove('over')" ondrop="dropPhotos(event)">
                            <input type="file" id="alFileInput" accept="image/*" multiple
                                onchange="pickPhotos(this.files)" />
                            <div class="al-drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <h4>Drag & drop photos here</h4>
                            <p>Or click to browse &nbsp;·&nbsp; JPG, PNG, WebP &nbsp;·&nbsp; Max 5MB each &nbsp;·&nbsp;
                                Up to 5 photos</p>
                        </div>
                        <div class="al-photo-grid" id="alGrid"></div>
                        <div class="al-photo-hint" id="alHint" style="display:none;"></div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="al-sub-card al-form-step-hidden" id="alCardSubmit">
                    <button type="submit" class="al-sub-btn" id="alSubBtn">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <p class="al-sub-note">
                        <i class="fas fa-shield-alt" style="color:#059669;margin-right:4px;"></i>
                        Free listing &mdash; reviewed and published within 24 hours
                    </p>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- ===== STEP GUIDE ===== -->
<section class="al-guide">
    <div class="container">

        <div class="al-guide-heading">
            <h2>How to Add Your Listing</h2>
            <p>Get your medical facility live on HealthDial in four simple steps — completely free.</p>
        </div>

        <!-- Steps -->
        <div class="al-steps">
            <div class="al-step">
                <div class="al-step-illus" style="background:rgba(37,99,235,0.12);color:#3b82f6;">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="al-step-text">
                    <div class="al-step-label">Step 1</div>
                    <h3>Fill the Form</h3>
                    <p>Enter your facility name, category, address, contact details and business hours above.</p>
                </div>
            </div>
            <div class="al-step">
                <div class="al-step-illus" style="background:rgba(16,185,129,0.12);color:#10b981;">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="al-step-text">
                    <div class="al-step-label">Step 2</div>
                    <h3>Upload Photos</h3>
                    <p>Add up to 5 clear photos of your facility — entrance, interior, equipment, signboard.</p>
                </div>
            </div>
            <div class="al-step">
                <div class="al-step-illus" style="background:rgba(245,158,11,0.12);color:#f59e0b;">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="al-step-text">
                    <div class="al-step-label">Step 3</div>
                    <h3>Submit &amp; Get Verified</h3>
                    <p>Our team reviews your listing within 24 hours and publishes it with a verified badge.</p>
                </div>
            </div>
            <div class="al-step">
                <div class="al-step-illus" style="background:rgba(124,58,237,0.12);color:#8b5cf6;">
                    <i class="fas fa-rocket"></i>
                </div>
                <div class="al-step-text">
                    <div class="al-step-label">Step 4</div>
                    <h3>Go Live &amp; Get Found</h3>
                    <p>Patients nearby start discovering your facility — via search, GPS navigation and 1-tap calls.</p>
                </div>
            </div>
        </div>

        <!-- QR Code promo -->
        <div class="al-qr-section">
            <div class="al-qr-section-text">
                <h3>Boost Reach with Your Own QR Code</h3>
                <p>After your listing goes live, order a printed QR code card for your reception desk, visiting cards or
                    clinic entrance. Patients scan it to instantly view your profile, call you, and get directions.</p>
                <ul class="al-qr-steps">
                    <li><i class="fas fa-check-circle"></i> Unique QR linked directly to your HealthDial profile</li>
                    <li><i class="fas fa-check-circle"></i> Works offline on printed cards, boards &amp; posters</li>
                    <li><i class="fas fa-check-circle"></i> Patients scan &rarr; call, get directions, read reviews</li>
                    <li><i class="fas fa-check-circle"></i> Track scans &amp; measure real-world patient footfall</li>
                </ul>
                <a href="promote.php" class="al-promote-btn">
                    <i class="fas fa-qrcode"></i> Get Your QR Code
                </a>
            </div>
            <div class="al-qr-box">
                <img src="assets/images/qr-code.png" alt="Sample QR Code" />
                <div class="al-qr-box-label">Sample QR Code</div>
                <div class="al-qr-box-sub">Your profile QR after listing</div>
            </div>
        </div>

        <!-- After successful listing -->
        <div class="al-after-success">
            <h3><i class="fas fa-check-circle" style="color:#10b981;"></i> After Your Listing Goes Live</h3>
            <p>Here's what happens automatically once our team approves and publishes your listing.</p>
            <div class="al-after-grid">
                <div class="al-after-card">
                    <i class="fas fa-search" style="background:rgba(37,99,235,0.18);color:#60a5fa;"></i>
                    <div>
                        <strong>Appear in Search</strong>
                        <span>Patients searching for your category and city will see your listing immediately.</span>
                    </div>
                </div>
                <div class="al-after-card">
                    <i class="fas fa-map-marker-alt" style="background:rgba(16,185,129,0.18);color:#34d399;"></i>
                    <div>
                        <strong>GPS Navigation</strong>
                        <span>Patients get one-tap GPS directions to your facility from the app.</span>
                    </div>
                </div>
                <div class="al-after-card">
                    <i class="fas fa-phone" style="background:rgba(234,88,12,0.18);color:#fb923c;"></i>
                    <div>
                        <strong>1-Tap Calling</strong>
                        <span>Your phone number is prominently shown for instant patient contact.</span>
                    </div>
                </div>
                <div class="al-after-card">
                    <i class="fas fa-star" style="background:rgba(245,158,11,0.18);color:#fbbf24;"></i>
                    <div>
                        <strong>Reviews &amp; Ratings</strong>
                        <span>Patients can rate and review your facility, building trust over time.</span>
                    </div>
                </div>
                <div class="al-after-card">
                    <i class="fas fa-bolt" style="background:rgba(147,51,234,0.18);color:#c084fc;"></i>
                    <div>
                        <strong>Promote for Top Spot</strong>
                        <span>Optionally promote your listing to appear at the very top of search results.</span>
                    </div>
                </div>
                <div class="al-after-card">
                    <i class="fas fa-qrcode" style="background:rgba(6,182,212,0.18);color:#22d3ee;"></i>
                    <div>
                        <strong>QR Code Card</strong>
                        <span>Order a printed QR code to display at your facility and on visiting cards.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tips -->
        <div class="al-tips" style="margin-top:40px;margin-bottom:0;">
            <div class="al-tips-title"><i class="fas fa-lightbulb"></i> Tips for a Great Listing</div>
            <div class="al-tip"><i class="fas fa-check-circle"></i> Add clear, well-lit photos of your facility entrance
                and interior</div>
            <div class="al-tip"><i class="fas fa-check-circle"></i> Include the full address with a nearby landmark so
                patients can find you easily</div>
            <div class="al-tip"><i class="fas fa-check-circle"></i> Use the GPS button to add your exact coordinates —
                helps with map navigation</div>
            <div class="al-tip"><i class="fas fa-check-circle"></i> Write a detailed description of your services and
                specialties</div>
        </div>

    </div>
</section>

<!-- QR Code Upsell Popup -->
<div class="al-overlay" id="alQrOverlay">
    <div class="al-qr-popup">
        <button class="al-qr-popup-close" onclick="skipQr()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
        <div class="al-qr-popup-inner">
            <div class="al-qr-popup-visual">
                <div class="al-qr-popup-icon-wrap">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="al-qr-popup-badge">ONE-TIME</div>
                <div class="al-qr-popup-price">₹200</div>
                <div class="al-qr-popup-price-sub">Printed card delivery</div>
            </div>
            <div class="al-qr-popup-content">
                <div class="al-qr-popup-tag">
                    <i class="fas fa-check-circle"></i> Your Listing is Live!
                </div>
                <h2 class="al-qr-popup-title">Boost Walk-ins with<br>Your Own QR Code</h2>
                <p class="al-qr-popup-desc">Patients scan it to instantly find, call, and navigate to your facility.
                    Place it at your reception, entrance, or visiting cards.</p>
                <ul class="al-qr-popup-features">
                    <li><i class="fas fa-check"></i> Unique QR linked directly to your HealthDial profile</li>
                    <li><i class="fas fa-check"></i> Works offline on printed cards, boards &amp; posters</li>
                    <li><i class="fas fa-check"></i> Patients scan → call, directions, reviews instantly</li>
                    <li><i class="fas fa-check"></i> Track real-world patient footfall &amp; scan count</li>
                </ul>
                <div class="al-qr-popup-actions">
                    <button class="al-qr-buy-btn" id="alQrBuyBtn" onclick="buyQrCode()">
                        <i class="fas fa-qrcode"></i> Buy QR Code — ₹200
                    </button>
                    <button class="al-qr-skip-btn" onclick="skipQr()">No, Thanks</button>
                </div>
                <p class="al-qr-popup-secure">
                    <i class="fas fa-shield-alt"></i> Secure payment via Razorpay · No hidden charges
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Success Overlay -->
<div class="al-overlay" id="alSuccessOverlay">
    <div class="al-success-box">
        <div class="al-success-icon" id="alSuccessIcon"><i class="fas fa-check"></i></div>
        <h2 id="alSuccessTitle">Listing Added Successfully!</h2>
        <p id="alSuccessMsg">Your listing is now live on HealthDial. Patients across India can find, call, and navigate
            to your facility.</p>
        <div class="al-success-actions">
            <a id="alViewListingBtn" href="listing-detail.php?id=0" class="al-sub-btn" style="text-decoration:none;">
                <i class="fas fa-eye"></i> View My Listing
            </a>
            <a href="add-listing.php" onclick="resetForm(event)"
                style="font-size:14px;color:#2563eb;font-weight:600;text-decoration:none;">
                <i class="fas fa-plus"></i> Add Another Listing
            </a>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    /* ===== Add Listing JS ===== */
    let photos = []; // [{ file, dataUrl }]
    let _listingId = 0; // stored after successful submission

    // ---- Category pill selection ----
    document.querySelectorAll('.al-cat-pill').forEach(function (pill) {
        pill.addEventListener('click', function () {
            document.querySelectorAll('.al-cat-pill').forEach(function (p) { p.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('alCat').value = this.dataset.value;
            document.getElementById('alContinueBtn').classList.add('active');
        });
    });

    function alContinueForm() {
        const fields = document.getElementById('alBasicFields');
        fields.style.display = 'block';
        void fields.offsetHeight;
        fields.classList.add('al-reveal');
        document.getElementById('alContinueWrap').style.display = 'none';

        ['alCardLocation', 'alCardContact', 'alCardHours', 'alCardPhotos', 'alCardSubmit'].forEach((id, i) => {
            setTimeout(() => {
                const el = document.getElementById(id);
                el.style.display = 'block';
                void el.offsetHeight;
                el.classList.add('al-reveal');
            }, 120 + i * 140);
        });
    }
    // ----------------------------------

    // ---- QR popup flow ----
    function showQrPopup(listingId) {
        _listingId = listingId;
        document.getElementById('alViewListingBtn').href = 'listing-detail.php?id=' + listingId;
        document.getElementById('alQrOverlay').classList.add('show');
    }

    function skipQr() {
        document.getElementById('alQrOverlay').classList.remove('show');
        showSuccessPopup(false);
    }

    function showSuccessPopup(qrPaid) {
        if (qrPaid) {
            document.getElementById('alSuccessIcon').style.background = 'rgba(37,99,235,0.18)';
            document.getElementById('alSuccessIcon').style.color = '#60a5fa';
            document.getElementById('alSuccessTitle').textContent = 'QR Code Generated!';
            document.getElementById('alSuccessMsg').textContent =
                'Your listing is live and your QR code has been generated successfully. It will be printed and delivered to your facility.';
        }
        document.getElementById('alSuccessOverlay').classList.add('show');
        if (qrPaid && _listingId) {
            setTimeout(() => {
                window.location.href = 'listing-detail.php?id=' + _listingId + '&qr=generated';
            }, 2800);
        }
    }

    async function buyQrCode() {
        const btn = document.getElementById('alQrBuyBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating order…';

        try {
            const res = await fetch('qr_create_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    listing_id: _listingId
                })
            });
            const data = await res.json();

            if (!data.success) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-qrcode"></i> Buy QR Code — ₹200';
                alert(data.message || 'Could not create order. Please try again.');
                return;
            }

            const rzp = new Razorpay({
                key: data.razorpay_key_id,
                amount: data.amount_paise,
                currency: 'INR',
                name: 'HealthDial',
                description: 'QR Code for your listing',
                order_id: data.razorpay_order_id,
                theme: {
                    color: '#2563eb'
                },
                handler: async function (response) {
                    try {
                        const vRes = await fetch('qr_verify_payment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                listing_id: _listingId,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature
                            })
                        });
                        const vData = await vRes.json();
                        document.getElementById('alQrOverlay').classList.remove('show');
                        showSuccessPopup(vData.success);
                    } catch {
                        document.getElementById('alQrOverlay').classList.remove('show');
                        showSuccessPopup(false);
                    }
                },
                modal: {
                    ondismiss: function () {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-qrcode"></i> Buy QR Code — ₹200';
                    }
                }
            });
            rzp.open();
        } catch {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-qrcode"></i> Buy QR Code — ₹200';
            alert('Network error. Please try again.');
        }
    }
    // -----------------------

    // Description counter
    document.getElementById('alDesc').addEventListener('input', function () {
        document.getElementById('alDescCount').textContent = this.value.length;
    });

    // 24x7 toggle
    function toggle24(cb) {
        document.getElementById('alHoursRow').style.display = cb.checked ? 'none' : 'grid';
    }

    // GPS
    function detectGPS() {
        const btn = document.getElementById('alGpsBtn');
        const status = document.getElementById('alGpsStatus');
        if (!navigator.geolocation) {
            setGpsStatus('Geolocation not supported by your browser.', false);
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting…';
        setGpsStatus('<i class="fas fa-circle-notch fa-spin"></i> Detecting your location…', null);

        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('alLat').value = pos.coords.latitude.toFixed(6);
                document.getElementById('alLng').value = pos.coords.longitude.toFixed(6);
                btn.innerHTML = '<i class="fas fa-check"></i> Detected';
                btn.style.cssText = 'background:#dcfce7;color:#059669;border-color:#86efac;';
                btn.disabled = false;
                setGpsStatus('<i class="fas fa-check-circle"></i> Coordinates set. You can also type them manually.',
                    true);
            },
            () => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Retry';
                // Allow manual entry
                ['alLat', 'alLng'].forEach(id => {
                    const el = document.getElementById(id);
                    el.removeAttribute('readonly');
                    el.style.background = '';
                });
                setGpsStatus(
                    '<i class="fas fa-exclamation-circle"></i> Could not detect. Please enter coordinates manually.',
                    false);
            }, {
            enableHighAccuracy: true,
            timeout: 12000
        }
        );
    }

    function setGpsStatus(html, ok) {
        const el = document.getElementById('alGpsStatus');
        el.style.display = 'block';
        el.innerHTML = html;
        el.style.color = ok === true ? '#059669' : ok === false ? '#ef4444' : '#64748b';
    }

    // Photo handling
    function pickPhotos(files) {
        addPhotos(Array.from(files));
    }

    function dropPhotos(e) {
        e.preventDefault();
        document.getElementById('alDrop').classList.remove('over');
        addPhotos(Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')));
    }

    function addPhotos(files) {
        const slots = 5 - photos.length;
        if (slots <= 0) {
            showError('Maximum 5 photos allowed.');
            return;
        }
        files.slice(0, slots).forEach(file => {
            if (file.size > 5 * 1024 * 1024) {
                showError('"' + file.name + '" exceeds 5MB limit.');
                return;
            }
            const reader = new FileReader();
            reader.onload = e => {
                photos.push({
                    file,
                    dataUrl: e.target.result
                });
                renderGrid();
            };
            reader.readAsDataURL(file);
        });
    }

    function renderGrid() {
        const grid = document.getElementById('alGrid');
        const hint = document.getElementById('alHint');
        grid.innerHTML = photos.map((p, i) => `
        <div class="al-photo-thumb ${i === 0 ? 'cover' : ''}" onclick="setCover(${i})" title="${i === 0 ? 'Cover photo' : 'Set as cover'}">
            <img src="${p.dataUrl}" alt="" />
            ${i === 0 ? '<div class="al-cover-badge">COVER</div>' : ''}
            <button class="al-rm" type="button" onclick="event.stopPropagation();rmPhoto(${i})" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </div>`).join('');
        if (photos.length) {
            hint.style.display = 'block';
            hint.innerHTML = '<i class="fas fa-images" style="color:#2563eb;margin-right:5px;"></i>' +
                photos.length + ' photo' + (photos.length > 1 ? 's' : '') + ' selected' +
                (photos.length > 1 ? ' &nbsp;·&nbsp; click any photo to set it as cover' : '');
        } else {
            hint.style.display = 'none';
        }
        // Reset file input so the same file can be re-selected if removed
        document.getElementById('alFileInput').value = '';
    }

    function setCover(i) {
        if (i === 0) return;
        photos.unshift(photos.splice(i, 1)[0]);
        renderGrid();
    }

    function rmPhoto(i) {
        photos.splice(i, 1);
        renderGrid();
    }

    // Form submit
    document.getElementById('alForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        hideError();

        if (photos.length === 0) {
            showError('Please add at least one photo of your facility.');
            document.getElementById('alDrop').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            return;
        }

        const btn = document.getElementById('alSubBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';


        try {
            const city = document.getElementById('alCity').value.trim();
            const state = document.getElementById('alState').value.trim();
            const address = document.getElementById('alAddr').value.trim();
            const is24 = document.getElementById('al24').checked;

            const payload = {
                category_id: document.getElementById('alCat').value,
                name: document.getElementById('alName').value.trim(),
                description: document.getElementById('alDesc').value.trim(),
                address,
                city,
                state,
                latitude: document.getElementById('alLat').value || '0',
                longitude: document.getElementById('alLng').value || '0',
                mobile: document.getElementById('alMobile').value.trim(),
                whatsapp: document.getElementById('alWA').value.trim(),
                email: document.getElementById('alEmail').value.trim(),
                open_time: is24 ? '00:00:00' : document.getElementById('alOpen').value,
                close_time: is24 ? '00:00:00' : document.getElementById('alClose').value,
                is_24x7: is24 ? '1' : '0',
                images: photos.map((p, i) => ({
                    data: p.dataUrl,
                    name: p.file.name,
                    is_primary: i === 0 ? '1' : '0'
                }))
            };

            const res = await fetch('add-listing-submit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.success) {
                showQrPopup(data.data.listing_id);
            } else {
                showError(data.message || 'Submission failed. Please try again.');
                btn.disabled = false;
                btn.innerHTML = 'Next <i class="fas fa-arrow-right"></i>';
            }
        } catch (err) {
            showError('Network error. Please check your connection and try again.');
            btn.disabled = false;
            btn.innerHTML = 'Next <i class="fas fa-arrow-right"></i>';
        }
    });

    function showError(msg) {
        const el = document.getElementById('alError');
        document.getElementById('alErrorText').textContent = msg;
        el.classList.add('show');
        el.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    function hideError() {
        document.getElementById('alError').classList.remove('show');
    }

    function resetForm(e) {
        if (e) e.preventDefault();
        document.getElementById('alOverlay').classList.remove('show');
        document.getElementById('alForm').reset();
        photos = [];
        renderGrid();
        document.getElementById('alHoursRow').style.display = 'grid';
        document.getElementById('alDescCount').textContent = '0';
        document.getElementById('alSubBtn').disabled = false;
        document.getElementById('alSubBtn').innerHTML = 'Next <i class="fas fa-arrow-right"></i>';
        _listingId = 0;
        document.getElementById('alQrOverlay').classList.remove('show');
        document.getElementById('alSuccessOverlay').classList.remove('show');
        const si = document.getElementById('alSuccessIcon');
        si.style.background = '';
        si.style.color = '';
        document.getElementById('alSuccessTitle').textContent = 'Listing Added Successfully!';
        document.getElementById('alSuccessMsg').textContent =
            'Your listing is now live on HealthDial. Patients across India can find, call, and navigate to your facility.';

        // Reset progressive form state
        const continueWrap = document.getElementById('alContinueWrap');
        continueWrap.style.display = 'block';
        document.getElementById('alContinueBtn').classList.remove('active');
        document.getElementById('alCat').value = '';
        document.querySelectorAll('.al-cat-pill').forEach(function (p) { p.classList.remove('active'); });
        const basicFields = document.getElementById('alBasicFields');
        basicFields.style.display = 'none';
        basicFields.classList.remove('al-reveal');
        ['alCardLocation', 'alCardContact', 'alCardHours', 'alCardPhotos', 'alCardSubmit'].forEach(id => {
            const el = document.getElementById(id);
            el.style.display = 'none';
            el.classList.remove('al-reveal');
        });

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
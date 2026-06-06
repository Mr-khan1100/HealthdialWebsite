<?php
$currentPage = 'listings';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/seo.php';
require_once 'includes/listing-data.php';

$listingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$requestedSlug = isset($_GET['slug']) ? hd_slugify($_GET['slug'], '') : '';
$listing = null;
$listingMeta = null;
$images = [];
$reviews = [];
$conn = getDbConnection();

if ($listingId <= 0 && $requestedSlug !== '') {
    if ($conn) {
        $listingId = hd_get_listing_id_by_slug($conn, $requestedSlug);
    }
    // Fallback: extract trailing numeric ID from slug (e.g. "apollo-hospital-123" -> 123)
    // Works even when DB is unavailable, allowing the API fetch below to proceed.
    if ($listingId <= 0 && preg_match('/-(\d+)$/', $requestedSlug, $idMatch)) {
        $listingId = intval($idMatch[1]);
    }
}

if ($listingId > 0) {
    if ($conn) {
        $listingMeta = hd_get_listing_meta_by_id($conn, $listingId);
        if (!$listingMeta || ($listingMeta['status'] ?? '') !== 'approved') {
            $listingId = 0;
        }
    }
}

if ($listingId > 0 && $conn) {
    $dbDetail = hd_fetch_listing_detail_from_db($conn, $listingId);
    if ($dbDetail) {
        $listing = $dbDetail['listing'];
        $images = $dbDetail['images'];
        $reviews = $dbDetail['reviews'];
    }
}

if ($listingId > 0 && !$listing) {
    $apiUrl = API_BASE . 'get_listing_detail.php?id=' . $listingId;

    $data = fetch_api_data($apiUrl);

    // Validate we got proper listing data
    if (isset($data['success']) && $data['success'] && !empty($data['data']['name'])) {
        $apiData = $data['data'];

        // Map API response to our template variables
        $listing = [
            'id' => $apiData['id'] ?? $listingId,
            'category_id' => $listingMeta['category_id'] ?? null,
            'name' => $apiData['name'],
            'category_name' => $apiData['category'],
            'description' => $apiData['description'],
            'address' => $apiData['address'],
            'city' => $listingMeta['city'] ?? '',
            'slug' => $listingMeta['slug'] ?? null,
            'city_slug' => $listingMeta['city_slug'] ?? null,
            'category_slug' => $listingMeta['category_slug'] ?? null,
            'updated_at' => $listingMeta['updated_at'] ?? ($apiData['updatedAt'] ?? null),
            'avg_rating' => $apiData['rating'],
            'review_count' => $apiData['reviewCount'],
            'mobile' => $apiData['mobile'],
            'whatsapp' => $apiData['whatsapp'],
            'email' => $apiData['email'],
            'open_time' => $apiData['openTime'],
            'close_time' => $apiData['closeTime'],
            'is_24x7' => $apiData['is24x7'] ? 1 : 0,
            'latitude' => $apiData['latitude'],
            'longitude' => $apiData['longitude']
        ];

        // Setup images — normalize URL case (HealthDial vs healthdial)
        if (!empty($apiData['images']) && is_array($apiData['images'])) {
            foreach ($apiData['images'] as $img) {
                $imgUrl = $img['url'] ?? '';
                // Fix case sensitivity: normalize path
                $imgUrl = str_replace('/healthdial/', '/HealthDial/', $imgUrl);
                $imgUrl = str_replace('/healthDial/', '/HealthDial/', $imgUrl);
                // Remove double base URLs if present
                if (preg_match('/^(https?:\/\/[^\/]+\/.*?uploads\/.*?\/?)(https?:\/\/.*)$/i', $imgUrl, $m)) {
                    $imgUrl = $m[2];
                }
                if (!empty($imgUrl)) {
                    $images[] = [
                        'image_path' => $imgUrl,
                        'is_external_url' => true
                    ];
                }
            }
        }

        // Setup reviews
        if (!empty($apiData['reviews']) && is_array($apiData['reviews'])) {
            foreach ($apiData['reviews'] as $rev) {
                $reviews[] = [
                    'user_name' => $rev['user']['name'] ?? $rev['userName'] ?? $rev['guest_name'] ?? 'Anonymous',
                    'rating' => $rev['rating'],
                    'comment' => $rev['review'] ?? $rev['comment'] ?? '',
                    'created_at' => $rev['createdAt'] ?? $rev['date'] ?? date('Y-m-d')
                ];
            }
        }
    }
}

$canonicalUrl = null;
$structuredData = [];
$hoursInfo = null;
$similarListings = [];

if ($listing) {
    $hoursInfo = hd_listing_hours_info($listing);
    if ($conn) {
        $similarListings = hd_fetch_similar_listings($conn, $listing, 6);
    }

    $canonicalUrl = hd_listing_url($listing, true);

    if (hd_should_redirect_to_canonical($canonicalUrl)) {
        header('Location: ' . $canonicalUrl, true, 301);
        exit;
    }

    $cityLabel = hd_city_label($listing['city']);
    $pageTitle = $listing['name'] . ' - ' . $listing['category_name'] . ' in ' . $cityLabel;
    $pageDesc = hd_listing_meta_description($listing);
    $structuredData = [
        hd_listing_structured_data($listing, $images, $canonicalUrl),
        hd_listing_breadcrumb_structured_data($listing, $canonicalUrl),
    ];
} else {
    $pageTitle = 'Listing Not Found';
    $pageDesc = 'Listing not found on HealthDial.';
}

// Check if QR code has been unlocked (paid) for this listing
$qrPaid = false;
if ($listing && $conn) {
    $qrCheck = @$conn->prepare("SELECT id FROM listing_qr_payments WHERE listing_id = ? AND status = 'paid' LIMIT 1");
    if ($qrCheck) {
        $qrCheck->bind_param('i', $listingId);
        $qrCheck->execute();
        $qrPaid = $qrCheck->get_result()->num_rows > 0;
        $qrCheck->close();
    }
}

require_once 'includes/header.php';

if (!$listing): ?>
<section class="section" style="padding-top:140px; min-height:60vh;">
    <div class="container" style="text-align:center;">
        <div class="card" style="max-width:500px; margin:0 auto; padding:64px 32px;">
            <div class="card-icon danger" style="margin:0 auto 20px;"><?= icon('alert') ?></div>
            <h2 class="card-title">Listing Not Found</h2>
            <p class="card-text">This listing may have been removed or is pending approval.</p>
            <a href="listings.php" class="btn btn-primary" style="margin-top:24px;"><?= icon('arrowRight') ?> Browse
                Listings</a>
        </div>
    </div>
</section>
<?php else:
    $baseUrl = LISTING_IMAGE_BASE;
    $rating = round($listing['avg_rating'], 1);
    $reviewCount = intval($listing['review_count']);
    ?>

<!-- ===== DETAIL HERO ===== -->
<section class="detail-hero">
    <div class="container">
        <a href="javascript:history.back()" class="detail-back-btn"><?= icon('arrowRight') ?> Back</a>
    </div>
</section>

<!-- ===== IMAGE GALLERY ===== -->
<?php
    // Determine category gradient for placeholder
    $catLower = strtolower($listing['category_name'] ?? '');
    $catGrad = 'linear-gradient(135deg, #2b7de9, #60a5fa)';
    if (strpos($catLower, 'clinic') !== false)
        $catGrad = 'linear-gradient(135deg, #10b981, #34d399)';
    elseif (strpos($catLower, 'pharm') !== false)
        $catGrad = 'linear-gradient(135deg, #7c3aed, #a78bfa)';
    elseif (strpos($catLower, 'lab') !== false || strpos($catLower, 'pathol') !== false)
        $catGrad = 'linear-gradient(135deg, #f59e0b, #fbbf24)';
    elseif (strpos($catLower, 'dental') !== false)
        $catGrad = 'linear-gradient(135deg, #06b6d4, #22d3ee)';
    $catIcon = '🏥';
    if (strpos($catLower, 'clinic') !== false)
        $catIcon = '🩺';
    elseif (strpos($catLower, 'pharm') !== false)
        $catIcon = '💊';
    elseif (strpos($catLower, 'lab') !== false || strpos($catLower, 'pathol') !== false)
        $catIcon = '🔬';
    elseif (strpos($catLower, 'dental') !== false)
        $catIcon = '🦷';
    ?>
<section class="detail-gallery">
    <div class="container">
        <?php if (!empty($images)): ?>
        <div class="gallery-carousel" id="galleryCarousel">
            <div class="gallery-track" id="galleryTrack">
                <?php foreach ($images as $idx => $img):
                            $imgUrl = $img['is_external_url'] ? $img['image_path'] : $baseUrl . $img['image_path'];
                            ?>
                <div class="gallery-slide">
                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= htmlspecialchars($listing['name']) ?>"
                        loading="lazy"
                        onerror="this.parentElement.innerHTML='<div class=\'listing-placeholder-modern\' style=\'background:<?= $catGrad ?>;height:100%;min-height:300px;border-radius:0;\'><div class=\'placeholder-icon-ring\'><span style=\'font-size:28px\'><?= $catIcon ?></span></div><span class=\'placeholder-name\'><?= htmlspecialchars($listing['name']) ?></span><span class=\'placeholder-cat\'><?= htmlspecialchars($listing['category_name']) ?></span></div>';" />
                    <div class="watermark-overlay"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" />
                        </svg>HealthDial</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($images) > 1): ?>
            <button class="gallery-nav gallery-prev" onclick="galleryPrev()"><?= icon('arrowRight') ?></button>
            <button class="gallery-nav gallery-next" onclick="galleryNext()"><?= icon('arrowRight') ?></button>
            <div class="gallery-dots" id="galleryDots">
                <?php for ($i = 0; $i < count($images); $i++): ?>
                <span class="gallery-dot <?= $i === 0 ? 'active' : '' ?>" onclick="galleryGoTo(<?= $i ?>)"></span>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- No images — show premium category placeholder -->
        <div class="listing-placeholder-modern"
            style="background:<?= $catGrad ?>;min-height:300px;border-radius:var(--radius-lg);">
            <div class="placeholder-icon-ring"><span style="font-size:32px"><?= $catIcon ?></span></div>
            <span class="placeholder-name" style="font-size:18px;"><?= htmlspecialchars($listing['name']) ?></span>
            <span class="placeholder-cat"><?= htmlspecialchars($listing['category_name']) ?></span>
            <div class="watermark-overlay"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" />
                </svg>HealthDial</div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== DETAIL INFO ===== -->
<section class="section" style="padding-top:24px; padding-bottom:24px;">
    <div class="container">
        <div class="detail-layout">
            <!-- Main Info -->
            <div class="detail-main">
                <div class="detail-header">
                    <span class="detail-category-badge"><?= htmlspecialchars($listing['category_name']) ?></span>
                    <?php if (false): ?>
                    <span class="detail-badge-24x7">24×7</span>
                    <?php endif; ?>
                    <?php if ($listing['is_24x7']): ?>
                    <span class="detail-badge-24x7">24x7</span>
                    <?php endif; ?>
                    <?php if (!empty($hoursInfo['status'])): ?>
                    <span
                        class="detail-status-badge <?= !empty($hoursInfo['is_open']) ? 'open' : 'closed' ?>"><?= htmlspecialchars($hoursInfo['status']) ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="detail-title"><?= htmlspecialchars($listing['name']) ?></h1>

                <div class="detail-rating">
                    <div class="detail-stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <span class="star <?= $s <= round($rating) ? 'filled' : '' ?>"><?= icon('star') ?></span>
                        <?php endfor; ?>
                    </div>
                    <span class="detail-rating-text"><?= $rating ?> (<?= $reviewCount ?> reviews)</span>
                </div>

                <div class="detail-info-grid">
                    <div class="detail-info-item">
                        <span class="detail-info-icon"><?= icon('gps') ?></span>
                        <div>
                            <div class="detail-info-label">Address</div>
                            <div class="detail-info-value">
                                <?= htmlspecialchars($listing['address']) ?>
                                <?= $listing['city'] ? ', ' . htmlspecialchars($listing['city']) : '' ?>
                            </div>
                        </div>
                    </div>
                    <div class="detail-info-item">
                        <span class="detail-info-icon"><?= icon('clock') ?></span>
                        <div>
                            <div class="detail-info-label">Hours</div>
                            <div class="detail-info-value">
                                <?= htmlspecialchars($hoursInfo['label'] ?? 'Timings not available') ?>
                                <?php if (!empty($hoursInfo['status'])): ?>
                                <span
                                    class="hours-inline-status <?= !empty($hoursInfo['is_open']) ? 'open' : 'closed' ?>"><?= htmlspecialchars($hoursInfo['status']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (false): ?>
                    <div class="detail-info-item">
                        <span class="detail-info-icon"><?= icon('clock') ?></span>
                        <div>
                            <div class="detail-info-label">Timings</div>
                            <div class="detail-info-value"><?= date('g:i A', strtotime($listing['open_time'])) ?> —
                                <?= date('g:i A', strtotime($listing['close_time'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($listing['email']): ?>
                    <div class="detail-info-item">
                        <span class="detail-info-icon"><?= icon('news') ?></span>
                        <div>
                            <div class="detail-info-label">Email</div>
                            <div class="detail-info-value"><a
                                    href="mailto:<?= htmlspecialchars($listing['email']) ?>"><?= htmlspecialchars($listing['email']) ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- MOBILE ONLY: Contact (reordered for mobile) -->
                <div class="dm-contact detail-contact-card">
                    <h3>Contact</h3>
                    <div class="detail-contact-buttons">
                        <?php if ($listing['mobile']): ?>
                        <a href="tel:<?= htmlspecialchars($listing['mobile']) ?>" class="btn detail-btn-call">
                            <?= icon('phone') ?> Call Now
                        </a>
                        <?php endif; ?>
                        <?php $waNum = $listing['whatsapp'] ?? $listing['mobile']; if ($waNum): ?>
                        <a href="https://wa.me/91<?= preg_replace('/[^0-9]/', '', $waNum) ?>" target="_blank"
                            class="btn detail-btn-whatsapp">
                            <?= icon('phone') ?> WhatsApp
                        </a>
                        <?php endif; ?>
                        <?php if ($listing['latitude'] && $listing['longitude']): ?>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $listing['latitude'] ?>,<?= $listing['longitude'] ?>"
                            target="_blank" class="btn detail-btn-directions">
                            <?= icon('gps') ?> Get Directions
                        </a>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-secondary detail-btn-share" onclick="shareListing()"
                        style="width:100%; margin-top:12px;">
                        <i class="fas fa-share-alt"></i> Share Listing
                    </button>
                    <button class="btn btn-primary"
                        onclick="document.getElementById('aptModalDetail').classList.add('active')"
                        style="width:100%; margin-top:8px;">
                        <i class="fas fa-calendar-check"></i> Request Appointment
                    </button>
                    <a href="promote.php?listing_id=<?= $listing['id'] ?>&listing_name=<?= urlencode($listing['name']) ?>"
                        class="btn"
                        style="width:100%; margin-top:8px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; display:flex; align-items:center; justify-content:center; gap:6px; font-weight:600;">
                        <i class="fas fa-bolt"></i> Promote This Listing
                    </a>
                </div>

                <!-- MOBILE ONLY: Map (reordered for mobile) -->
                <?php if ($listing['latitude'] && $listing['longitude']): ?>
                <div class="dm-map detail-map-card">
                    <h3>Location</h3>
                    <div class="detail-map">
                        <iframe
                            src="https://maps.google.com/maps?q=<?= $listing['latitude'] ?>,<?= $listing['longitude'] ?>&z=15&output=embed"
                            width="100%" height="250" style="border:0; border-radius:var(--radius);" allowfullscreen=""
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($listing['description']): ?>
                <div class="detail-description">
                    <h3>About</h3>
                    <p><?= nl2br(htmlspecialchars($listing['description'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- ===== QR CODE ===== -->
                <style>
                .detail-qr-section {
                    margin: 24px 0;
                }

                .detail-qr-card {
                    display: flex;
                    align-items: center;
                    gap: 24px;
                    background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
                    border: 1px solid #bfdbfe;
                    border-radius: 18px;
                    padding: 24px 28px;
                    box-shadow: 0 2px 12px rgba(37, 99, 235, .07);
                }

                .detail-qr-canvas {
                    flex-shrink: 0;
                    width: 210px;
                    height: 210px;
                    background: #fff;
                    border-radius: 16px;
                    padding: 16px;
                    box-shadow: 0 2px 14px rgba(0, 0, 0, .12);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .detail-qr-canvas canvas,
                .detail-qr-canvas img {
                    display: block;
                    border-radius: 4px;
                }

                .detail-qr-info {
                    flex: 1;
                    min-width: 0;
                }

                .detail-qr-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: #2563eb;
                    color: #fff;
                    border-radius: 100px;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: .4px;
                    padding: 4px 12px;
                    margin-bottom: 10px;
                }

                .detail-qr-info h4 {
                    font-size: 15px;
                    font-weight: 700;
                    color: #0f172a;
                    margin: 0 0 6px;
                    line-height: 1.3;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .detail-qr-info p {
                    font-size: 13px;
                    color: #475569;
                    margin: 0 0 16px;
                    line-height: 1.55;
                }

                .detail-qr-actions {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                }

                .detail-qr-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 16px;
                    border-radius: 9px;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    border: none;
                    transition: background .18s, transform .15s;
                    text-decoration: none;
                }

                .detail-qr-btn:hover {
                    transform: translateY(-1px);
                }

                .detail-qr-btn-primary {
                    background: #2563eb;
                    color: #fff;
                }

                .detail-qr-btn-primary:hover {
                    background: #1d4ed8;
                }

                .detail-qr-btn-secondary {
                    background: #f1f5f9;
                    color: #374151;
                    border: 1.5px solid #e2e8f0;
                }

                .detail-qr-btn-secondary:hover {
                    background: #e2e8f0;
                }

                @media (max-width: 540px) {
                    .detail-qr-card {
                        flex-direction: column;
                        align-items: flex-start;
                        padding: 20px;
                        gap: 18px;
                    }

                    .detail-qr-info h4 {
                        white-space: normal;
                    }
                }

                .detail-qr-locked-placeholder {
                    flex-shrink: 0;
                    width: 210px;
                    height: 210px;
                    background: #f8fafc;
                    border-radius: 16px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    border: 2px dashed #cbd5e1;
                }

                .detail-qr-lock-icon {
                    font-size: 30px;
                    color: #94a3b8;
                }

                .detail-qr-lock-price {
                    font-size: 12px;
                    font-weight: 800;
                    color: #475569;
                    background: #fff;
                    border-radius: 6px;
                    padding: 3px 10px;
                    border: 1.5px solid #e2e8f0;
                }

                .detail-qr-badge-locked {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: linear-gradient(135deg, #f59e0b, #d97706);
                    color: #fff;
                    border-radius: 100px;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: .4px;
                    padding: 4px 12px;
                    margin-bottom: 10px;
                }

                .detail-qr-btn-pay {
                    background: linear-gradient(135deg, #2563eb, #059669);
                    color: #fff;
                    box-shadow: 0 4px 15px rgba(37, 99, 235, .25);
                }

                .detail-qr-btn-pay:hover {
                    background: linear-gradient(135deg, #1d4ed8, #047857);
                    box-shadow: 0 6px 20px rgba(37, 99, 235, .35);
                }

                .detail-qr-btn-pay:disabled {
                    opacity: .7;
                    cursor: not-allowed;
                    transform: none;
                }

                /* ===== MOBILE SECTION REORDER ===== */
                .dm-contact, .dm-map, .dm-app { display: none; }

                @media (max-width: 768px) {
                    .detail-sidebar { display: none !important; }
                    .detail-main { display: flex; flex-direction: column; }
                    .detail-header    { order: 1; }
                    .detail-title     { order: 2; }
                    .detail-rating    { order: 3; }
                    .detail-info-grid { order: 4; }
                    .dm-contact       { display: block; order: 5; }
                    .dm-map           { display: block; order: 6; }
                    .detail-description { order: 7; }
                    .dm-reviews       { display: block; order: 8; }
                    .detail-qr-section { order: 9; }
                    .dm-app           { display: none; }
                }
                </style>

                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

                <div class="detail-qr-section" id="qrSection">
                    <?php if ($qrPaid): ?>
                    <div class="detail-qr-card">
                        <div class="detail-qr-canvas" id="detailQrCode"></div>
                        <div class="detail-qr-info">
                            <div class="detail-qr-badge"><i class="fas fa-qrcode"></i> Scan to Review</div>
                            <h4><?= htmlspecialchars($listing['name']) ?></h4>
                            <p>Scan this QR code with any phone camera to open this listing and drop a review instantly
                                — no app needed.</p>
                            <div class="detail-qr-actions">
                                <button class="detail-qr-btn detail-qr-btn-primary" onclick="downloadQR()">
                                    <i class="fas fa-download"></i> Download QR
                                </button>
                                <button class="detail-qr-btn detail-qr-btn-secondary" id="qrCopyBtn"
                                    onclick="copyReviewLink()">
                                    <i class="fas fa-link"></i> Copy Link
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="detail-qr-card">
                        <div class="detail-qr-locked-placeholder">
                            <i class="fas fa-lock detail-qr-lock-icon"></i>
                            <span class="detail-qr-lock-price">₹200</span>
                        </div>
                        <div class="detail-qr-info">
                            <div class="detail-qr-badge-locked"><i class="fas fa-star"></i> One-Time Unlock</div>
                            <h4>Get Your Review QR Code</h4>
                            <p>Share a scannable QR with patients — they scan once and land directly on your review
                                section. No app needed. One-time payment, valid forever.</p>
                            <div class="detail-qr-actions">
                                <button class="detail-qr-btn detail-qr-btn-pay" id="qrPayBtn"
                                    onclick="initiateQrPayment()">
                                    <i class="fas fa-lock-open"></i> Pay ₹200 — Unlock QR
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- MOBILE ONLY: Get App (after QR on mobile) -->
                <div class="dm-app">
                    <img src="assets/images/icon.png" alt="HealthDial"
                        style="width:48px; height:48px; border-radius:12px; flex-shrink:0;" />
                    <div style="flex:1; min-width:0;">
                        <strong>Get the HealthDial App</strong>
                        <p style="font-size:var(--fs-xs); color:var(--text-muted); margin-top:4px;">GPS navigation,
                            reminders & more</p>
                    </div>
                    <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank"
                        class="btn btn-primary" style="font-size:var(--fs-xs); padding:8px 16px; flex-shrink:0;">Download</a>
                </div>

                <!-- Reviews -->
                <div class="detail-reviews dm-reviews" id="reviews">
                    <h3>Reviews (<?= $reviewCount ?>)</h3>
                    <?php if (!empty($reviews)): ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $rev): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="review-avatar"><?= strtoupper(substr($rev['user_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="review-name"><?= htmlspecialchars($rev['user_name'] ?? 'Anonymous') ?>
                                    </div>
                                    <div class="review-date"><?= date('M d, Y', strtotime($rev['created_at'])) ?></div>
                                </div>
                                <div class="review-rating-badge">⭐ <?= $rev['rating'] ?></div>
                            </div>
                            <?php if ($rev['comment']): ?>
                            <p class="review-comment"><?= htmlspecialchars($rev['comment']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted); font-size:var(--fs-sm); padding:8px 0;">No reviews yet. Be the first to review!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar / Contact -->
            <div class="detail-sidebar">
                <div class="detail-contact-card">
                    <h3>Contact</h3>
                    <div class="detail-contact-buttons">
                        <?php if ($listing['mobile']): ?>
                        <a href="tel:<?= htmlspecialchars($listing['mobile']) ?>" class="btn detail-btn-call">
                            <?= icon('phone') ?> Call Now
                        </a>
                        <?php endif; ?>
                        <?php
                            $whatsapp = $listing['whatsapp'] ?? $listing['mobile'];
                            if ($whatsapp): ?>
                        <a href="https://wa.me/91<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>" target="_blank"
                            class="btn detail-btn-whatsapp">
                            <?= icon('phone') ?> WhatsApp
                        </a>
                        <?php endif; ?>
                        <?php if ($listing['latitude'] && $listing['longitude']): ?>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $listing['latitude'] ?>,<?= $listing['longitude'] ?>"
                            target="_blank" class="btn detail-btn-directions">
                            <?= icon('gps') ?> Get Directions
                        </a>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-secondary detail-btn-share" onclick="shareListing()"
                        style="width:100%; margin-top:12px;">
                        <i class="fas fa-share-alt"></i> Share Listing
                    </button>
                    <!-- Appointment Request -->
                    <button class="btn btn-primary"
                        onclick="document.getElementById('aptModalDetail').classList.add('active')"
                        style="width:100%; margin-top:8px;">
                        <i class="fas fa-calendar-check"></i> Request Appointment
                    </button>
                    <!-- Promote This Listing -->
                    <a href="promote.php?listing_id=<?= $listing['id'] ?>&listing_name=<?= urlencode($listing['name']) ?>"
                        class="btn"
                        style="width:100%; margin-top:8px; background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; display:flex; align-items:center; justify-content:center; gap:6px; font-weight:600;">
                        <i class="fas fa-bolt"></i> Promote This Listing
                    </a>
                </div>

                <!-- Map -->
                <?php if ($listing['latitude'] && $listing['longitude']): ?>
                <div class="detail-map-card">
                    <h3>Location</h3>
                    <div class="detail-map">
                        <iframe
                            src="https://maps.google.com/maps?q=<?= $listing['latitude'] ?>,<?= $listing['longitude'] ?>&z=15&output=embed"
                            width="100%" height="250" style="border:0; border-radius:var(--radius);" allowfullscreen=""
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
                <?php endif; ?>

                <!-- App CTA -->
                <div class="detail-app-card">
                    <img src="assets/images/icon.png" alt="HealthDial"
                        style="width:48px; height:48px; border-radius:12px;" />
                    <div>
                        <strong>Get the HealthDial App</strong>
                        <p style="font-size:var(--fs-xs); color:var(--text-muted); margin-top:4px;">GPS navigation,
                            reminders & more</p>
                    </div>
                    <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank"
                        class="btn btn-primary" style="font-size:var(--fs-xs); padding:8px 16px;">Download</a>
                </div>
            </div>
        </div>

        <div class="mobile-sections-wrapper">
        <?php if (!empty($similarListings)): ?>
        <section class="similar-listings-section" aria-labelledby="similarListingsTitle">
            <div class="similar-listings-header">
                <div>
                    <span class="section-label"><i class="fas fa-hospital"></i> Similar Facilities</span>
                    <h2 id="similarListingsTitle">More
                        <?= htmlspecialchars(strtolower($listing['category_name'] ?? 'medical facilities')) ?> in
                        <?= htmlspecialchars(hd_city_label($listing['city'] ?? '')) ?>
                    </h2>
                </div>
                <a href="<?= $assetBase ?>/looking.php?cat=<?= intval($listing['category_id'] ?? 0) ?>&name=<?= urlencode($listing['category_name'] ?? '') ?>&city=<?= urlencode(hd_city_label($listing['city'] ?? '')) ?>"
                    class="similar-view-all">View all</a>
            </div>
            <div class="listing-grid similar-listings-grid">
                <?php foreach ($similarListings as $similar): ?>
                <?php
                            $similarUrl = hd_listing_url([
                                'id' => $similar['id'],
                                'name' => $similar['name'],
                                'address' => $similar['address'],
                                'city' => $similar['city'],
                                'slug' => $similar['slug'] ?? null,
                            ], false);
                            $similarRating = round(floatval($similar['avg_rating'] ?? 0), 1);
                            $similarReviews = intval($similar['review_count'] ?? 0);
                            ?>
                <article class="listing-card similar-listing-card">
                    <a href="<?= htmlspecialchars($similarUrl) ?>" class="listing-card-image">
                        <?php if (!empty($similar['image'])): ?>
                        <img src="<?= htmlspecialchars($similar['image']) ?>"
                            alt="<?= htmlspecialchars($similar['name']) ?>" loading="lazy" />
                        <?php else: ?>
                        <div class="listing-placeholder-modern" style="background:<?= $catGrad ?>">
                            <div class="placeholder-icon-ring"><span style="font-size:28px"><?= $catIcon ?></span></div>
                            <div class="placeholder-name"><?= htmlspecialchars(substr($similar['name'], 0, 30)) ?></div>
                            <div class="placeholder-cat">
                                <?= htmlspecialchars($similar['category_name'] ?? $listing['category_name']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </a>
                    <div class="listing-card-body">
                        <div class="listing-card-top">
                            <span
                                class="listing-category-badge"><?= htmlspecialchars($similar['category_name'] ?? $listing['category_name']) ?></span>
                            <span class="listing-rating">
                                <i class="fas fa-star"></i>
                                <small><?= $similarRating ?> (<?= $similarReviews ?>)</small>
                            </span>
                        </div>
                        <a href="<?= htmlspecialchars($similarUrl) ?>"
                            class="listing-card-name"><?= htmlspecialchars($similar['name']) ?></a>
                        <p class="listing-card-address"><i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($similar['address'] ?? '') ?>
                            <?= !empty($similar['city']) ? ', ' . htmlspecialchars($similar['city']) : '' ?>
                        </p>
                        <?php if (!empty($similar['distance'])): ?>
                        <span class="listing-distance"><i class="fas fa-location-arrow"></i>
                            <?= htmlspecialchars($similar['distance']) ?> km away</span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== WEB REVIEW FORM ===== -->
        <div class="review-form-section" id="reviewFormSection">
            <h3><i class="fas fa-pen" style="color:var(--blue);"></i> Write a Review</h3>

            <div class="star-rating-wrapper">
                <label class="review-label">Your Rating</label>
                <div class="star-input" id="starInput" onmouseleave="hdPreviewStars(0)">
                    <span class="star-icon" data-val="1" onclick="hdSetStar(1)"
                        onmouseenter="hdPreviewStars(1)">&#9733;</span>
                    <span class="star-icon" data-val="2" onclick="hdSetStar(2)"
                        onmouseenter="hdPreviewStars(2)">&#9733;</span>
                    <span class="star-icon" data-val="3" onclick="hdSetStar(3)"
                        onmouseenter="hdPreviewStars(3)">&#9733;</span>
                    <span class="star-icon" data-val="4" onclick="hdSetStar(4)"
                        onmouseenter="hdPreviewStars(4)">&#9733;</span>
                    <span class="star-icon" data-val="5" onclick="hdSetStar(5)"
                        onmouseenter="hdPreviewStars(5)">&#9733;</span>
                    <span class="star-label" id="starLabel">Tap to rate</span>
                </div>
            </div>
            <script>
            // Star rating - defined inline to guarantee availability
            var hdSelectedRating = 0;
            var hdRatingLabels = ['Tap to rate', 'Poor', 'Fair', 'Good', 'Great', 'Excellent'];
            var hdRatingColors = ['#94a3b8', '#ef4444', '#f97316', '#eab308', '#22c55e', '#10b981'];

            function hdSetStar(v) {
                hdSelectedRating = v;
                hdRenderStars(v);
            }

            function hdPreviewStars(v) {
                hdRenderStars(v === 0 ? hdSelectedRating : v);
            }

            function hdRenderStars(v) {
                var icons = document.querySelectorAll('#starInput .star-icon');
                for (var i = 0; i < icons.length; i++) {
                    if (i < v) {
                        icons[i].classList.add('active');
                    } else {
                        icons[i].classList.remove('active');
                    }
                }
                var lbl = document.getElementById('starLabel');
                if (lbl) {
                    lbl.textContent = hdRatingLabels[v] || hdRatingLabels[0];
                    lbl.style.color = hdRatingColors[v] || hdRatingColors[0];
                }
            }
            </script>

            <div class="review-field">
                <label class="review-label" for="reviewName"><i class="fas fa-user"></i> Your Name</label>
                <input type="text" id="reviewName" placeholder="Enter your name" class="review-input" />
            </div>

            <div class="review-field">
                <label class="review-label" for="reviewText"><i class="fas fa-comment-alt"></i> Your Review</label>
                <textarea class="review-textarea" id="reviewText" placeholder="Share your experience with this place..."
                    maxlength="500" oninput="updateCharCount(this)"></textarea>
                <span class="review-char-count" id="charCount">0 / 500</span>
            </div>

            <button class="review-submit-btn" onclick="submitReview()">
                <i class="fas fa-paper-plane"></i> Submit Review
            </button>
        </div>

        <!-- MOBILE ONLY: Get App (below Write Review) -->
        <div class="dm-app-mobile">
            <img src="assets/images/icon.png" alt="HealthDial"
                style="width:48px; height:48px; border-radius:12px; flex-shrink:0;" />
            <div style="flex:1; min-width:0;">
                <strong>Get the HealthDial App</strong>
                <p style="font-size:var(--fs-xs); color:var(--text-muted); margin-top:4px;">GPS navigation,
                    reminders &amp; more</p>
            </div>
            <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank"
                class="btn btn-primary" style="font-size:var(--fs-xs); padding:8px 16px; flex-shrink:0;">Download</a>
        </div>
        </div><!-- /mobile-sections-wrapper -->
    </div>
</section>
<style>
.dm-app-mobile { display: none; }
@media (max-width: 768px) {
    .mobile-sections-wrapper {
        display: flex;
        flex-direction: column;
    }
    .review-form-section   { order: 1; }
    .dm-app-mobile         { order: 2; }
    .similar-listings-section { order: 3; }
    .dm-app-mobile {
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--card);
        border-radius: var(--radius);
        padding: 16px;
        margin-top: 16px;
    }
}
</style>

<!-- Appointment Request Modal -->
<div class="apt-modal-overlay" id="aptModalDetail">
    <div class="apt-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3><i class="fas fa-calendar-check" style="color:var(--blue);"></i> Book Appointment</h3>
            <button onclick="document.getElementById('aptModalDetail').classList.remove('active')"
                style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <p style="color:var(--text-muted);font-size:var(--fs-sm);margin-bottom:20px;">Request an appointment at
            <strong><?= htmlspecialchars($listing['name']) ?></strong>
        </p>
        <div class="apt-field">
            <label><i class="fas fa-user" style="color:var(--blue);margin-right:6px;"></i> Patient Name</label>
            <input type="text" id="aptNameDetail" placeholder="Enter patient name" />
        </div>
        <div class="apt-field">
            <label><i class="fas fa-phone" style="color:var(--blue);margin-right:6px;"></i> Contact Number</label>
            <input type="tel" id="aptPhoneDetail" placeholder="Enter your phone number" />
        </div>
        <div style="display:flex;gap:12px;">
            <div class="apt-field" style="flex:1;">
                <label><i class="fas fa-calendar" style="color:var(--blue);margin-right:6px;"></i> Preferred
                    Date</label>
                <input type="date" id="aptDateDetail" />
            </div>
            <div class="apt-field" style="flex:1;">
                <label><i class="fas fa-clock" style="color:var(--blue);margin-right:6px;"></i> Preferred Time</label>
                <input type="time" id="aptTimeDetail" />
            </div>
        </div>
        <div class="apt-field">
            <label><i class="fas fa-notes-medical" style="color:var(--blue);margin-right:6px;"></i> Health
                Concern</label>
            <textarea id="aptDescDetail" rows="3"
                placeholder="Briefly describe your symptoms or reason for visit..."></textarea>
        </div>
        <button class="btn btn-primary" style="width:100%;padding:14px;" onclick="submitDetailAppointment()">
            <i class="fas fa-paper-plane"></i> Send Appointment Request
        </button>
        <p style="color:var(--text-muted);font-size:11px;text-align:center;margin-top:10px;">
            <i class="fas fa-lock" style="font-size:10px;"></i> Your details are shared only with this hospital via
            WhatsApp
        </p>
    </div>
</div>

<script>
// QR Code
const HD_QR_PAID = <?= $qrPaid ? 'true' : 'false' ?>;
const HD_LISTING_ID = <?= intval($listing['id']) ?>;
const HD_REVIEW_URL = '<?= addslashes($canonicalUrl) ?>#reviewFormSection';
const HD_LISTING_NAME_SLUG = '<?= addslashes(preg_replace('/[^a-z0-9]+/i', '-', $listing['name'])) ?>';

function renderQRCode(container) {
    if (!container) return;

    function initQR() {
        if (typeof QRCode === 'undefined') {
            setTimeout(initQR, 80);
            return;
        }
        new QRCode(container, {
            text: HD_REVIEW_URL,
            width: 178,
            height: 178,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.L
        });
    }
    initQR();
}

if (HD_QR_PAID) {
    renderQRCode(document.getElementById('detailQrCode'));
}

function downloadQR() {
    const canvas = document.querySelector('#detailQrCode canvas');
    if (!canvas) return;
    const a = document.createElement('a');
    a.download = HD_LISTING_NAME_SLUG + '-review-qr.png';
    a.href = canvas.toDataURL('image/png');
    a.click();
}

function copyReviewLink() {
    const url = '<?= addslashes($canonicalUrl) ?>#reviews';
    const btn = document.getElementById('qrCopyBtn');
    navigator.clipboard.writeText(url).then(function() {
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            btn.style.background = '#dcfce7';
            btn.style.color = '#15803d';
            setTimeout(function() {
                btn.innerHTML = orig;
                btn.style.background = '';
                btn.style.color = '';
            }, 2000);
        }
    });
}

function initiateQrPayment() {
    const btn = document.getElementById('qrPayBtn');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating order...';
        btn.disabled = true;
    }

    fetch('qr_create_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                listing_id: HD_LISTING_ID
            })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Could not create order');
            if (data.already_paid) {
                location.reload();
                return;
            }
            openRazorpayCheckout(data.razorpay_order_id, data.razorpay_key_id, data.amount_paise);
        })
        .catch(err => {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-lock-open"></i> Pay ₹200 — Unlock QR';
                btn.disabled = false;
            }
            alert('Could not initiate payment. Please try again.\n' + err.message);
        });
}

function openRazorpayCheckout(orderId, keyId, amountPaise) {
    if (typeof Razorpay === 'undefined') {
        const s = document.createElement('script');
        s.src = 'https://checkout.razorpay.com/v1/checkout.js';
        s.onload = () => openRazorpayCheckout(orderId, keyId, amountPaise);
        s.onerror = () => {
            const btn = document.getElementById('qrPayBtn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-lock-open"></i> Pay ₹200 — Unlock QR';
                btn.disabled = false;
            }
            alert('Could not load payment gateway. Please check your connection.');
        };
        document.head.appendChild(s);
        return;
    }
    new Razorpay({
        key: keyId,
        amount: amountPaise,
        currency: 'INR',
        name: 'HealthDial',
        description: 'QR Code Unlock',
        order_id: orderId,
        handler: function(response) {
            verifyQrPayment(response.razorpay_order_id, response.razorpay_payment_id, response
                .razorpay_signature);
        },
        theme: {
            color: '#2563eb'
        },
        modal: {
            ondismiss: function() {
                const btn = document.getElementById('qrPayBtn');
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-lock-open"></i> Pay ₹200 — Unlock QR';
                    btn.disabled = false;
                }
            }
        }
    }).open();
}

function verifyQrPayment(rzpOrderId, rzpPaymentId, rzpSignature) {
    fetch('qr_verify_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                listing_id: HD_LISTING_ID,
                razorpay_order_id: rzpOrderId,
                razorpay_payment_id: rzpPaymentId,
                razorpay_signature: rzpSignature
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showQrUnlocked();
            } else {
                alert('Payment verification failed. Please contact support with payment ID: ' + rzpPaymentId);
            }
        })
        .catch(() => alert('Verification error. Please contact support.'));
}

function showQrUnlocked() {
    const section = document.getElementById('qrSection');
    if (!section) return;
    section.innerHTML = `
        <div class="detail-qr-card">
            <div class="detail-qr-canvas" id="detailQrCode"></div>
            <div class="detail-qr-info">
                <div class="detail-qr-badge"><i class="fas fa-check-circle"></i> QR Unlocked!</div>
                <h4><?= addslashes(htmlspecialchars($listing['name'])) ?></h4>
                <p>Your QR code is ready. Share it with patients to collect reviews instantly — no app needed.</p>
                <div class="detail-qr-actions">
                    <button class="detail-qr-btn detail-qr-btn-primary" onclick="downloadQR()">
                        <i class="fas fa-download"></i> Download QR
                    </button>
                    <button class="detail-qr-btn detail-qr-btn-secondary" id="qrCopyBtn" onclick="copyReviewLink()">
                        <i class="fas fa-link"></i> Copy Link
                    </button>
                </div>
            </div>
        </div>
    `;
    renderQRCode(document.getElementById('detailQrCode'));
}

// Gallery carousel
let currentSlide = 0;
const totalSlides = <?= count($images) ?>;

function galleryGoTo(i) {
    currentSlide = i;
    const track = document.getElementById('galleryTrack');
    if (track) track.style.transform = `translateX(-${i * 100}%)`;
    document.querySelectorAll('.gallery-dot').forEach((d, idx) => d.classList.toggle('active', idx === i));
}

function galleryNext() {
    galleryGoTo((currentSlide + 1) % totalSlides);
}

function galleryPrev() {
    galleryGoTo((currentSlide - 1 + totalSlides) % totalSlides);
}

// Share
function shareListing() {
    const url = window.location.href;
    if (navigator.share) {
        navigator.share({
            title: '<?= addslashes($listing['name']) ?>',
            url: url
        });
    } else {
        navigator.clipboard.writeText(url);
        alert('Link copied to clipboard!');
    }
}

// Swipe support
(function() {
    const carousel = document.getElementById('galleryCarousel');
    if (!carousel) return;
    let startX = 0;
    carousel.addEventListener('touchstart', e => {
        startX = e.touches[0].clientX;
    });
    carousel.addEventListener('touchend', e => {
        const diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) diff > 0 ? galleryNext() : galleryPrev();
    });
})();

function updateCharCount(el) {
    const count = el.value.length;
    const counter = document.getElementById('charCount');
    if (counter) {
        counter.textContent = count + ' / 500';
        counter.style.color = count > 450 ? '#ef4444' : '#94a3b8';
    }
}

window.updateCharCount = updateCharCount;

// Submit Review via API
function submitReview() {
    const name = document.getElementById('reviewName').value.trim();
    const text = document.getElementById('reviewText').value.trim();
    const listingId = <?= $listing['id'] ?>;

    if (!name) {
        alert('Please enter your name.');
        return;
    }
    if (!hdSelectedRating) {
        alert('Please select a rating.');
        return;
    }
    if (!text || text.length < 10) {
        alert('Please write a review (at least 10 characters).');
        return;
    }

    const btn = document.querySelector('.review-submit-btn');
    const origText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    btn.disabled = true;

    fetch('submit_review.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                listing_id: listingId,
                rating: hdSelectedRating,
                review: text,
                guest_name: name
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const form = document.querySelector('.review-form-section');
                form.innerHTML = `
                    <div style="text-align:center;padding:30px 20px;">
                        <div style="width:60px;height:60px;border-radius:50%;background:rgba(67,182,73,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <i class="fas fa-check" style="color:var(--green);font-size:24px;"></i>
                        </div>
                        <h3 style="margin-bottom:8px;">Thank You!</h3>
                        <p style="color:var(--text-muted);">Your review has been submitted and will be visible after approval.</p>
                    </div>
                `;
            } else {
                // API returned error - send via WhatsApp as backup
                const msg =
                    `New Review for <?= addslashes($listing['name']) ?>\n\nName: ${name}\nRating: ${hdSelectedRating}/5\nReview: ${text || 'No comment'}`;
                window.open('https://wa.me/919911660669?text=' + encodeURIComponent(msg), '_blank');
                const form = document.querySelector('.review-form-section');
                form.innerHTML = `
                    <div style="text-align:center;padding:30px 20px;">
                        <div style="width:60px;height:60px;border-radius:50%;background:rgba(67,182,73,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <i class="fas fa-check" style="color:var(--green);font-size:24px;"></i>
                        </div>
                        <h3 style="margin-bottom:8px;">Thank You!</h3>
                        <p style="color:var(--text-muted);">Your review has been sent successfully.</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            console.error('Review submit error:', err);
            // Send via WhatsApp as reliable backup
            const msg =
                `New Review for <?= addslashes($listing['name']) ?>\n\nName: ${name}\nRating: ${hdSelectedRating}/5\nReview: ${text || 'No comment'}`;
            window.open('https://wa.me/919911660669?text=' + encodeURIComponent(msg), '_blank');
            // Still show success to user
            const form = document.querySelector('.review-form-section');
            form.innerHTML = `
                <div style="text-align:center;padding:30px 20px;">
                    <div style="width:60px;height:60px;border-radius:50%;background:rgba(67,182,73,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                        <i class="fas fa-check" style="color:var(--green);font-size:24px;"></i>
                    </div>
                    <h3 style="margin-bottom:8px;">Thank You!</h3>
                    <p style="color:var(--text-muted);">Your review has been sent successfully.</p>
                </div>
            `;
        });
}
window.submitReview = submitReview;

// Submit Appointment — send to listing's WhatsApp/mobile number
function submitDetailAppointment() {
    const name = document.getElementById('aptNameDetail').value;
    const phone = document.getElementById('aptPhoneDetail').value;
    const date = document.getElementById('aptDateDetail').value;
    const time = document.getElementById('aptTimeDetail').value;
    const desc = document.getElementById('aptDescDetail').value;
    if (!name || !phone) {
        alert('Please enter patient name and contact number.');
        return;
    }
    const dateStr = date ? new Date(date + 'T00:00').toLocaleDateString('en-IN', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    }) : 'Flexible';
    const timeStr = time || 'Flexible';
    const msg = 'Hello,\n\nI would like to request an appointment at *<?= addslashes($listing['name']) ?>*.\n\n' +
        '\u{1F464} Patient: ' + name + '\n' +
        '\u{1F4DE} Contact: ' + phone + '\n' +
        '\u{1F4C5} Preferred Date: ' + dateStr + '\n' +
        '\u{1F550} Preferred Time: ' + timeStr + '\n' +
        '\u{1F3E5} Concern: ' + (desc || 'General Consultation') + '\n\n' +
        'Please confirm availability. Thank you!\n\n' +
        '-- Sent via HealthDial (healthdial.com)';
    <?php
            $aptWa = $listing['whatsapp'] ?: $listing['mobile'];
            $aptWaClean = preg_replace('/[^0-9]/', '', $aptWa);
            $aptWaFull = $aptWaClean ? '91' . $aptWaClean : '919911660669';
            ?>
    window.open('https://wa.me/<?= $aptWaFull ?>?text=' + encodeURIComponent(msg), '_blank');
    document.getElementById('aptModalDetail').classList.remove('active');
}
window.submitDetailAppointment = submitDetailAppointment;
</script>
<script src="assets/js/listings.js?v=2.4.0"></script>

<?php if (!$qrPaid): ?>
<!-- ===== QR UPSELL INTERSTITIAL ===== -->
<div id="qr-interstitial" class="qri-overlay" aria-modal="true" role="dialog" aria-label="Unlock QR Code">
    <div class="qri-card">
        <button class="qri-close" onclick="closeQrInterstitial()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>

        <!-- Visual -->
        <div class="qri-visual">
            <div class="qri-blob qri-blob1"></div>
            <div class="qri-blob qri-blob2"></div>

            <!-- Simulated QR + phone scan animation -->
            <div class="qri-scene">
                <div class="qri-qr-mock">
                    <div class="qri-qr-corner qri-qr-tl"></div>
                    <div class="qri-qr-corner qri-qr-tr"></div>
                    <div class="qri-qr-corner qri-qr-bl"></div>
                    <div class="qri-qr-dots">
                        <?php
                                $pattern = [1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, 1, 0, 1, 1, 1, 0, 0, 1, 0, 1, 0, 1, 1, 0, 1, 1, 0, 1, 0, 1, 0, 0, 1, 1, 0, 1, 0, 1, 0, 0, 1, 1, 0, 1, 1, 0, 1, 0];
                                foreach ($pattern as $bit)
                                    echo '<span class="' . ($bit ? 'on' : '') . '"></span>';
                                ?>
                    </div>
                    <div class="qri-scanline"></div>
                </div>
                <div class="qri-phone-icon"><i class="fas fa-mobile-screen-button"></i></div>
                <div class="qri-pulse-ring qri-pulse1"></div>
                <div class="qri-pulse-ring qri-pulse2"></div>
            </div>

            <div class="qri-badge-tag">
                <i class="fas fa-lock-open"></i> One-Time ₹200 Unlock
            </div>
        </div>

        <!-- Body -->
        <div class="qri-body">
            <h2 class="qri-title">Let Patients <span class="gradient-text">Review You</span> in One Scan</h2>
            <p class="qri-sub">Get a personal QR code for <strong><?= htmlspecialchars($listing['name']) ?></strong>.
                Patients scan once → land on your review page. No app needed. Reviews build trust, trust brings more
                patients.</p>

            <div class="qri-benefits">
                <div class="qri-benefit"><i class="fas fa-bolt"></i> Instant review access — one scan</div>
                <div class="qri-benefit"><i class="fas fa-infinity"></i> Valid forever — pay once</div>
                <div class="qri-benefit"><i class="fas fa-download"></i> Download & print for your reception</div>
                <div class="qri-benefit"><i class="fas fa-star"></i> More reviews = higher ranking</div>
            </div>

            <div class="qri-price-row">
                <div class="qri-price">
                    <span class="qri-price-old">₹999</span>
                    <span class="qri-price-current">₹200</span>
                    <span class="qri-price-tag">Launch Offer</span>
                </div>
            </div>

            <div class="qri-ctas">
                <button class="qri-btn-primary" onclick="closeQrInterstitial(); initiateQrPayment();">
                    <i class="fas fa-qrcode"></i> Unlock My QR Code — ₹200
                </button>
                <button class="qri-btn-skip" onclick="closeQrInterstitial()">Maybe Later</button>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== QR UPSELL INTERSTITIAL ===== */
.qri-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    padding: 16px;
    animation: qriFadeIn 0.35s ease both;
}

.qri-overlay.hiding {
    animation: qriFadeOut 0.28s ease both;
}

@keyframes qriFadeIn {
    from {
        opacity: 0
    }

    to {
        opacity: 1
    }
}

@keyframes qriFadeOut {
    from {
        opacity: 1
    }

    to {
        opacity: 0
    }
}

.qri-card {
    position: relative;
    width: 100%;
    max-width: 500px;
    border-radius: 24px;
    overflow: hidden;
    background: var(--glass, rgba(8, 16, 40, 0.97));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    backdrop-filter: blur(28px);
    -webkit-backdrop-filter: blur(28px);
    box-shadow: 0 32px 80px rgba(0, 0, 0, 0.6);
    animation: qriSlideUp 0.4s cubic-bezier(.22, 1, .36, 1) both;
}

[data-theme="light"] .qri-card {
    background: rgba(255, 255, 255, 0.98);
}

@keyframes qriSlideUp {
    from {
        transform: translateY(40px);
        opacity: 0
    }

    to {
        transform: translateY(0);
        opacity: 1
    }
}

.qri-close {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 10;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.12));
    background: rgba(255, 255, 255, 0.07);
    color: var(--text-muted, #94a3b8);
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.qri-close:hover {
    background: rgba(239, 68, 68, 0.18);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.35);
}

/* Visual header */
.qri-visual {
    position: relative;
    height: 200px;
    overflow: hidden;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.18) 0%, rgba(37, 99, 235, 0.15) 50%, rgba(16, 185, 129, 0.14) 100%);
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    display: flex;
    align-items: center;
    justify-content: center;
}

.qri-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(55px);
    opacity: 0.4;
}

.qri-blob1 {
    width: 200px;
    height: 200px;
    background: #7c3aed;
    top: -60px;
    right: -20px;
    animation: blobFloat 8s ease-in-out infinite;
}

.qri-blob2 {
    width: 160px;
    height: 160px;
    background: #10b981;
    bottom: -40px;
    left: -20px;
    animation: blobFloat 6s ease-in-out infinite reverse;
}

/* QR scene */
.qri-scene {
    position: relative;
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Simulated QR code */
.qri-qr-mock {
    position: relative;
    width: 110px;
    height: 110px;
    background: rgba(255, 255, 255, 0.92);
    border-radius: 12px;
    padding: 10px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}

[data-theme="light"] .qri-qr-mock {
    background: rgba(255, 255, 255, 1);
}

.qri-qr-corner {
    position: absolute;
    width: 22px;
    height: 22px;
    border: 3px solid #1e3a8a;
    border-radius: 3px;
}

.qri-qr-tl {
    top: 8px;
    left: 8px;
}

.qri-qr-tr {
    top: 8px;
    right: 8px;
}

.qri-qr-bl {
    bottom: 8px;
    left: 8px;
}

.qri-qr-tl::after,
.qri-qr-tr::after,
.qri-qr-bl::after {
    content: '';
    position: absolute;
    width: 10px;
    height: 10px;
    background: #1e3a8a;
    border-radius: 1px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.qri-qr-dots {
    position: absolute;
    inset: 32px;
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1.5px;
}

.qri-qr-dots span {
    border-radius: 1px;
}

.qri-qr-dots span.on {
    background: #1e3a8a;
}

/* Scan line */
.qri-scanline {
    position: absolute;
    left: 6px;
    right: 6px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #22c55e, transparent);
    box-shadow: 0 0 8px #22c55e;
    animation: scanMove 2s ease-in-out infinite;
    top: 10px;
}

@keyframes scanMove {
    0% {
        top: 10px;
        opacity: 0;
    }

    10% {
        opacity: 1;
    }

    90% {
        opacity: 1;
    }

    100% {
        top: 100px;
        opacity: 0;
    }
}

/* Phone icon */
.qri-phone-icon {
    position: absolute;
    bottom: -8px;
    right: -24px;
    font-size: 2.4rem;
    color: rgba(255, 255, 255, 0.85);
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
    animation: phoneBob 3s ease-in-out infinite;
}

@keyframes phoneBob {

    0%,
    100% {
        transform: translateY(0) rotate(-8deg)
    }

    50% {
        transform: translateY(-6px) rotate(-8deg)
    }
}

/* Pulse rings */
.qri-pulse-ring {
    position: absolute;
    border-radius: 50%;
    border: 1.5px solid rgba(99, 102, 241, 0.45);
    animation: qriPulseOut 2.4s ease-out infinite;
}

.qri-pulse1 {
    width: 130px;
    height: 130px;
    top: -10px;
    left: -10px;
}

.qri-pulse2 {
    width: 160px;
    height: 160px;
    top: -25px;
    left: -25px;
    animation-delay: .8s;
}

@keyframes qriPulseOut {
    0% {
        transform: scale(0.85);
        opacity: 0.6
    }

    100% {
        transform: scale(1.2);
        opacity: 0
    }
}

/* Badge */
.qri-badge-tag {
    position: absolute;
    bottom: 14px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #7c3aed, #2563eb);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    padding: 5px 16px;
    border-radius: 99px;
    white-space: nowrap;
    box-shadow: 0 4px 16px rgba(124, 58, 237, 0.45);
}

/* Body */
.qri-body {
    padding: 22px 26px 26px;
}

.qri-title {
    font-size: 1.28rem;
    font-weight: 800;
    color: var(--text, #f1f5f9);
    line-height: 1.3;
    margin-bottom: 8px;
}

.qri-sub {
    font-size: 0.85rem;
    color: var(--text-secondary, #94a3b8);
    line-height: 1.6;
    margin-bottom: 16px;
}

.qri-benefits {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 18px;
}

.qri-benefit {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-secondary, #94a3b8);
}

.qri-benefit i {
    color: #a78bfa;
    font-size: 0.78rem;
    flex-shrink: 0;
}

/* Price */
.qri-price-row {
    margin-bottom: 18px;
}

.qri-price {
    display: flex;
    align-items: center;
    gap: 10px;
}

.qri-price-old {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    text-decoration: line-through;
}

.qri-price-current {
    font-size: 1.7rem;
    font-weight: 900;
    color: var(--text, #f1f5f9);
    line-height: 1;
}

.qri-price-tag {
    font-size: 0.68rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    padding: 3px 10px;
    border-radius: 99px;
}

/* CTAs */
.qri-ctas {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.qri-btn-primary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 24px;
    border-radius: 99px;
    background: linear-gradient(135deg, #7c3aed, #2563eb);
    color: #fff;
    font-weight: 700;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 24px rgba(124, 58, 237, 0.4);
    transition: transform 0.2s, box-shadow 0.2s;
    font-family: inherit;
}

.qri-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(124, 58, 237, 0.55);
}

.qri-btn-skip {
    background: none;
    border: none;
    color: var(--text-muted, #64748b);
    font-size: 0.82rem;
    cursor: pointer;
    padding: 6px;
    text-align: center;
    font-weight: 600;
    transition: color 0.2s;
    font-family: inherit;
}

.qri-btn-skip:hover {
    color: var(--text-secondary, #94a3b8);
}

@media (max-width: 480px) {
    .qri-body {
        padding: 18px 18px 22px;
    }

    .qri-title {
        font-size: 1.1rem;
    }

    .qri-visual {
        height: 170px;
    }

    .qri-benefits {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function() {
    var KEY = 'hd_qr_seen_<?= intval($listing['id']) ?>';
    var el = document.getElementById('qr-interstitial');
    if (!el) return;
    if (sessionStorage.getItem(KEY)) {
        el.style.display = 'none';
        return;
    }
    sessionStorage.setItem(KEY, '1');
    // Show after 3 seconds so user can first read the listing
    el.style.display = 'none';
    setTimeout(function() {
        el.style.display = 'flex';
    }, 3000);
})();

function closeQrInterstitial() {
    var el = document.getElementById('qr-interstitial');
    if (!el) return;
    el.classList.add('hiding');
    setTimeout(function() {
        el.style.display = 'none';
    }, 300);
}

document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('qr-interstitial');
    if (!el) return;
    el.addEventListener('click', function(e) {
        if (e.target === el) closeQrInterstitial();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeQrInterstitial();
    });
});
</script>
<?php endif; ?>

<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
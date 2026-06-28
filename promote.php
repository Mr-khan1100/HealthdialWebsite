<?php
$currentPage = 'promote';
$pageTitle = 'Promote Your Listing — HealthDial';
$pageDesc = 'Boost your hospital, clinic or pharmacy visibility on HealthDial. Choose a promotion plan and pay securely via PayU.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/user_auth.php';
// Only logged-in, phone-verified users/vendors can purchase a promotion (2-step).
$hdUser = hd_require_phone_verified();
require_once 'includes/header.php';
require_once 'includes/website_banner.php';

// Fetch plans from API
$plans = [];
$plansData = fetch_api_data(API_BASE . 'get_promotion_plans.php');
if ($plansData && !empty($plansData['data'])) {
    $plans = $plansData['data'];
}

// Pre-selected listing from URL
$preselectedId = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
$preselectedName = isset($_GET['listing_name']) ? htmlspecialchars($_GET['listing_name']) : '';
?>



<!-- Hero -->
<section class="promote-hero">
    <div class="container">
        <?php render_website_banner('promotion', 'top'); ?>
        <div class="promote-hero-content">
            <span class="promote-badge"><i class="fas fa-bolt"></i> Premium Promotion</span>
            <h1>Get More <span class="gradient-text">Patients</span> for Your Listing</h1>
            <p>Promote your hospital, clinic or pharmacy on HealthDial and appear at the top of search results. Reach
                thousands of patients looking for healthcare nearby.</p>
        </div>
    </div>
</section>

<!-- Promotion Wizard -->
<section class="section promote-section">
    <div class="container">
        <div class="promote-wizard">
            <!-- Progress Steps -->
            <div class="promote-steps">
                <div class="promote-step active" id="step1Indicator">
                    <div class="step-number">1</div>
                    <span>Find Listing</span>
                </div>
                <div class="promote-step-line"></div>
                <div class="promote-step" id="step2Indicator">
                    <div class="step-number">2</div>
                    <span>Choose Plan</span>
                </div>
                <div class="promote-step-line"></div>
                <div class="promote-step" id="step3Indicator">
                    <div class="step-number">3</div>
                    <span>Pay & Promote</span>
                </div>
            </div>

            <!-- Step 1: Search Listing -->
            <div class="promote-card" id="step1">
                <h2><i class="fas fa-search" style="color:var(--blue);"></i> Find Your Listing</h2>
                <p class="promote-card-desc">Search by name or city to find the listing you want to promote.</p>
                <div class="promote-search-wrap">
                    <div class="promote-search-bar">
                        <i class="fas fa-hospital" style="color:var(--blue);margin-right:8px;"></i>
                        <input type="text" id="promoListingSearch" placeholder="Type listing name..." autocomplete="off"
                            value="<?= $preselectedName ?>" />
                        <div class="promote-search-spinner" id="searchSpinner" style="display:none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                    <div class="promote-search-dropdown" id="promoSearchDropdown"></div>
                </div>
                <div class="promote-selected" id="selectedListingDisplay"
                    style="display:<?= $preselectedId ? 'flex' : 'none' ?>;">
                    <div class="promote-selected-info">
                        <i class="fas fa-check-circle" style="color:var(--green);"></i>
                        <span id="selectedListingName"><?= $preselectedName ?></span>
                    </div>
                    <button class="promote-change-btn" onclick="clearSelectedListing()"><i class="fas fa-times"></i>
                        Change</button>
                </div>
                <input type="hidden" id="selectedListingId" value="<?= $preselectedId ?>" />
                <button class="btn btn-primary promote-next-btn" id="step1NextBtn" onclick="goToStep(2)"
                    <?= $preselectedId ? '' : 'disabled' ?>>
                    Continue <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <!-- Step 2: Choose Plan -->
            <div class="promote-card" id="step2" style="display:none;">
                <h2><i class="fas fa-crown" style="color:#f59e0b;"></i> Choose Your Plan</h2>
                <p class="promote-card-desc">Select a promotion plan that fits your needs.</p>
                <div class="promote-plans-grid" id="plansGrid">
                    <?php if (empty($plans)): ?>
                    <div class="promote-no-plans">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>No promotion plans available right now. Please try again later.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($plans as $i => $plan): ?>
                    <div class="promote-plan-card <?= $i === 1 ? 'popular' : '' ?>" data-plan-id="<?= $plan['id'] ?>"
                        data-plan-price="<?= $plan['price'] ?>" onclick="selectPlan(this)">
                        <?php if ($i === 1): ?><span class="plan-popular-tag">Most Popular</span><?php endif; ?>
                        <h3><?= htmlspecialchars($plan['name']) ?></h3>
                        <div class="plan-price">₹<?= number_format($plan['price'], 0) ?></div>
                        <div class="plan-duration"><i class="fas fa-calendar-alt"></i> <?= $plan['duration_days'] ?>
                            days</div>
                        <p class="plan-desc">
                            <?= htmlspecialchars($plan['description'] ?? 'Boost your listing visibility') ?>
                        </p>
                        <ul class="plan-features">
                            <li><i class="fas fa-check"></i> Top search position</li>
                            <li><i class="fas fa-check"></i> "Sponsored" badge</li>
                            <li><i class="fas fa-check"></i> <?= $plan['duration_days'] ?> days visibility</li>
                            <?php if ($plan['price'] >= 500): ?>
                            <li><i class="fas fa-check"></i> Priority in nearby results</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <input type="hidden" id="selectedPlanId" value="" />
                <div class="promote-nav-btns">
                    <button class="btn btn-secondary" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i>
                        Back</button>
                    <button class="btn btn-primary promote-next-btn" id="step2NextBtn" onclick="goToStep(3)" disabled>
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 3: Details & Pay -->
            <div class="promote-card" id="step3" style="display:none;">
                <h2><i class="fas fa-credit-card" style="color:var(--green);"></i> Complete Payment</h2>
                <p class="promote-card-desc">Enter your details and proceed to secure payment.</p>

                <div class="promote-summary" id="orderSummary"></div>

                <div class="promote-form">
                    <div class="promote-field">
                        <label><i class="fas fa-user"></i> Your Name</label>
                        <input type="text" id="promoName" placeholder="Enter your full name"
                            value="<?= htmlspecialchars($hdUser['name'] ?? '') ?>" />
                    </div>
                    <div class="promote-field-row">
                        <div class="promote-field">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" id="promoPhone" placeholder="10-digit phone" maxlength="10"
                                value="<?= htmlspecialchars(substr(preg_replace('/\D/', '', $hdUser['mobile'] ?? ''), -10)) ?>" />
                        </div>
                        <div class="promote-field">
                            <label><i class="fas fa-envelope"></i> Email <span
                                    style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                            <input type="email" id="promoEmail" placeholder="your@email.com"
                                value="<?= htmlspecialchars($hdUser['email'] ?? '') ?>" />
                        </div>
                    </div>
                </div>

                <div class="promote-nav-btns">
                    <button class="btn btn-secondary" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i>
                        Back</button>
                    <button class="btn btn-primary promote-pay-btn" id="payNowBtn" onclick="initiatePayment()">
                        <i class="fas fa-lock"></i> Pay ₹<span id="payAmount">0</span> Securely
                    </button>
                </div>
                <p class="promote-secure-note">
                    <i class="fas fa-shield-alt"></i> Secured by <strong>PayU</strong> Payment Gateway. Your payment
                    details are never stored on our servers.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Trust Signals -->
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="promote-trust-grid">
            <div class="promote-trust-item">
                <div class="trust-icon"><i class="fas fa-eye"></i></div>
                <h4>10x More Views</h4>
                <p>Promoted listings get up to 10x more visibility</p>
            </div>
            <div class="promote-trust-item">
                <div class="trust-icon"><i class="fas fa-phone-volume"></i></div>
                <h4>More Calls</h4>
                <p>Reach patients actively searching for healthcare</p>
            </div>
            <div class="promote-trust-item">
                <div class="trust-icon"><i class="fas fa-shield-alt"></i></div>
                <h4>Secure Payments</h4>
                <p>PCI-DSS compliant payments via PayU</p>
            </div>
            <div class="promote-trust-item">
                <div class="trust-icon"><i class="fas fa-headset"></i></div>
                <h4>24/7 Support</h4>
                <p>Dedicated support for promoted listings</p>
            </div>
        </div>
    </div>
</section>

<script>
const API_BASE = '<?= API_BASE ?>';
let selectedListing = {
    id: <?= $preselectedId ?>,
    name: '<?= addslashes($preselectedName) ?>'
};
let selectedPlan = null;
let searchTimeout;

// ===== STEP NAVIGATION =====
function goToStep(step) {
    if (step === 2 && !selectedListing.id) {
        alert('Please select a listing first.');
        return;
    }
    if (step === 3 && !document.getElementById('selectedPlanId').value) {
        alert('Please select a plan first.');
        return;
    }

    document.querySelectorAll('.promote-card').forEach(c => c.style.display = 'none');
    document.getElementById('step' + step).style.display = 'block';

    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById('step' + i + 'Indicator');
        indicator.classList.toggle('active', i <= step);
        indicator.classList.toggle('completed', i < step);
    }

    if (step === 3) populateSummary();
    document.querySelector('.promote-wizard').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

// ===== STEP 1: LISTING SEARCH =====
// Uses same approach as homepage search (get_filtered_listings.php + search.php)
const searchInput = document.getElementById('promoListingSearch');
const dropdown = document.getElementById('promoSearchDropdown');
const spinner = document.getElementById('searchSpinner');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        return;
    }

    spinner.style.display = 'block';
    searchTimeout = setTimeout(async () => {
        try {
            // Same API call as homepage search
            const url = API_BASE + 'search.php?q=' + encodeURIComponent(q);
            const resp = await fetch(url);
            let text = await resp.text();
            // Handle double-JSON responses (same as homepage)
            if (text.includes('}{')) {
                text = text.substr(text.lastIndexOf('}{') + 1);
            }
            const data = JSON.parse(text);
            spinner.style.display = 'none';

            const listings = (data.data && data.data.results) ? data.data.results : [];

            if (listings.length === 0) {
                dropdown.innerHTML =
                    '<div class="promo-search-item no-result"><i class="fas fa-info-circle"></i> No listings found for "' +
                    q + '"</div>';
            } else {
                dropdown.innerHTML = listings.slice(0, 10).map(l => {
                    const safeName = (l.name || '').replace(/'/g, "\\'").replace(/"/g,
                        '&quot;');
                    const city = l.city || l.address || '';
                    const safeCity = city.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const cat = l.category || '';
                    const rating = l.rating ? '⭐ ' + l.rating : '';
                    return `
                        <div class="promo-search-item" onclick="selectListingItem(${l.id}, '${safeName}', '${safeCity}')">
                            <div class="promo-item-name">${l.name}</div>
                            <div class="promo-item-city"><i class="fas fa-map-marker-alt"></i> ${city || 'Unknown'}${cat ? ' · ' + cat : ''}${rating ? ' · ' + rating : ''}</div>
                        </div>
                    `;
                }).join('');
            }
            dropdown.style.display = 'block';
        } catch (err) {
            console.error('Search error:', err);
            spinner.style.display = 'none';
            dropdown.innerHTML =
                '<div class="promo-search-item no-result"><i class="fas fa-exclamation-triangle"></i> Search failed. Try again.</div>';
            dropdown.style.display = 'block';
        }
    }, 300);
});

document.addEventListener('click', e => {
    if (!e.target.closest('.promote-search-wrap')) dropdown.style.display = 'none';
});

function selectListingItem(id, name, city) {
    selectedListing = {
        id,
        name
    };
    document.getElementById('selectedListingId').value = id;
    document.getElementById('selectedListingName').textContent = name + (city ? ' — ' + city : '');
    document.getElementById('selectedListingDisplay').style.display = 'flex';
    searchInput.value = name;
    dropdown.style.display = 'none';
    document.getElementById('step1NextBtn').disabled = false;
}

function clearSelectedListing() {
    selectedListing = {
        id: 0,
        name: ''
    };
    document.getElementById('selectedListingId').value = '';
    document.getElementById('selectedListingDisplay').style.display = 'none';
    searchInput.value = '';
    searchInput.focus();
    document.getElementById('step1NextBtn').disabled = true;
}

// ===== STEP 2: PLAN SELECTION =====
function selectPlan(card) {
    document.querySelectorAll('.promote-plan-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('selectedPlanId').value = card.dataset.planId;
    document.getElementById('step2NextBtn').disabled = false;
    selectedPlan = {
        id: card.dataset.planId,
        price: card.dataset.planPrice,
        name: card.querySelector('h3').textContent,
        duration: card.querySelector('.plan-duration').textContent
    };
}

// ===== STEP 3: SUMMARY & PAY =====
function populateSummary() {
    document.getElementById('orderSummary').innerHTML = `
        <div class="summary-row"><span><i class="fas fa-hospital"></i> Listing</span><strong>${selectedListing.name}</strong></div>
        <div class="summary-row"><span><i class="fas fa-crown"></i> Plan</span><strong>${selectedPlan.name}</strong></div>
        <div class="summary-row"><span><i class="fas fa-calendar"></i> Duration</span><strong>${selectedPlan.duration}</strong></div>
        <div class="summary-row total"><span>Total Amount</span><strong>₹${parseInt(selectedPlan.price).toLocaleString('en-IN')}</strong></div>
    `;
    document.getElementById('payAmount').textContent = parseInt(selectedPlan.price).toLocaleString('en-IN');
}

function initiatePayment() {
    const name = document.getElementById('promoName').value.trim();
    const phone = document.getElementById('promoPhone').value.trim();
    const email = document.getElementById('promoEmail').value.trim();

    if (!name) {
        alert('Please enter your name.');
        return;
    }
    if (!phone || phone.length < 10) {
        alert('Please enter a valid 10-digit phone number.');
        return;
    }

    const btn = document.getElementById('payNowBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting to secure payment…';
    btn.disabled = true;

    // POST the order to our server, which records it and hands off to the active
    // payment gateway (Cashfree or PayU). On completion the gateway returns the
    // user to its callback/return handler → payment-success.php.
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'promotion_initiate.php';
    const fields = {
        listing_id: document.getElementById('selectedListingId').value,
        plan_id: document.getElementById('selectedPlanId').value,
        customer_name: name,
        customer_phone: phone,
        customer_email: email
    };
    for (const k in fields) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = k;
        input.value = fields[k];
        form.appendChild(input);
    }
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>
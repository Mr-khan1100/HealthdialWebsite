<?php
$currentPage = 'profile';
$pageTitle = 'My Profile';
$pageDesc = 'Manage your HealthDial account, listings and support.';

require_once 'includes/db.php';
require_once 'includes/seo.php';
require_once 'includes/user_auth.php';
require_once 'includes/firebase_config.php';

// Profile is for logged-in users only.
$hdUser = hd_require_login();

// Phone (2nd factor) state for the verification card.
$phoneVerified = !empty($hdUser['phone_verified']);
$verifyRequired = (($_GET['verify'] ?? '') === 'required') && !$phoneVerified;
$verifyReturn = hd_safe_return_url($_GET['return'] ?? '');
$fbConfig = hd_firebase_config();
$fbConfigured = hd_firebase_is_configured();

// Pull the user's own listings (any status) to show on the profile.
$myListings = [];
$conn = getDbConnection();
if ($conn) {
    $uid = (int) $hdUser['id'];
    $slugSelect = hd_db_has_column($conn, 'listings', 'slug') ? 'l.slug' : 'NULL AS slug';
    $sql = "SELECT l.id, l.name, l.address, l.city, l.status, l.created_at, $slugSelect,
                   c.name AS category_name,
                   li.image_path, li.is_external_url
            FROM listings l
            LEFT JOIN categories c ON c.id = l.category_id
            LEFT JOIN listing_images li ON li.listing_id = l.id AND li.is_primary = 1
            WHERE l.user_id = ?
            ORDER BY l.created_at DESC
            LIMIT 60";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $img = trim($row['image_path'] ?? '');
            if ($img !== '') {
                $row['image_url'] = (strpos($img, 'http') === 0 || intval($row['is_external_url']) === 1)
                    ? $img
                    : LISTING_IMAGE_BASE . $img;
            } else {
                $row['image_url'] = null;
            }
            $myListings[] = $row;
        }
        $stmt->close();
    }
}

$displayName = trim($hdUser['name'] ?? '') !== '' ? trim($hdUser['name']) : 'HealthDial User';
$initial = strtoupper(substr($displayName, 0, 1));
$contactLine = trim($hdUser['mobile'] ?? '') !== '' ? $hdUser['mobile'] : ($hdUser['email'] ?? '');
$hdReturnProfile = urlencode('profile.php');

require_once 'includes/icons.php';
require_once 'includes/header.php';
?>

<section class="section" style="padding-top:120px; min-height:70vh;">
    <div class="container">

        <!-- ===== Account header ===== -->
        <div class="profile-head">
            <div class="profile-avatar">
                <?php if (!empty($hdUser['profile_image'])): ?>
                <img src="<?= htmlspecialchars($hdUser['profile_image']) ?>" alt="<?= htmlspecialchars($displayName) ?>" />
                <?php else: ?>
                <span><?= htmlspecialchars($initial) ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-id">
                <h1><?= htmlspecialchars($displayName) ?></h1>
                <?php if ($contactLine !== ''): ?>
                <p><i class="fas <?= trim($hdUser['mobile'] ?? '') !== '' ? 'fa-phone' : 'fa-envelope' ?>"></i>
                    <?= htmlspecialchars($contactLine) ?></p>
                <?php endif; ?>
                <?php if (!empty($hdUser['email']) && trim($hdUser['mobile'] ?? '') !== ''): ?>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($hdUser['email']) ?></p>
                <?php endif; ?>
            </div>
            <a href="<?= $assetBase ?>/logout.php?return=index.php" class="profile-logout">
                <i class="fas fa-right-from-bracket"></i> Logout
            </a>
        </div>

        <!-- ===== Phone verification (2nd factor) ===== -->
        <div id="phoneCard" class="verify-card <?= $phoneVerified ? 'verify-card--done' : '' ?>">
            <?php if ($phoneVerified): ?>
            <div class="verify-row">
                <span class="verify-ic verify-ic--ok"><i class="fas fa-shield-check"></i></span>
                <div class="verify-txt">
                    <strong>Phone verified</strong>
                    <small>+91 <?= htmlspecialchars($hdUser['mobile'] ?? '') ?> &middot; your account is protected with 2-step
                        verification.</small>
                </div>
            </div>
            <?php elseif (!$fbConfigured): ?>
            <div class="verify-row">
                <span class="verify-ic verify-ic--warn"><i class="fas fa-triangle-exclamation"></i></span>
                <div class="verify-txt">
                    <strong>Phone verification unavailable</strong>
                    <small>Firebase isn't configured yet. Add it in <code>includes/firebase_config.php</code>.</small>
                </div>
            </div>
            <?php else: ?>
            <div class="verify-row">
                <span class="verify-ic verify-ic--warn"><i class="fas fa-mobile-screen-button"></i></span>
                <div class="verify-txt">
                    <strong>Verify your phone number</strong>
                    <small>
                        <?php if ($verifyRequired): ?>
                        You need a verified phone number before you can list, promote, claim or pay. It only takes a
                        minute.
                        <?php else: ?>
                        Add 2-step verification. A verified phone is required to list, promote, claim or pay.
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <div id="pvError" class="verify-alert verify-alert--err" style="display:none;"></div>
            <div id="pvNotice" class="verify-alert verify-alert--ok" style="display:none;"></div>

            <!-- Step 1: number -->
            <div id="pvStep1">
                <label class="verify-label">Mobile number</label>
                <div class="verify-phone-row">
                    <span class="verify-cc">+91</span>
                    <input type="tel" id="pvPhone" class="verify-input" placeholder="10-digit mobile number"
                        maxlength="10" inputmode="numeric" autocomplete="tel" />
                </div>
                <div id="pv-recaptcha" style="margin-top:12px; display:flex;"></div>
                <button id="pvSendBtn" class="btn btn-primary" style="margin-top:12px; width:100%;">
                    <i class="fas fa-paper-plane"></i> Send OTP
                </button>
            </div>

            <!-- Step 2: code -->
            <div id="pvStep2" style="display:none;">
                <p class="verify-otp-hint">Enter the 6-digit code sent to <strong id="pvPhoneLabel"></strong></p>
                <input type="text" id="pvCode" class="verify-input verify-otp" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;"
                    maxlength="6" inputmode="numeric" autocomplete="one-time-code" />
                <button id="pvVerifyBtn" class="btn btn-primary" style="margin-top:12px; width:100%;">
                    <i class="fas fa-check"></i> Verify &amp; secure my account
                </button>
                <button id="pvBackBtn" class="btn" style="margin-top:8px; width:100%; background:transparent;">Change
                    number</button>
            </div>
            <?php endif; ?>
        </div>

        <!-- ===== Quick actions ===== -->
        <div class="profile-actions">
            <a href="<?= $assetBase ?>/add-listing.php" class="profile-action">
                <span class="pa-icon" style="background:linear-gradient(135deg,#059669,#10b981);"><i
                        class="fas fa-plus"></i></span>
                <span class="pa-text"><strong>Add Listing</strong><small>List your business free</small></span>
            </a>
            <a href="<?= $assetBase ?>/promote.php" class="profile-action">
                <span class="pa-icon" style="background:linear-gradient(135deg,#f59e0b,#f97316);"><i
                        class="fas fa-bolt"></i></span>
                <span class="pa-text"><strong>Promote</strong><small>Boost your reach</small></span>
            </a>
            <a href="<?= $assetBase ?>/contact.php" class="profile-action">
                <span class="pa-icon" style="background:linear-gradient(135deg,#2563eb,#3b82f6);"><i
                        class="fas fa-headset"></i></span>
                <span class="pa-text"><strong>Support</strong><small>Get help or raise a ticket</small></span>
            </a>
        </div>

        <!-- ===== My listings ===== -->
        <div class="profile-listings">
            <div class="profile-section-head">
                <h2>My Listings <span class="pl-count"><?= count($myListings) ?></span></h2>
                <a href="<?= $assetBase ?>/add-listing.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i>
                    Add new</a>
            </div>

            <?php if (empty($myListings)): ?>
            <div class="profile-empty">
                <i class="fas fa-store"></i>
                <p>You haven't added any listings yet.</p>
                <a href="<?= $assetBase ?>/add-listing.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add your
                    first listing</a>
            </div>
            <?php else: ?>
            <div class="profile-listing-grid">
                <?php foreach ($myListings as $row):
                    $status = strtolower($row['status'] ?? 'pending');
                    $isApproved = ($status === 'approved');
                    $statusClass = $isApproved ? 'ok' : (in_array($status, ['rejected', 'blocked', 'inactive']) ? 'bad' : 'wait');
                    $url = $isApproved ? hd_listing_url([
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'address' => $row['address'],
                        'city' => $row['city'],
                        'slug' => $row['slug'] ?? null,
                    ], false) : null;
                    $cardTag = $url ? 'a' : 'div';
                    $href = $url ? ' href="' . htmlspecialchars($url) . '"' : '';
                ?>
                <<?= $cardTag ?><?= $href ?> class="profile-listing-card">
                    <div class="plc-image">
                        <?php if (!empty($row['image_url'])): ?>
                        <img src="<?= htmlspecialchars($row['image_url']) ?>"
                            alt="<?= htmlspecialchars($row['name']) ?>" loading="lazy" />
                        <?php else: ?>
                        <div class="plc-placeholder"><i class="fas fa-store"></i></div>
                        <?php endif; ?>
                        <span class="plc-status <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                    </div>
                    <div class="plc-body">
                        <strong><?= htmlspecialchars($row['name']) ?></strong>
                        <?php if (!empty($row['category_name'])): ?>
                        <small><i class="fas fa-tag"></i> <?= htmlspecialchars($row['category_name']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($row['city'])): ?>
                        <small><i class="fas fa-location-dot"></i> <?= htmlspecialchars($row['city']) ?></small>
                        <?php endif; ?>
                    </div>
                </<?= $cardTag ?>>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<style>
.profile-head {
    display: flex;
    align-items: center;
    gap: 18px;
    background: var(--glass, rgba(8, 16, 40, 0.6));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    border-radius: 20px;
    padding: 22px 24px;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.30);
}

[data-theme="light"] .profile-head {
    background: #fff;
}

.profile-avatar {
    flex: 0 0 auto;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.9rem;
    font-weight: 800;
    background: linear-gradient(135deg, #2563eb, #10b981);
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-id {
    flex: 1 1 auto;
    min-width: 0;
}

.profile-id h1 {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 4px;
}

.profile-id p {
    margin: 2px 0;
    font-size: .88rem;
    color: var(--text-secondary, #94a3b8);
}

.profile-id p i {
    width: 16px;
    margin-right: 4px;
}

.profile-logout {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 99px;
    font-weight: 700;
    font-size: .85rem;
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.35);
    background: rgba(239, 68, 68, 0.10);
    text-decoration: none;
}

.profile-logout:hover {
    background: rgba(239, 68, 68, 0.18);
}

.profile-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-top: 18px;
}

.profile-action {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border-radius: 16px;
    text-decoration: none;
    color: inherit;
    background: var(--glass, rgba(8, 16, 40, 0.6));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    transition: transform .15s, box-shadow .15s;
}

[data-theme="light"] .profile-action {
    background: #fff;
}

.profile-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
}

.pa-icon {
    flex: 0 0 auto;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.05rem;
}

.pa-text {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}

.pa-text strong {
    font-size: .98rem;
}

.pa-text small {
    font-size: .78rem;
    color: var(--text-muted, #94a3b8);
}

.profile-listings {
    margin-top: 30px;
}

.profile-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.profile-section-head h2 {
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0;
}

.pl-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    margin-left: 6px;
    border-radius: 99px;
    font-size: .78rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #2563eb, #10b981);
}

.btn-sm {
    padding: 8px 14px !important;
    font-size: .82rem !important;
}

.profile-empty {
    text-align: center;
    padding: 48px 20px;
    border: 1px dashed var(--glass-border, rgba(255, 255, 255, 0.18));
    border-radius: 18px;
    color: var(--text-secondary, #94a3b8);
}

.profile-empty i {
    font-size: 2.4rem;
    margin-bottom: 12px;
    color: var(--text-muted, #64748b);
}

.profile-empty p {
    margin-bottom: 16px;
}

.profile-listing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 16px;
}

.profile-listing-card {
    display: block;
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    background: var(--glass, rgba(8, 16, 40, 0.6));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    transition: transform .15s, box-shadow .15s;
}

[data-theme="light"] .profile-listing-card {
    background: #fff;
}

a.profile-listing-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 34px rgba(0, 0, 0, 0.28);
}

.plc-image {
    position: relative;
    height: 140px;
    background: rgba(255, 255, 255, 0.04);
}

.plc-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.plc-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--text-muted, #64748b);
}

.plc-status {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: .72rem;
    font-weight: 700;
    color: #fff;
    backdrop-filter: blur(4px);
}

.plc-status.ok {
    background: rgba(16, 185, 129, 0.9);
}

.plc-status.wait {
    background: rgba(245, 158, 11, 0.92);
}

.plc-status.bad {
    background: rgba(239, 68, 68, 0.92);
}

.plc-body {
    padding: 12px 14px 14px;
}

.plc-body strong {
    display: block;
    font-size: .95rem;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.plc-body small {
    display: block;
    font-size: .78rem;
    color: var(--text-muted, #94a3b8);
    margin-top: 2px;
}

.plc-body small i {
    width: 14px;
    margin-right: 3px;
}

@media (max-width: 640px) {
    .profile-head {
        flex-wrap: wrap;
        gap: 14px;
    }

    .profile-logout {
        order: 3;
        width: 100%;
        justify-content: center;
    }

    .profile-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<style>
.verify-card {
    margin-top: 18px;
    padding: 18px 20px;
    border-radius: 16px;
    background: var(--glass, rgba(8, 16, 40, 0.6));
    border: 1px solid rgba(245, 158, 11, 0.45);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
}

[data-theme="light"] .verify-card {
    background: #fff;
}

.verify-card--done {
    border-color: rgba(16, 185, 129, 0.45);
}

.verify-row {
    display: flex;
    align-items: center;
    gap: 14px;
}

.verify-ic {
    flex: 0 0 auto;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.1rem;
}

.verify-ic--ok {
    background: linear-gradient(135deg, #059669, #10b981);
}

.verify-ic--warn {
    background: linear-gradient(135deg, #f59e0b, #f97316);
}

.verify-txt {
    display: flex;
    flex-direction: column;
    line-height: 1.4;
}

.verify-txt strong {
    font-size: 1rem;
}

.verify-txt small {
    font-size: .82rem;
    color: var(--text-secondary, #94a3b8);
}

.verify-label {
    display: block;
    font-size: .8rem;
    font-weight: 600;
    color: var(--text-secondary, #94a3b8);
    margin: 16px 0 6px;
}

.verify-phone-row {
    display: flex;
    gap: 8px;
}

.verify-cc {
    display: flex;
    align-items: center;
    padding: 0 14px;
    border-radius: 12px;
    font-weight: 700;
    background: rgba(255, 255, 255, .06);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, .12));
}

.verify-input {
    width: 100%;
    padding: 13px 14px;
    border-radius: 12px;
    font-size: .95rem;
    font-family: inherit;
    background: rgba(255, 255, 255, .04);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, .14));
    color: var(--text, #f1f5f9);
    outline: none;
}

[data-theme="light"] .verify-input {
    background: #f8fafc;
    color: #0f172a;
}

.verify-input:focus {
    border-color: #2563eb;
}

.verify-otp {
    text-align: center;
    letter-spacing: .5em;
    font-size: 1.3rem;
    font-weight: 700;
}

.verify-otp-hint {
    font-size: .85rem;
    color: var(--text-secondary, #94a3b8);
    margin: 16px 0 10px;
}

.verify-alert {
    margin-top: 14px;
    padding: 11px 14px;
    border-radius: 12px;
    font-size: .84rem;
    line-height: 1.5;
}

.verify-alert--err {
    background: rgba(239, 68, 68, .1);
    border: 1px solid rgba(239, 68, 68, .3);
    color: #fca5a5;
}

.verify-alert--ok {
    background: rgba(16, 185, 129, .12);
    border: 1px solid rgba(16, 185, 129, .35);
    color: #34d399;
}
</style>

<?php if (!$phoneVerified && $fbConfigured): ?>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-auth-compat.js"></script>
<script>
(function () {
    var firebaseConfig = <?= json_encode([
        'apiKey' => $fbConfig['apiKey'],
        'authDomain' => $fbConfig['authDomain'],
        'projectId' => $fbConfig['projectId'],
        'storageBucket' => $fbConfig['storageBucket'],
        'messagingSenderId' => $fbConfig['messagingSenderId'],
        'appId' => $fbConfig['appId'],
    ], JSON_UNESCAPED_SLASHES) ?>;
    var RETURN_URL = <?= json_encode($verifyReturn) ?>;

    if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
    var auth = firebase.auth();
    var $ = function (id) { return document.getElementById(id); };
    var confirmationResult = null;

    function err(msg) { var e = $('pvError'); e.textContent = msg; e.style.display = 'block'; $('pvNotice').style.display = 'none'; }
    function clearMsg() { $('pvError').style.display = 'none'; $('pvNotice').style.display = 'none'; }
    function ok(msg) { var n = $('pvNotice'); n.textContent = msg; n.style.display = 'block'; $('pvError').style.display = 'none'; }

    function recaptcha() {
        if (!window._pvRecaptcha) {
            window._pvRecaptcha = new firebase.auth.RecaptchaVerifier('pv-recaptcha', { size: 'normal' });
            window._pvRecaptcha.render();
        }
        return window._pvRecaptcha;
    }
    function resetRecaptcha() {
        if (window._pvRecaptcha) { try { window._pvRecaptcha.clear(); } catch (e) {} window._pvRecaptcha = null; }
        recaptcha();
    }
    try { recaptcha(); } catch (e) {}

    function setBusy(btn, on, busyText, normalHtml) {
        btn.disabled = on;
        btn.innerHTML = on ? '<i class="fas fa-spinner fa-spin"></i> ' + busyText : normalHtml;
    }

    // Step 1 — ask OUR server for permission (rate limit), then Firebase sends the SMS.
    $('pvSendBtn').addEventListener('click', function () {
        clearMsg();
        var btn = this;
        var phone = ($('pvPhone').value || '').replace(/\D/g, '');
        if (phone.length !== 10) { err('Please enter a valid 10-digit mobile number.'); return; }

        setBusy(btn, true, 'Checking…', '');
        fetch('phone_otp_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ phone: phone })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.ok) {
                setBusy(btn, false, '', '<i class="fas fa-paper-plane"></i> Send OTP');
                err(res.error || 'Could not send the code right now.');
                return;
            }
            // Permission granted → Firebase sends the SMS.
            setBusy(btn, true, 'Sending…', '');
            auth.signInWithPhoneNumber('+91' + phone, recaptcha()).then(function (cr) {
                confirmationResult = cr;
                setBusy(btn, false, '', '<i class="fas fa-paper-plane"></i> Send OTP');
                $('pvPhoneLabel').textContent = '+91 ' + phone;
                $('pvStep1').style.display = 'none';
                $('pvStep2').style.display = 'block';
                $('pvCode').focus();
            }).catch(function (e) {
                setBusy(btn, false, '', '<i class="fas fa-paper-plane"></i> Send OTP');
                err(humanError(e));
                resetRecaptcha();
            });
        }).catch(function () {
            setBusy(btn, false, '', '<i class="fas fa-paper-plane"></i> Send OTP');
            err('Network error. Please try again.');
        });
    });

    // Step 2 — confirm code with Firebase, then link the number on OUR server.
    $('pvVerifyBtn').addEventListener('click', function () {
        clearMsg();
        var btn = this;
        var code = ($('pvCode').value || '').replace(/\D/g, '');
        if (code.length < 6) { err('Enter the 6-digit code.'); return; }
        if (!confirmationResult) { err('Please request a code again.'); return; }

        setBusy(btn, true, 'Verifying…', '');
        confirmationResult.confirm(code).then(function (result) {
            return result.user.getIdToken();
        }).then(function (idToken) {
            return fetch('phone_verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id_token: idToken })
            });
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.ok) {
                ok('Phone verified! Redirecting…');
                setTimeout(function () { window.location.href = RETURN_URL || 'profile.php'; }, 900);
            } else {
                setBusy(btn, false, '', '<i class="fas fa-check"></i> Verify &amp; secure my account');
                err(res.error || 'Could not verify. Please try again.');
            }
        }).catch(function (e) {
            setBusy(btn, false, '', '<i class="fas fa-check"></i> Verify &amp; secure my account');
            err(humanError(e));
        });
    });

    $('pvBackBtn').addEventListener('click', function () {
        confirmationResult = null;
        $('pvStep2').style.display = 'none';
        $('pvStep1').style.display = 'block';
        clearMsg();
    });

    function humanError(e) {
        var c = e && e.code ? e.code : '';
        if (c === 'auth/invalid-verification-code') return 'Incorrect OTP. Please try again.';
        if (c === 'auth/code-expired') return 'That code expired. Please request a new one.';
        if (c === 'auth/too-many-requests') return 'Too many attempts. Please try again later.';
        if (c === 'auth/invalid-phone-number') return 'Invalid phone number.';
        if (c === 'auth/invalid-app-credential' || c === 'auth/captcha-check-failed')
            return 'reCAPTCHA check failed. Please solve it and try again.';
        if (c === 'auth/unauthorized-domain')
            return 'This domain is not authorized in Firebase (Authentication → Settings → Authorized domains).';
        return (e && e.message) ? e.message : 'Something went wrong. Please try again.';
    }
})();
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

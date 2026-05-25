<?php
$currentPage = 'add-listing';
$pageTitle   = 'Add Your Medical Listing | HealthDial';
$pageDesc    = 'List your hospital, clinic, pharmacy, lab or medical facility on HealthDial for free. Reach thousands of patients searching for healthcare near them.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

$categories = [];
$catData = fetch_api_data(API_BASE . 'get_categories.php');
if ($catData && !empty($catData['success']) && !empty($catData['data'])) {
    $categories = $catData['data'];
}
?>

<style>
/* ===== ADD LISTING PAGE ===== */
.al-hero {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 55%, #0ea5e9 100%);
    padding: 120px 0 56px;
    position: relative;
    overflow: hidden;
}
.al-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
.al-hero-inner { position: relative; z-index: 1; text-align: center; color: #fff; }
.al-hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
    border-radius: 100px; padding: 6px 18px; font-size: 13px; font-weight: 600;
    margin-bottom: 20px; backdrop-filter: blur(8px);
}
.al-hero h1 { font-size: clamp(26px, 4vw, 46px); font-weight: 800; margin-bottom: 14px; line-height: 1.2; }
.al-hero p  { font-size: 16px; opacity: .88; max-width: 540px; margin: 0 auto 32px; line-height: 1.6; }
.al-hero-stats { display: flex; gap: 40px; justify-content: center; flex-wrap: wrap; }
.al-hero-stat strong { display: block; font-size: 26px; font-weight: 800; }
.al-hero-stat span   { font-size: 12px; opacity: .75; }

/* Wrapper */
.al-page { background: #f1f5f9; padding: 48px 0 96px; }
.al-form-wrap { max-width: 740px; margin: 0 auto; }

/* Tips */
.al-tips {
    background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 16px; padding: 20px 22px; margin-bottom: 28px;
}
.al-tips-title { font-size: 14px; font-weight: 700; color: #1e40af; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.al-tip { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #1e40af; margin-bottom: 8px; }
.al-tip:last-child { margin-bottom: 0; }
.al-tip i { color: #2563eb; margin-top: 1px; flex-shrink: 0; }

/* Error banner */
.al-error {
    display: none; align-items: center; gap: 10px;
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
    padding: 14px 18px; color: #dc2626; font-size: 14px; font-weight: 500;
    margin-bottom: 20px;
}
.al-error.show { display: flex; }

/* Cards */
.al-card {
    background: #fff; border-radius: 20px; border: 1px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 20px; overflow: hidden;
    transition: box-shadow .2s, border-color .2s;
}
.al-card:focus-within { box-shadow: 0 4px 20px rgba(37,99,235,.1); border-color: #bfdbfe; }
.al-card-head {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 22px; border-bottom: 1px solid #f1f5f9;
}
.al-card-icon {
    width: 40px; height: 40px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;
}
.al-card-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }
.al-card-sub   { font-size: 12px; color: #64748b; margin: 2px 0 0; }
.al-card-body  { padding: 22px; }

/* Fields */
.al-f { margin-bottom: 18px; }
.al-f:last-child { margin-bottom: 0; }
.al-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
label.al-lbl { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 7px; }
label.al-lbl .req { color: #ef4444; margin-left: 2px; }
.al-input, .al-sel, .al-txt {
    width: 100%; padding: 11px 14px; border: 1.5px solid #e2e8f0; border-radius: 11px;
    font-family: inherit; font-size: 14px; color: #0f172a; background: #f8fafc;
    transition: border-color .18s, box-shadow .18s, background .18s; outline: none; box-sizing: border-box;
}
.al-input:focus, .al-sel:focus, .al-txt:focus {
    border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
.al-input::placeholder, .al-txt::placeholder { color: #94a3b8; }
.al-sel {
    appearance: none; cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px;
}
.al-txt { resize: vertical; min-height: 96px; }
.al-char { font-size: 11px; color: #94a3b8; text-align: right; margin-top: 4px; }

/* GPS row */
.al-gps-row { display: flex; gap: 10px; }
.al-gps-row .al-input { flex: 1; background: #f1f5f9; }
.al-gps-btn {
    flex-shrink: 0; height: 44px; padding: 0 14px;
    background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 11px;
    color: #2563eb; font-size: 13px; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 6px; transition: all .2s; white-space: nowrap;
}
.al-gps-btn:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
.al-gps-btn:disabled { opacity: .6; cursor: default; }
.al-gps-status { font-size: 12px; margin-top: 6px; display: none; }

/* 24x7 toggle */
.al-24-toggle {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; background: #f8fafc; border: 1.5px solid #e2e8f0;
    border-radius: 11px; cursor: pointer; margin-bottom: 14px; user-select: none;
    transition: border-color .2s;
}
.al-24-toggle:hover { border-color: #9333ea; }
.al-24-toggle input[type="checkbox"] { width: 18px; height: 18px; accent-color: #9333ea; cursor: pointer; flex-shrink: 0; }
.al-24-lbl strong { display: block; font-size: 14px; font-weight: 600; color: #0f172a; }
.al-24-lbl small  { font-size: 12px; color: #64748b; }

/* Photo upload */
.al-drop {
    border: 2px dashed #cbd5e1; border-radius: 14px; padding: 32px 20px;
    text-align: center; background: #f8fafc; cursor: pointer; position: relative;
    transition: border-color .2s, background .2s;
}
.al-drop:hover, .al-drop.over { border-color: #2563eb; background: #eff6ff; }
.al-drop input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.al-drop-icon { width: 54px; height: 54px; background: #eff6ff; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; color: #2563eb; font-size: 22px; }
.al-drop h4 { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
.al-drop p  { font-size: 13px; color: #64748b; margin: 0; }
.al-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin-top: 18px; }
.al-photo-thumb {
    position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden;
    border: 2px solid transparent; cursor: pointer; transition: border-color .2s;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.al-photo-thumb.cover { border-color: #2563eb; }
.al-photo-thumb img  { width: 100%; height: 100%; object-fit: cover; display: block; }
.al-cover-badge {
    position: absolute; top: 5px; left: 5px; background: #2563eb; color: #fff;
    font-size: 9px; font-weight: 800; letter-spacing: .5px; padding: 2px 7px; border-radius: 100px;
}
.al-rm {
    position: absolute; top: 5px; right: 5px; width: 22px; height: 22px;
    background: rgba(0,0,0,.55); color: #fff; border: none; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 10px; transition: background .18s;
}
.al-rm:hover { background: #ef4444; }
.al-photo-hint { font-size: 12px; color: #64748b; text-align: center; margin-top: 10px; }

/* Submit */
.al-sub-card {
    background: #fff; border-radius: 20px; border: 1px solid #e2e8f0;
    padding: 28px 22px; text-align: center; box-shadow: 0 1px 4px rgba(0,0,0,.05);
}
.al-sub-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    background: linear-gradient(135deg, #2563eb, #0ea5e9); color: #fff;
    border: none; border-radius: 13px; padding: 15px 48px;
    font-size: 16px; font-weight: 700; cursor: pointer; min-width: 220px;
    transition: transform .2s, box-shadow .2s;
}
.al-sub-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,99,235,.32); }
.al-sub-btn:disabled { opacity: .6; cursor: default; transform: none; box-shadow: none; }
.al-sub-note { font-size: 13px; color: #64748b; margin-top: 12px; }

/* Success overlay */
.al-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 10000; align-items: center; justify-content: center; padding: 24px;
    backdrop-filter: blur(4px);
}
.al-overlay.show { display: flex; }
.al-success-box {
    background: #fff; border-radius: 24px; padding: 48px 36px;
    text-align: center; max-width: 420px; width: 100%;
    animation: alPop .38s cubic-bezier(.34,1.56,.64,1);
}
@keyframes alPop { from { transform: scale(.75); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.al-success-icon {
    width: 76px; height: 76px; border-radius: 50%;
    background: #dcfce7; color: #059669; font-size: 32px;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 22px;
}
.al-success-box h2 { font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 10px; }
.al-success-box p  { font-size: 15px; color: #64748b; margin-bottom: 28px; line-height: 1.6; }
.al-success-actions { display: flex; flex-direction: column; gap: 10px; align-items: center; }

@media (max-width: 640px) {
    .al-row { grid-template-columns: 1fr; }
    .al-hero { padding: 96px 0 44px; }
    .al-card-body { padding: 18px 16px; }
    .al-drop { padding: 24px 16px; }
    .al-sub-card { padding: 22px 16px; }
}
</style>

<!-- HERO -->
<section class="al-hero">
    <div class="container">
        <div class="al-hero-inner">
            <div class="al-hero-badge"><i class="fas fa-plus-circle"></i> Free to List</div>
            <h1>Add Your Medical Facility</h1>
            <p>Reach thousands of patients searching for healthcare near them. Verified listing, live within 24 hours.</p>
            <div class="al-hero-stats">
                <div class="al-hero-stat"><strong>3,43,000+</strong><span>Listings</span></div>
                <div class="al-hero-stat"><strong>500+</strong><span>Cities</span></div>
                <div class="al-hero-stat"><strong>24 hrs</strong><span>Approval</span></div>
            </div>
        </div>
    </div>
</section>

<!-- FORM -->
<div class="al-page">
<div class="container">
<div class="al-form-wrap">

    <!-- Tips -->
    <div class="al-tips">
        <div class="al-tips-title"><i class="fas fa-lightbulb"></i> Tips for a great listing</div>
        <div class="al-tip"><i class="fas fa-check-circle"></i> Add clear, well-lit photos of your facility entrance and interior</div>
        <div class="al-tip"><i class="fas fa-check-circle"></i> Include the full address with a nearby landmark so patients can find you easily</div>
        <div class="al-tip"><i class="fas fa-check-circle"></i> Use the GPS button to add your exact coordinates — helps with map navigation</div>
        <div class="al-tip"><i class="fas fa-check-circle"></i> Write a detailed description of your services and specialties</div>
    </div>

    <!-- Error -->
    <div class="al-error" id="alError">
        <i class="fas fa-exclamation-circle" style="flex-shrink:0;"></i>
        <span id="alErrorText"></span>
    </div>

    <form id="alForm" novalidate>

    <!-- 1. Basic Info -->
    <div class="al-card">
        <div class="al-card-head">
            <div class="al-card-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-clinic-medical"></i></div>
            <div><div class="al-card-title">Basic Information</div><div class="al-card-sub">About your facility</div></div>
        </div>
        <div class="al-card-body">
            <div class="al-f">
                <label class="al-lbl" for="alCat">Category <span class="req">*</span></label>
                <select class="al-sel" id="alCat" name="category_id" required>
                    <option value="">— Select a Category —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= intval($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="al-f">
                <label class="al-lbl" for="alName">Facility / Business Name <span class="req">*</span></label>
                <input class="al-input" type="text" id="alName" name="name" placeholder="e.g. Apollo Multi-Speciality Hospital" required maxlength="200" />
            </div>
            <div class="al-f">
                <label class="al-lbl" for="alDesc">Description <span class="req">*</span></label>
                <textarea class="al-txt" id="alDesc" name="description" placeholder="Describe your services, specialties, available facilities, doctors, equipment..." required rows="4" maxlength="2000"></textarea>
                <div class="al-char"><span id="alDescCount">0</span>/2000</div>
            </div>
        </div>
    </div>

    <!-- 2. Location -->
    <div class="al-card">
        <div class="al-card-head">
            <div class="al-card-icon" style="background:#f0fdf4;color:#059669;"><i class="fas fa-map-marker-alt"></i></div>
            <div><div class="al-card-title">Location</div><div class="al-card-sub">Where are you located?</div></div>
        </div>
        <div class="al-card-body">
            <div class="al-f">
                <label class="al-lbl" for="alAddr">Full Address <span class="req">*</span></label>
                <input class="al-input" type="text" id="alAddr" name="address" placeholder="Building, Street, Area, Landmark" required maxlength="400" />
            </div>
            <div class="al-f al-row">
                <div>
                    <label class="al-lbl" for="alCity">City <span class="req">*</span></label>
                    <input class="al-input" type="text" id="alCity" name="city" placeholder="e.g. Mumbai" required maxlength="100" />
                </div>
                <div>
                    <label class="al-lbl" for="alState">State</label>
                    <input class="al-input" type="text" id="alState" name="state" placeholder="e.g. Maharashtra" maxlength="100" />
                </div>
            </div>
            <div class="al-f">
                <label class="al-lbl">GPS Coordinates <span style="font-weight:400;color:#64748b;">(optional — improves navigation)</span></label>
                <div class="al-gps-row">
                    <input class="al-input" type="text" id="alLat" name="latitude"  placeholder="Latitude"  readonly />
                    <input class="al-input" type="text" id="alLng" name="longitude" placeholder="Longitude" readonly />
                    <button type="button" class="al-gps-btn" id="alGpsBtn" onclick="detectGPS()">
                        <i class="fas fa-location-crosshairs"></i> Detect
                    </button>
                </div>
                <div class="al-gps-status" id="alGpsStatus"></div>
            </div>
        </div>
    </div>

    <!-- 3. Contact -->
    <div class="al-card">
        <div class="al-card-head">
            <div class="al-card-icon" style="background:#fff7ed;color:#ea580c;"><i class="fas fa-phone-alt"></i></div>
            <div><div class="al-card-title">Contact Details</div><div class="al-card-sub">How patients can reach you</div></div>
        </div>
        <div class="al-card-body">
            <div class="al-f al-row">
                <div>
                    <label class="al-lbl" for="alMobile">Phone Number <span class="req">*</span></label>
                    <input class="al-input" type="tel" id="alMobile" name="mobile" placeholder="+91 98765 43210" required maxlength="15" />
                </div>
                <div>
                    <label class="al-lbl" for="alWA">WhatsApp Number</label>
                    <input class="al-input" type="tel" id="alWA" name="whatsapp" placeholder="Same as phone?" maxlength="15" />
                </div>
            </div>
            <div class="al-f">
                <label class="al-lbl" for="alEmail">Email Address</label>
                <input class="al-input" type="email" id="alEmail" name="email" placeholder="info@yourhospital.com" maxlength="200" />
            </div>
        </div>
    </div>

    <!-- 4. Hours -->
    <div class="al-card">
        <div class="al-card-head">
            <div class="al-card-icon" style="background:#fdf4ff;color:#9333ea;"><i class="fas fa-clock"></i></div>
            <div><div class="al-card-title">Business Hours</div><div class="al-card-sub">When are you open?</div></div>
        </div>
        <div class="al-card-body">
            <label class="al-24-toggle" for="al24">
                <input type="checkbox" id="al24" name="is_24x7" value="1" onchange="toggle24(this)" />
                <div class="al-24-lbl">
                    <strong><i class="fas fa-infinity" style="color:#9333ea;margin-right:6px;"></i>Open 24 Hours / 7 Days</strong>
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
    <div class="al-card">
        <div class="al-card-head">
            <div class="al-card-icon" style="background:#fff1f2;color:#e11d48;"><i class="fas fa-camera"></i></div>
            <div><div class="al-card-title">Photos <span class="req">*</span></div><div class="al-card-sub">Up to 5 photos — first is cover image</div></div>
        </div>
        <div class="al-card-body">
            <div class="al-drop" id="alDrop"
                ondragover="event.preventDefault();this.classList.add('over')"
                ondragleave="this.classList.remove('over')"
                ondrop="dropPhotos(event)">
                <input type="file" id="alFileInput" accept="image/*" multiple onchange="pickPhotos(this.files)" />
                <div class="al-drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <h4>Drag & drop photos here</h4>
                <p>Or click to browse &nbsp;·&nbsp; JPG, PNG, WebP &nbsp;·&nbsp; Max 5MB each &nbsp;·&nbsp; Up to 5 photos</p>
            </div>
            <div class="al-photo-grid" id="alGrid"></div>
            <div class="al-photo-hint" id="alHint" style="display:none;"></div>
        </div>
    </div>

    <!-- Submit -->
    <div class="al-sub-card">
        <button type="submit" class="al-sub-btn" id="alSubBtn">
            <i class="fas fa-paper-plane"></i> Submit for Review
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

<!-- Success overlay -->
<div class="al-overlay" id="alOverlay">
    <div class="al-success-box">
        <div class="al-success-icon"><i class="fas fa-check"></i></div>
        <h2>Listing Submitted!</h2>
        <p>Thank you! Our team will review your listing and publish it within 24 hours. You'll reach thousands of patients searching for healthcare in your area.</p>
        <div class="al-success-actions">
            <a href="index.php" class="al-sub-btn" style="text-decoration:none;">
                <i class="fas fa-home"></i> Back to Home
            </a>
            <a href="add-listing.php" onclick="resetForm(event)" style="font-size:14px;color:#2563eb;font-weight:600;text-decoration:none;">
                <i class="fas fa-plus"></i> Add Another Listing
            </a>
        </div>
    </div>
</div>

<script>
/* ===== Add Listing JS ===== */
let photos = []; // [{ file, dataUrl }]

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
    const btn    = document.getElementById('alGpsBtn');
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
            setGpsStatus('<i class="fas fa-check-circle"></i> Coordinates set. You can also type them manually.', true);
        },
        () => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Retry';
            // Allow manual entry
            ['alLat','alLng'].forEach(id => {
                const el = document.getElementById(id);
                el.removeAttribute('readonly');
                el.style.background = '';
            });
            setGpsStatus('<i class="fas fa-exclamation-circle"></i> Could not detect. Please enter coordinates manually.', false);
        },
        { enableHighAccuracy: true, timeout: 12000 }
    );
}
function setGpsStatus(html, ok) {
    const el = document.getElementById('alGpsStatus');
    el.style.display = 'block';
    el.innerHTML = html;
    el.style.color = ok === true ? '#059669' : ok === false ? '#ef4444' : '#64748b';
}

// Photo handling
function pickPhotos(files) { addPhotos(Array.from(files)); }
function dropPhotos(e) {
    e.preventDefault();
    document.getElementById('alDrop').classList.remove('over');
    addPhotos(Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')));
}

function addPhotos(files) {
    const slots = 5 - photos.length;
    if (slots <= 0) { showError('Maximum 5 photos allowed.'); return; }
    files.slice(0, slots).forEach(file => {
        if (file.size > 5 * 1024 * 1024) { showError('"' + file.name + '" exceeds 5MB limit.'); return; }
        const reader = new FileReader();
        reader.onload = e => { photos.push({ file, dataUrl: e.target.result }); renderGrid(); };
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
        hint.innerHTML = '<i class="fas fa-images" style="color:#2563eb;margin-right:5px;"></i>'
            + photos.length + ' photo' + (photos.length > 1 ? 's' : '') + ' selected'
            + (photos.length > 1 ? ' &nbsp;·&nbsp; click any photo to set it as cover' : '');
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
        document.getElementById('alDrop').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    const btn = document.getElementById('alSubBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';

    try {
        const city    = document.getElementById('alCity').value.trim();
        const state   = document.getElementById('alState').value.trim();
        const address = document.getElementById('alAddr').value.trim();
        const is24    = document.getElementById('al24').checked;

        const payload = {
            category_id: document.getElementById('alCat').value,
            name:        document.getElementById('alName').value.trim(),
            description: document.getElementById('alDesc').value.trim(),
            address,
            city,
            state,
            latitude:    document.getElementById('alLat').value  || '0',
            longitude:   document.getElementById('alLng').value  || '0',
            mobile:      document.getElementById('alMobile').value.trim(),
            whatsapp:    document.getElementById('alWA').value.trim(),
            email:       document.getElementById('alEmail').value.trim(),
            open_time:   is24 ? '00:00:00' : document.getElementById('alOpen').value,
            close_time:  is24 ? '00:00:00' : document.getElementById('alClose').value,
            is_24x7:     is24 ? '1' : '0',
            images: photos.map((p, i) => ({
                data:       p.dataUrl,
                name:       p.file.name,
                is_primary: i === 0 ? '1' : '0'
            }))
        };

        const res  = await fetch('add-listing-submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('alOverlay').classList.add('show');
        } else {
            showError(data.message || 'Submission failed. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit for Review';
        }
    } catch (err) {
        showError('Network error. Please check your connection and try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit for Review';
    }
});

function showError(msg) {
    const el = document.getElementById('alError');
    document.getElementById('alErrorText').textContent = msg;
    el.classList.add('show');
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function hideError() { document.getElementById('alError').classList.remove('show'); }

function resetForm(e) {
    if (e) e.preventDefault();
    document.getElementById('alOverlay').classList.remove('show');
    document.getElementById('alForm').reset();
    photos = [];
    renderGrid();
    document.getElementById('alHoursRow').style.display = 'grid';
    document.getElementById('alDescCount').textContent = '0';
    document.getElementById('alSubBtn').disabled = false;
    document.getElementById('alSubBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Submit for Review';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php require_once 'includes/footer.php'; ?>

<?php
$currentPage = 'edit-listing';
$pageTitle = 'Edit Listing';
$pageDesc = 'Update your HealthDial listing details.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/seo.php';
require_once 'includes/user_auth.php';

// Only the logged-in, phone-verified owner can edit (2-step).
$hdUser = hd_require_phone_verified();

$listingId = intval($_GET['id'] ?? 0);
$conn = getDbConnection();
if (!$conn || $listingId <= 0) {
    header('Location: profile.php');
    exit;
}

// Fetch the listing and enforce ownership.
$stmt = $conn->prepare("SELECT * FROM listings WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $listingId);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$listing) {
    header('Location: profile.php');
    exit;
}
if ((int) $listing['user_id'] !== (int) $hdUser['id']) {
    // Not the owner — bounce back to the listing.
    header('Location: listing-detail.php?id=' . $listingId);
    exit;
}

// Categories for the dropdown.
$categories = [];
$catRes = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Existing images.
$images = [];
$imgStmt = $conn->prepare("SELECT id, image_path, is_primary, is_external_url FROM listing_images WHERE listing_id = ? ORDER BY is_primary DESC, id ASC");
$imgStmt->bind_param('i', $listingId);
$imgStmt->execute();
$imgRes = $imgStmt->get_result();
while ($row = $imgRes->fetch_assoc()) {
    $path = trim($row['image_path'] ?? '');
    if ($path === '') {
        continue;
    }
    $url = (strpos($path, 'http') === 0 || (int) $row['is_external_url'] === 1) ? $path : LISTING_IMAGE_BASE . $path;
    $images[] = ['type' => 'existing', 'id' => (int) $row['id'], 'url' => $url];
}
$imgStmt->close();

// Opening hours (per-day), with sensible defaults if none stored.
$parsedHours = hd_parse_opening_hours($listing['opening_hours'] ?? null);
$is24 = !empty($listing['is_24x7']);
$days = [
    'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
    'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
];

require_once 'includes/header.php';
?>

<section class="section" style="padding-top:120px; min-height:70vh;">
    <div class="container" style="max-width:760px;">
        <div class="el-head">
            <h1>Edit listing</h1>
            <p>Update your details below. Changes go live immediately.</p>
        </div>

        <div class="el-alert" id="elError" style="display:none;"></div>

        <form id="elForm" novalidate>
            <input type="hidden" id="elId" value="<?= (int) $listing['id'] ?>">

            <!-- Basics -->
            <div class="el-card">
                <h2 class="el-card-title"><i class="fas fa-circle-info"></i> Business details</h2>

                <label class="el-label" for="elCat">Category</label>
                <select id="elCat" class="el-input">
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= ((int) $c['id'] === (int) $listing['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <label class="el-label" for="elName">Name</label>
                <input type="text" id="elName" class="el-input" maxlength="150"
                    value="<?= htmlspecialchars($listing['name'] ?? '') ?>">

                <label class="el-label" for="elDesc">Description</label>
                <textarea id="elDesc" class="el-input" rows="4" maxlength="2000"><?= htmlspecialchars($listing['description'] ?? '') ?></textarea>
            </div>

            <!-- Location -->
            <div class="el-card">
                <h2 class="el-card-title"><i class="fas fa-location-dot"></i> Location</h2>

                <label class="el-label" for="elAddr">Address</label>
                <input type="text" id="elAddr" class="el-input" value="<?= htmlspecialchars($listing['address'] ?? '') ?>">

                <label class="el-label" for="elCity">City</label>
                <input type="text" id="elCity" class="el-input" value="<?= htmlspecialchars($listing['city'] ?? '') ?>">

                <div class="el-row">
                    <div>
                        <label class="el-label" for="elLat">Latitude</label>
                        <input type="text" id="elLat" class="el-input" value="<?= htmlspecialchars($listing['latitude'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="el-label" for="elLng">Longitude</label>
                        <input type="text" id="elLng" class="el-input" value="<?= htmlspecialchars($listing['longitude'] ?? '') ?>">
                    </div>
                </div>
                <button type="button" class="btn" id="elGpsBtn" onclick="elDetectGPS()"
                    style="margin-top:10px;background:rgba(37,99,235,.12);color:#60a5fa;border:1px solid rgba(37,99,235,.35);">
                    <i class="fas fa-location-crosshairs"></i> Use my current location
                </button>
                <div class="el-gps-status" id="elGpsStatus" style="display:none;"></div>
            </div>

            <!-- Contact -->
            <div class="el-card">
                <h2 class="el-card-title"><i class="fas fa-phone"></i> Contact</h2>

                <label class="el-label" for="elMobile">Mobile</label>
                <input type="tel" id="elMobile" class="el-input" value="<?= htmlspecialchars($listing['mobile'] ?? '') ?>">

                <label class="el-label" for="elWA">WhatsApp <span style="font-weight:400;opacity:.6;">(optional)</span></label>
                <input type="tel" id="elWA" class="el-input" value="<?= htmlspecialchars($listing['whatsapp'] ?? '') ?>">

                <label class="el-label" for="elEmail">Email <span style="font-weight:400;opacity:.6;">(optional)</span></label>
                <input type="email" id="elEmail" class="el-input" value="<?= htmlspecialchars($listing['email'] ?? '') ?>">
            </div>

            <!-- Hours -->
            <div class="el-card">
                <h2 class="el-card-title"><i class="fas fa-clock"></i> Business hours</h2>

                <label class="el-24" for="el24">
                    <input type="checkbox" id="el24" onchange="elToggle24(this)" <?= $is24 ? 'checked' : '' ?>>
                    <span><strong><i class="fas fa-infinity"></i> Open 24 Hours / 7 Days</strong></span>
                </label>

                <div id="elDaysWrap" style="<?= $is24 ? 'display:none;' : '' ?>">
                    <div class="el-days-tools">
                        <span class="el-days-hint">Untick a day to mark it closed.</span>
                        <button type="button" class="el-copy-btn" onclick="elCopyHoursToAll()">
                            <i class="fas fa-copy"></i> Apply 1st day to all
                        </button>
                    </div>
                    <?php foreach ($days as $dk => $dl):
                        $dayData = $parsedHours[$dk] ?? null;
                        $closed  = $dayData ? !empty($dayData['closed']) : ($dk === 'sun');
                        $slots   = ($dayData && empty($dayData['closed']) && !empty($dayData['slots']))
                            ? $dayData['slots']
                            : [['open' => '09:00', 'close' => '18:00']];
                    ?>
                    <div class="el-day-row<?= $closed ? ' is-closed' : '' ?>" data-day="<?= $dk ?>">
                        <label class="el-day-toggle">
                            <input type="checkbox" class="el-day-open" <?= $closed ? '' : 'checked' ?>
                                onchange="elToggleDay(this)">
                            <span class="el-day-name"><?= $dl ?></span>
                        </label>
                        <div class="el-day-slots">
                            <?php foreach ($slots as $sl): ?>
                            <div class="el-slot">
                                <input type="time" class="el-input el-slot-from" value="<?= htmlspecialchars($sl['open']) ?>">
                                <span class="el-day-sep">to</span>
                                <input type="time" class="el-input el-slot-to" value="<?= htmlspecialchars($sl['close']) ?>">
                                <button type="button" class="el-slot-rm" onclick="elRemoveSlot(this)"><i class="fas fa-times"></i></button>
                            </div>
                            <?php endforeach; ?>
                            <button type="button" class="el-add-slot" onclick="elAddSlot(this)">
                                <i class="fas fa-plus"></i> Add hours
                            </button>
                        </div>
                        <span class="el-day-closed-tag">Closed</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Photos -->
            <div class="el-card">
                <h2 class="el-card-title"><i class="fas fa-camera"></i> Photos <span style="font-weight:400;opacity:.6;">— first is cover, up to 5</span></h2>
                <div class="el-drop" onclick="document.getElementById('elFile').click();"
                    ondragover="event.preventDefault();this.classList.add('over')"
                    ondragleave="this.classList.remove('over')" ondrop="elDrop(event)">
                    <input type="file" id="elFile" accept="image/*" multiple style="display:none;"
                        onchange="elPick(this.files)">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Click or drag photos here · JPG/PNG/WebP · max 5MB each</span>
                </div>
                <div class="el-grid" id="elGrid"></div>
            </div>

            <div class="el-actions">
                <a href="listing-detail.php?id=<?= (int) $listing['id'] ?>" class="btn" style="background:transparent;border:1px solid var(--glass-border,rgba(255,255,255,.18));">Cancel</a>
                <button type="submit" class="btn btn-primary" id="elSubBtn"><i class="fas fa-floppy-disk"></i> Save changes</button>
            </div>
        </form>
    </div>
</section>

<style>
.el-head { margin-bottom: 18px; }
.el-head h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 4px; }
.el-head p { color: var(--text-secondary, #94a3b8); font-size: .9rem; }
.el-card {
    background: var(--glass, rgba(8, 16, 40, 0.6));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    border-radius: 16px;
    padding: 20px 22px;
    margin-bottom: 16px;
}
[data-theme="light"] .el-card { background: #fff; }
.el-card-title { font-size: 1.05rem; font-weight: 800; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; }
.el-card-title i { color: #60a5fa; }
.el-label { display: block; font-size: .8rem; font-weight: 600; color: var(--text-secondary, #94a3b8); margin: 14px 0 6px; }
.el-card-title + .el-label { margin-top: 0; }
.el-input {
    width: 100%; padding: 12px 14px; border-radius: 10px; font-size: .95rem; font-family: inherit;
    background: rgba(255, 255, 255, .04); border: 1px solid var(--glass-border, rgba(255, 255, 255, .14));
    color: var(--text, #f1f5f9); outline: none;
}
[data-theme="light"] .el-input { background: #f8fafc; color: #0f172a; }
.el-input:focus { border-color: #2563eb; }
textarea.el-input { resize: vertical; }
.el-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.el-gps-status { margin-top: 8px; font-size: .82rem; }
.el-alert {
    background: rgba(239, 68, 68, .1); border: 1px solid rgba(239, 68, 68, .3); color: #fca5a5;
    padding: 12px 14px; border-radius: 12px; font-size: .85rem; margin-bottom: 16px;
}
.el-24 { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0 4px; }
.el-24 input { width: 18px; height: 18px; accent-color: #9333ea; }
.el-24 i { color: #9333ea; margin-right: 4px; }
.el-days-tools { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin: 12px 0 4px; flex-wrap: wrap; }
.el-days-hint { font-size: 12px; color: var(--text-secondary, rgba(240, 246, 255, 0.6)); }
.el-copy-btn { background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .18); color: #60a5fa; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 8px; cursor: pointer; }
.el-copy-btn:hover { background: #2563eb; color: #fff; }
.el-day-row { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.07)); }
.el-day-row:last-child { border-bottom: none; }
.el-day-toggle { display: flex; align-items: center; gap: 8px; min-width: 120px; cursor: pointer; user-select: none; padding-top: 9px; }
.el-day-toggle input { width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer; }
.el-day-name { font-size: 14px; font-weight: 600; }
.el-day-slots { flex: 1; display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.el-slot { display: flex; align-items: center; gap: 8px; width: 100%; }
.el-slot .el-input { width: auto; flex: 1; padding: 8px 10px; min-width: 0; }
.el-day-sep { font-size: 12px; color: var(--text-muted, rgba(240, 246, 255, 0.4)); }
.el-slot-rm { flex-shrink: 0; width: 30px; height: 30px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, .18); background: transparent; color: #f87171; cursor: pointer; }
.el-slot-rm:hover { background: rgba(239, 68, 68, .15); }
.el-day-slots .el-slot:only-of-type .el-slot-rm { display: none; }
.el-add-slot { background: transparent; border: 1px dashed rgba(255, 255, 255, .25); color: #60a5fa; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 8px; cursor: pointer; }
.el-day-closed-tag { display: none; font-size: 13px; font-weight: 600; color: #f87171; flex: 1; padding-top: 9px; }
.el-day-row.is-closed .el-day-slots { display: none; }
.el-day-row.is-closed .el-day-closed-tag { display: inline; }
.el-day-row.is-closed .el-day-name { color: var(--text-muted, rgba(240, 246, 255, 0.4)); }
.el-drop { border: 2px dashed var(--glass-border, rgba(255, 255, 255, .2)); border-radius: 12px; padding: 22px; text-align: center; cursor: pointer; color: var(--text-secondary, #94a3b8); display: flex; flex-direction: column; align-items: center; gap: 8px; }
.el-drop.over { border-color: #2563eb; background: rgba(37, 99, 235, .06); }
.el-drop i { font-size: 1.6rem; color: #60a5fa; }
.el-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin-top: 12px; }
.el-thumb { position: relative; border-radius: 10px; overflow: hidden; aspect-ratio: 1; border: 2px solid transparent; cursor: pointer; background: rgba(255, 255, 255, .04); }
.el-thumb.cover { border-color: #2563eb; }
.el-thumb img { width: 100%; height: 100%; object-fit: cover; }
.el-thumb .el-cover-badge { position: absolute; bottom: 0; left: 0; right: 0; background: #2563eb; color: #fff; font-size: 10px; font-weight: 700; text-align: center; padding: 2px; }
.el-thumb .el-rm { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; border: none; background: rgba(0, 0, 0, .6); color: #fff; cursor: pointer; }
.el-actions { display: flex; gap: 12px; justify-content: flex-end; margin: 8px 0 30px; }
@media (max-width:560px){ .el-row { grid-template-columns: 1fr; } .el-actions { flex-direction: column-reverse; } .el-actions .btn { width: 100%; } }
</style>

<script>
(function () {
    var photos = <?= json_encode($images, JSON_UNESCAPED_SLASHES) ?>; // {type:'existing',id,url} | {type:'new',dataUrl}
    var $ = function (id) { return document.getElementById(id); };

    function showError(msg) { var e = $('elError'); e.textContent = msg; e.style.display = 'block'; e.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    function hideError() { $('elError').style.display = 'none'; }

    /* ---- Photos ---- */
    function renderGrid() {
        var grid = $('elGrid');
        grid.innerHTML = photos.map(function (p, i) {
            var src = p.type === 'existing' ? p.url : p.dataUrl;
            return '<div class="el-thumb ' + (i === 0 ? 'cover' : '') + '" onclick="elSetCover(' + i + ')" title="' + (i === 0 ? 'Cover photo' : 'Set as cover') + '">' +
                '<img src="' + src + '" alt="">' +
                (i === 0 ? '<div class="el-cover-badge">COVER</div>' : '') +
                '<button type="button" class="el-rm" onclick="event.stopPropagation();elRm(' + i + ')"><i class="fas fa-times"></i></button>' +
                '</div>';
        }).join('');
    }
    window.elSetCover = function (i) { if (i === 0) return; photos.unshift(photos.splice(i, 1)[0]); renderGrid(); };
    window.elRm = function (i) { photos.splice(i, 1); renderGrid(); };
    window.elPick = function (files) { elAdd(Array.prototype.slice.call(files)); };
    window.elDrop = function (e) {
        e.preventDefault();
        e.currentTarget.classList.remove('over');
        elAdd(Array.prototype.slice.call(e.dataTransfer.files).filter(function (f) { return f.type.indexOf('image/') === 0; }));
    };
    function elAdd(files) {
        var slots = 5 - photos.length;
        if (slots <= 0) { showError('Maximum 5 photos allowed.'); return; }
        files.slice(0, slots).forEach(function (file) {
            if (file.size > 5 * 1024 * 1024) { showError('"' + file.name + '" exceeds the 5MB limit.'); return; }
            var reader = new FileReader();
            reader.onload = function (ev) { photos.push({ type: 'new', dataUrl: ev.target.result }); renderGrid(); };
            reader.readAsDataURL(file);
        });
        $('elFile').value = '';
    }

    /* ---- Hours ---- */
    function slotHtml(from, to) {
        return '<div class="el-slot">' +
            '<input type="time" class="el-input el-slot-from" value="' + (from || '09:00') + '">' +
            '<span class="el-day-sep">to</span>' +
            '<input type="time" class="el-input el-slot-to" value="' + (to || '18:00') + '">' +
            '<button type="button" class="el-slot-rm" onclick="elRemoveSlot(this)"><i class="fas fa-times"></i></button>' +
            '</div>';
    }
    window.elToggle24 = function (cb) { $('elDaysWrap').style.display = cb.checked ? 'none' : ''; };
    window.elToggleDay = function (cb) { cb.closest('.el-day-row').classList.toggle('is-closed', !cb.checked); };
    window.elAddSlot = function (btn) { btn.insertAdjacentHTML('beforebegin', slotHtml('09:00', '18:00')); };
    window.elRemoveSlot = function (btn) {
        var slot = btn.closest('.el-slot');
        var wrap = slot.parentNode;
        if (wrap.querySelectorAll('.el-slot').length > 1) slot.remove();
    };
    window.elCopyHoursToAll = function () {
        var first = document.querySelector('#elDaysWrap .el-day-row');
        if (!first) return;
        var open = first.querySelector('.el-day-open').checked;
        var slots = [];
        first.querySelectorAll('.el-slot').forEach(function (s) {
            slots.push({ from: s.querySelector('.el-slot-from').value, to: s.querySelector('.el-slot-to').value });
        });
        document.querySelectorAll('#elDaysWrap .el-day-row').forEach(function (row, idx) {
            if (idx === 0) return;
            var cb = row.querySelector('.el-day-open');
            cb.checked = open;
            row.classList.toggle('is-closed', !open);
            var wrap = row.querySelector('.el-day-slots');
            wrap.querySelectorAll('.el-slot').forEach(function (s) { s.remove(); });
            var addBtn = wrap.querySelector('.el-add-slot');
            slots.forEach(function (sl) { addBtn.insertAdjacentHTML('beforebegin', slotHtml(sl.from, sl.to)); });
        });
    };
    function collectHours(is24) {
        var result = { opening_hours: null, openTime: '09:00', closeTime: '18:00', anyOpen: false };
        if (is24) return result;
        var oh = {}, firstFrom = null, firstTo = null;
        document.querySelectorAll('#elDaysWrap .el-day-row').forEach(function (row) {
            var key = row.dataset.day;
            if (row.querySelector('.el-day-open').checked) {
                var slots = [];
                row.querySelectorAll('.el-slot').forEach(function (s) {
                    var from = s.querySelector('.el-slot-from').value, to = s.querySelector('.el-slot-to').value;
                    if (from && to) slots.push({ open: from, close: to });
                });
                if (slots.length) { oh[key] = { slots: slots }; result.anyOpen = true; if (!firstFrom) { firstFrom = slots[0].open; firstTo = slots[0].close; } }
                else { oh[key] = { closed: true }; }
            } else { oh[key] = { closed: true }; }
        });
        result.opening_hours = oh;
        if (firstFrom) { result.openTime = firstFrom; result.closeTime = firstTo; }
        return result;
    }

    /* ---- GPS ---- */
    window.elDetectGPS = function () {
        var st = $('elGpsStatus');
        if (!navigator.geolocation) { st.style.display = 'block'; st.style.color = '#ef4444'; st.textContent = 'Geolocation not supported.'; return; }
        st.style.display = 'block'; st.style.color = '#64748b'; st.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Detecting…';
        navigator.geolocation.getCurrentPosition(function (pos) {
            $('elLat').value = pos.coords.latitude.toFixed(6);
            $('elLng').value = pos.coords.longitude.toFixed(6);
            st.style.color = '#059669'; st.innerHTML = '<i class="fas fa-check-circle"></i> Location set.';
        }, function () { st.style.color = '#ef4444'; st.innerHTML = '<i class="fas fa-exclamation-circle"></i> Could not detect — enter coordinates manually.'; }, { enableHighAccuracy: true, timeout: 12000 });
    };

    /* ---- Submit ---- */
    $('elForm').addEventListener('submit', function (e) {
        e.preventDefault();
        hideError();
        if (!$('elName').value.trim() || !$('elAddr').value.trim() || !$('elMobile').value.trim()) {
            showError('Name, address and mobile are required.'); return;
        }
        if (photos.length === 0) { showError('Please keep or add at least one photo.'); return; }

        var is24 = $('el24').checked;
        var hours = collectHours(is24);
        if (!is24 && !hours.anyOpen) { showError('Set hours for at least one day, or tick "Open 24 Hours".'); return; }

        // photos[0] is the cover. If it's a new image it's the first of new_images (index 0).
        var primary = photos[0].type === 'existing'
            ? { type: 'existing', id: photos[0].id }
            : { type: 'new', index: 0 };

        var payload = {
            listing_id: parseInt($('elId').value, 10),
            category_id: $('elCat').value,
            name: $('elName').value.trim(),
            description: $('elDesc').value.trim(),
            address: $('elAddr').value.trim(),
            city: $('elCity').value.trim(),
            latitude: $('elLat').value || '0',
            longitude: $('elLng').value || '0',
            mobile: $('elMobile').value.trim(),
            whatsapp: $('elWA').value.trim(),
            email: $('elEmail').value.trim(),
            is_24x7: is24 ? '1' : '0',
            open_time: is24 ? '00:00:00' : hours.openTime,
            close_time: is24 ? '00:00:00' : hours.closeTime,
            opening_hours: hours.opening_hours,
            kept_image_ids: photos.filter(function (p) { return p.type === 'existing'; }).map(function (p) { return p.id; }),
            new_images: photos.filter(function (p) { return p.type === 'new'; }).map(function (p) { return p.dataUrl; }),
            primary: primary
        };

        var btn = $('elSubBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        fetch('edit-listing-submit.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) { window.location.href = res.redirect || ('listing-detail.php?id=' + payload.listing_id); }
            else if (res.phone_required) { window.location.href = res.redirect || 'profile.php?verify=required'; }
            else { btn.disabled = false; btn.innerHTML = '<i class="fas fa-floppy-disk"></i> Save changes'; showError(res.message || 'Could not save changes.'); }
        }).catch(function () { btn.disabled = false; btn.innerHTML = '<i class="fas fa-floppy-disk"></i> Save changes'; showError('Network error. Please try again.'); });
    });

    renderGrid();
})();
</script>

<?php require_once 'includes/footer.php'; ?>

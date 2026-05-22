// ===== HealthDial Listings JS =====

const API_BASE = (window.LOOKING_CONFIG && window.LOOKING_CONFIG.apiBase) 
    || 'https://healthdial.com/HealthDial/Backend/api/';

let userLat = null;
let userLng = null;
let currentPage = 1;
let isLoading = false;
let hasMore = true;

document.addEventListener('DOMContentLoaded', () => {
    initGeolocation();
    initSearchAutocomplete();
    initPWA();
    
    // If on a page that loads listings
    if (document.getElementById('listingsGrid')) {
        loadListings();
    }
});

// ===== GEOLOCATION =====
function initGeolocation() {
    const stored = sessionStorage.getItem('hd_location');
    if (stored) {
        const loc = JSON.parse(stored);
        userLat = loc.lat;
        userLng = loc.lng;
        onLocationGranted();
        return;
    }

    if (sessionStorage.getItem('hd_location_dismissed')) return;

    // Check permission state first
    if (navigator.permissions) {
        navigator.permissions.query({ name: 'geolocation' }).then(result => {
            if (result.state === 'granted') {
                // Already granted, just get it
                requestLocation();
            } else if (result.state === 'prompt') {
                // Show a friendly prompt before browser prompt
                showLocationPrompt();
            }
            // If 'denied', do nothing
        }).catch(() => {
            // Fallback: just show our prompt
            showLocationPrompt();
        });
    } else {
        showLocationPrompt();
    }
}

function showLocationPrompt() {
    // Only show on pages with listings
    if (!document.getElementById('listingsGrid')) return;

    // Create a floating location prompt
    const prompt = document.createElement('div');
    prompt.id = 'locationPrompt';
    prompt.className = 'location-prompt';
    prompt.innerHTML = `
        <div class="location-prompt-inner">
            <div class="location-prompt-icon">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="location-prompt-text">
                <strong>Find nearby listings</strong>
                <span>Allow location to see results near you</span>
            </div>
            <button class="location-prompt-btn" onclick="requestLocation();document.getElementById('locationPrompt').remove();">
                <i class="fas fa-location-crosshairs"></i> Allow
            </button>
            <button class="location-prompt-close" onclick="dismissLocationBanner();document.getElementById('locationPrompt').remove();">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.body.appendChild(prompt);

    // Auto-dismiss after 15 seconds
    setTimeout(() => {
        if (document.getElementById('locationPrompt')) {
            document.getElementById('locationPrompt').remove();
        }
    }, 15000);
}

function requestLocation() {
    if (!navigator.geolocation) {
        console.warn('Geolocation not supported');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        pos => {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            sessionStorage.setItem('hd_location', JSON.stringify({ lat: userLat, lng: userLng }));
            // Remove any banners/prompts
            const banner = document.getElementById('locationBanner');
            if (banner) banner.style.display = 'none';
            const prompt = document.getElementById('locationPrompt');
            if (prompt) prompt.remove();
            onLocationGranted();
            // Reverse geocode to fill city
            reverseGeocode(userLat, userLng);
        },
        err => {
            console.warn('Location denied:', err.message);
            const banner = document.getElementById('locationBanner');
            if (banner) banner.style.display = 'none';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function reverseGeocode(lat, lng) {
    // Use free Nominatim reverse geocoding
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`)
        .then(r => r.json())
        .then(data => {
            const city = data.address?.city || data.address?.town || data.address?.village || data.address?.state_district || '';
            if (city) {
                const cityInput = document.getElementById('lookingCityInput');
                if (cityInput && !cityInput.value) {
                    cityInput.value = city;
                    cityInput.style.color = 'var(--blue)';
                }
                sessionStorage.setItem('hd_city', city);
            }
        })
        .catch(() => {});
}

function dismissLocationBanner() {
    const banner = document.getElementById('locationBanner');
    if (banner) banner.style.display = 'none';
    sessionStorage.setItem('hd_location_dismissed', '1');
}

function onLocationGranted() {
    // Update sort options on looking page
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.value = 'nearest';
        loadListings();
    }

    // Update "View All Nearby" link
    const nearYouLink = document.getElementById('nearYouLink');
    if (nearYouLink) {
        nearYouLink.href = `looking.php?lat=${userLat}&lng=${userLng}`;
    }
}

// ===== NEAR YOU (listings.php) =====
async function loadNearYou() {
    const section = document.getElementById('nearYouSection');
    const grid = document.getElementById('nearYouGrid');
    if (!section || !grid || !userLat) return;

    try {
        const url = `${API_BASE}get_filtered_listings.php?lat=${userLat}&lng=${userLng}&limit=6&radius=50`;
        const resp = await fetch(url);
        let text = await resp.text();
        if (text.includes('}{')) {
            text = text.substr(text.lastIndexOf('}{') + 1);
        }
        const data = JSON.parse(text);

        if (data.success && data.data.listings.length > 0) {
            grid.innerHTML = data.data.listings.map(l => renderListingCard(l)).join('');
            section.style.display = 'block';
            document.getElementById('nearYouMore').style.display = 'block';
        }
    } catch (e) {
        console.error('Failed to load nearby:', e);
    }
}

// ===== LISTINGS LOADING (looking.php) =====
async function loadListings(append = false) {
    if (isLoading) return;
    isLoading = true;

    const grid = document.getElementById('listingsGrid');
    const emptyState = document.getElementById('emptyState');
    const loadMoreWrap = document.getElementById('loadMoreWrap');
    const countEl = document.getElementById('resultsCount');

    if (!grid) return;

    const config = window.LOOKING_CONFIG || {};
    const catId = config.activeCat || 0;
    const city = document.getElementById('lookingCityInput')?.value || config.activeCity || '';
    const search = document.getElementById('lookingSearchInput')?.value || '';
    const sort = document.getElementById('sortSelect')?.value || 'rating';

    let url = `${API_BASE}get_filtered_listings.php?page=${currentPage}&limit=12`;
    if (catId) url += `&category_id=${catId}`;
    if (city) url += `&city=${encodeURIComponent(city)}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (userLat && userLng) url += `&lat=${userLat}&lng=${userLng}`;
    if (sort === 'nearest' && userLat) url += `&radius=100`;

    try {
        const resp = await fetch(url);
        let text = await resp.text();
        if (text.includes('}{')) {
            text = text.substr(text.lastIndexOf('}{') + 1);
        }
        const data = JSON.parse(text);

        if (data.success) {
            const listings = data.data.listings || [];
            const sponsored = data.data.sponsored || [];
            const total = data.data.pagination?.total || 0;
            const totalPages = data.data.pagination?.totalPages || 1;

            // Combine sponsored + regular listings into one grid
            // Sponsored go first on page 1
            const combinedHtml = [];
            if (sponsored.length > 0 && currentPage === 1) {
                combinedHtml.push(...sponsored.map(l => renderListingCard(l, true)));
            }
            combinedHtml.push(...listings.map(l => renderListingCard(l)));

            // Render listings
            if (!append) {
                grid.innerHTML = combinedHtml.join('');
            } else {
                grid.insertAdjacentHTML('beforeend', listings.map(l => renderListingCard(l)).join(''));
            }

            // Update count
            if (countEl) countEl.textContent = `${total} listing${total !== 1 ? 's' : ''} found`;

            // Show/hide empty state
            if (emptyState) emptyState.style.display = listings.length === 0 && currentPage === 1 ? 'block' : 'none';

            // Show/hide load more
            hasMore = currentPage < totalPages;
            if (loadMoreWrap) loadMoreWrap.style.display = hasMore ? 'block' : 'none';

            // Trigger reveal animations
            initRevealForNew();
        }
    } catch (e) {
        console.error('Failed to load listings:', e);
        if (grid && !append) {
            grid.innerHTML = '<div class="card" style="text-align:center; padding:32px; grid-column:1/-1;"><p class="card-text">Failed to load listings. Please try again.</p></div>';
        }
    }

    isLoading = false;
}

function loadMore() {
    currentPage++;
    loadListings(true);
}

// ===== SEARCH =====
function handleSearch(e) {
    e.preventDefault();
    const search = document.getElementById('searchInput')?.value || '';
    const city = document.getElementById('cityInput')?.value || '';
    let url = 'looking.php?';
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (city) url += `city=${encodeURIComponent(city)}`;
    window.location.href = url;
}

function searchListings(e) {
    if (e) e.preventDefault();
    hideDropdown();
    const searchVal = document.getElementById('lookingSearchInput')?.value || '';
    if (searchVal.trim()) addSearchHistory(searchVal.trim());
    const config = window.LOOKING_CONFIG || {};
    // If we are on index.php (has categories directly above), do NOT redirect, just reload grid
    if (document.getElementById('categoryPills')) {
        currentPage = 1;
        loadListings();
    } else {
        // If we are on listings.php (old flow), redirect to looking.php
        handleSearch(e);
    }
}

function changeSortAndReload() {
    currentPage = 1;
    loadListings();
}

function slugifyUrlPart(value, fallback = 'listing') {
    const slug = String(value || '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-+/g, '-');

    return slug || fallback;
}

function citySlugFromListing(listing) {
    const city = String(listing.city || '').split(',')[0].trim();
    return slugifyUrlPart(city, 'india');
}

function addressSlugFromListing(listing) {
    const citySlug = citySlugFromListing(listing);
    const skipped = new Set([
        citySlug,
        'india',
        'maharashtra',
        'delhi',
        'uttar-pradesh',
        'haryana',
        'gujarat',
        'karnataka',
        'tamil-nadu',
        'telangana',
        'west-bengal',
        'rajasthan',
        'madhya-pradesh',
        'punjab',
        'bihar',
        'ho'
    ]);
    const parts = String(listing.address || '')
        .replace(/\b\d{6}\b/g, '')
        .split(',')
        .map(part => slugifyUrlPart(part, ''))
        .filter(part => part && !skipped.has(part))
        .slice(0, 2);

    return parts.join('-');
}

function listingDetailUrl(listing) {
    if (listing.url && (/^\/[a-z0-9/-]+$/i.test(listing.url) || /^https:\/\/(www\.)?healthdial\.com\/[a-z0-9/-]+$/i.test(listing.url))) {
        return listing.url;
    }

    const city = citySlugFromListing(listing);
    let slug = listing.slug ? slugifyUrlPart(listing.slug) : slugifyUrlPart(listing.name);
    const area = addressSlugFromListing(listing);

    if (!listing.slug && area && !slug.includes(area)) {
        slug += `-${area}`;
    }

    if (!listing.slug && listing.id) {
        slug += `-${listing.id}`;
    }

    return `/${city}/${slug}`;
}

// ===== RENDER LISTING CARD =====
function renderListingCard(listing, isSponsored = false) {
    const catLower = (listing.category || '').toLowerCase();
    const placeholderData = getCategoryPlaceholder(catLower);
    const detailUrl = listingDetailUrl(listing);
    
    const placeholderHtml = `<div class="listing-placeholder-modern" style="background:${placeholderData.gradient}"><div class="placeholder-icon-ring">${placeholderData.svg}</div><div class="placeholder-name">${escHtml(listing.name).substring(0,30)}</div><div class="placeholder-cat">${escHtml(listing.category || 'Medical')}</div></div>`;
    
    const image = listing.image 
        ? `<img src="${listing.image}" alt="${escHtml(listing.name)}" loading="lazy" onerror="handleImgError(this)" data-cat="${escHtml(catLower)}" data-name="${escHtml(listing.name)}" data-catlabel="${escHtml(listing.category || 'Medical')}" /><div class="watermark-overlay"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>HealthDial</div>`
        : placeholderHtml;

    const distance = listing.distance != null 
        ? `<span class="listing-distance"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg> ${listing.distance} km</span>` 
        : '';

    const stars = renderStars(listing.rating || 0);
    const sponsoredBadge = isSponsored ? '<span class="listing-sponsored-badge">SPONSORED</span>' : '';

    const mobile = listing.mobile || '';

    const saved = getSavedListings();
    const isSaved = saved.includes(String(listing.id));
    const bookmarkClass = isSaved ? 'listing-bookmark saved' : 'listing-bookmark';

    // Build Google Maps directions URL
    const addr = encodeURIComponent((listing.name || '') + ' ' + (listing.address || '') + ' ' + (listing.city || ''));
    const mapsUrl = listing.latitude && listing.longitude 
        ? `https://www.google.com/maps/dir/?api=1&destination=${listing.latitude},${listing.longitude}`
        : `https://www.google.com/maps/search/?api=1&query=${addr}`;

    return `
    <div class="listing-card reveal">
        <a href="${escHtml(detailUrl)}" class="listing-card-image">
            ${image}
            ${sponsoredBadge}
            <button class="${bookmarkClass}" onclick="event.preventDefault();event.stopPropagation();toggleBookmark(${listing.id},this)" title="Save">
                <i class="${isSaved ? 'fas' : 'far'} fa-heart"></i>
            </button>
        </a>
        <div class="listing-card-body">
            <div class="listing-card-top">
                <span class="listing-category-badge">${escHtml(listing.category || '')}</span>
                <span class="listing-rating">${stars} <small>${listing.rating || 0} (${listing.reviewCount || 0})</small></span>
            </div>
            <a href="${escHtml(detailUrl)}" class="listing-card-name">${escHtml(listing.name)}</a>
            <p class="listing-card-address"><i class="fas fa-map-marker-alt"></i> ${escHtml(listing.address || '')}${listing.city ? ', ' + escHtml(listing.city) : ''}</p>
            ${distance}
            <div class="listing-card-actions">
                ${mobile ? `<a href="tel:${mobile}" class="listing-btn-call"><i class="fas fa-phone"></i> Call</a>` : ''}
                <a href="${mapsUrl}" target="_blank" class="listing-btn-directions"><i class="fas fa-directions"></i> Directions</a>
            </div>
        </div>
    </div>`;
}

function renderStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<svg width="12" height="12" viewBox="0 0 24 24" fill="${i <= Math.round(rating) ? '#f59e0b' : '#e2e8f0'}" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>`;
    }
    return stars;
}

function getCategoryPlaceholder(cat) {
    const categories = {
        hospital: {
            gradient: 'linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%)',
            svg: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M3 21h18M9 8h6M12 8v6M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16"/></svg>'
        },
        clinic: {
            gradient: 'linear-gradient(135deg, #047857 0%, #10b981 50%, #34d399 100%)',
            svg: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>'
        },
        pharmacy: {
            gradient: 'linear-gradient(135deg, #7c3aed 0%, #8b5cf6 50%, #a78bfa 100%)',
            svg: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12h6M12 9v6"/></svg>'
        },
        lab: {
            gradient: 'linear-gradient(135deg, #0e7490 0%, #06b6d4 50%, #22d3ee 100%)',
            svg: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M9 2v6l-4 8a3 3 0 003 3h8a3 3 0 003-3l-4-8V2"/><path d="M9 2h6"/><path d="M7 16h10"/></svg>'
        },
        ambulance: {
            gradient: 'linear-gradient(135deg, #dc2626 0%, #ef4444 50%, #f87171 100%)',
            svg: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/><path d="M7 8h4M9 6v4"/></svg>'
        },
        'blood bank': {
            gradient: 'linear-gradient(135deg, #be123c 0%, #e11d48 50%, #fb7185 100%)',
            svg: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>'
        }
    };
    
    // Find matching category
    for (const [key, val] of Object.entries(categories)) {
        if (cat.includes(key)) return val;
    }
    
    // Default medical
    return {
        gradient: 'linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #3b82f6 100%)',
        svg: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z"/><path d="M9 12h6M12 9v6"/></svg>'
    };
}

function handleImgError(img) {
    const cat = img.getAttribute('data-cat') || '';
    const name = img.getAttribute('data-name') || '';
    const catLabel = img.getAttribute('data-catlabel') || 'Medical';
    const data = getCategoryPlaceholder(cat);
    const parent = img.parentElement;
    // Remove the broken img and watermark
    parent.innerHTML = `<div class="listing-placeholder-modern" style="background:${data.gradient}"><div class="placeholder-icon-ring">${data.svg}</div><div class="placeholder-name">${name.substring(0,30)}</div><div class="placeholder-cat">${catLabel}</div></div>`;
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function initRevealForNew() {
    const els = document.querySelectorAll('.reveal:not(.visible)');
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.1 });
    els.forEach(el => obs.observe(el));
}

// ===== BOOKMARK / SAVE =====
function getSavedListings() {
    return JSON.parse(localStorage.getItem('hd_saved') || '[]');
}
function toggleBookmark(id, btn) {
    let saved = getSavedListings();
    const idStr = String(id);
    if (saved.includes(idStr)) {
        saved = saved.filter(s => s !== idStr);
        btn.classList.remove('saved');
        btn.querySelector('i').className = 'far fa-heart';
    } else {
        saved.push(idStr);
        btn.classList.add('saved');
        btn.querySelector('i').className = 'fas fa-heart';
    }
    localStorage.setItem('hd_saved', JSON.stringify(saved));
}

// ===== COMPARE =====
function addToCompare(listing) {
    let items = JSON.parse(localStorage.getItem('hd_compare') || '[]');
    if (items.length >= 3) { alert('You can compare up to 3 listings.'); return; }
    if (items.find(i => i.id == listing.id)) { alert('Already added to compare.'); return; }
    items.push(listing);
    localStorage.setItem('hd_compare', JSON.stringify(items));
    alert(listing.name + ' added to comparison. Go to Compare page to see results.');
}

// ===== SHARE =====
function shareListing(name, id) {
    const url = window.location.origin + listingDetailUrl({ name, id });
    const text = 'Check out ' + name + ' on HealthDial';
    if (navigator.share) {
        navigator.share({ title: name, text: text, url: url });
    } else {
        navigator.clipboard.writeText(url).then(() => alert('Link copied to clipboard!'));
    }
}

// ===== PREMIUM SEARCH AUTOCOMPLETE =====

const SEARCH_HISTORY_KEY = 'hd_search_history';
const MAX_HISTORY = 8;
let searchDebounceTimer = null;
let highlightedIndex = -1;
let currentDropdownItems = [];
let searchDropdown = null;
let cityDropdown = null;

function getSearchHistory() {
    return JSON.parse(localStorage.getItem(SEARCH_HISTORY_KEY) || '[]');
}

function addSearchHistory(term) {
    if (!term || term.length < 2) return;
    let history = getSearchHistory();
    history = history.filter(h => h.toLowerCase() !== term.toLowerCase());
    history.unshift(term);
    if (history.length > MAX_HISTORY) history = history.slice(0, MAX_HISTORY);
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(history));
}

function removeSearchHistory(term) {
    let history = getSearchHistory();
    history = history.filter(h => h !== term);
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(history));
}

function clearSearchHistory() {
    localStorage.removeItem(SEARCH_HISTORY_KEY);
}

const INDIAN_CITIES = [
    'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Ahmedabad', 'Chennai',
    'Kolkata', 'Pune', 'Jaipur', 'Lucknow', 'Kanpur', 'Nagpur',
    'Indore', 'Thane', 'Bhopal', 'Visakhapatnam', 'Patna', 'Vadodara',
    'Ghaziabad', 'Ludhiana', 'Agra', 'Nashik', 'Faridabad', 'Meerut',
    'Rajkot', 'Varanasi', 'Srinagar', 'Aurangabad', 'Dhanbad',
    'Amritsar', 'Noida', 'Gurgaon', 'Chandigarh', 'Coimbatore',
    'Jodhpur', 'Madurai', 'Guwahati', 'Dehradun', 'Ranchi',
    'Mysore', 'Udaipur', 'Bhubaneswar', 'Mangalore', 'Kochi',
    'Trivandrum', 'Raipur', 'Surat', 'Navi Mumbai', 'Goa'
];

const CATEGORY_SUGGESTIONS = [
    { text: 'Hospital', icon: 'fa-hospital', color: '#2563eb' },
    { text: 'Clinic', icon: 'fa-stethoscope', color: '#059669' },
    { text: 'Labs', icon: 'fa-flask', color: '#7c3aed' },
    { text: 'Medical Store', icon: 'fa-pills', color: '#dc2626' },
    { text: 'Ambulance', icon: 'fa-truck-medical', color: '#ea580c' },
    { text: 'Blood Bank', icon: 'fa-droplet', color: '#be123c' },
];

function initSearchAutocomplete() {
    const input = document.getElementById('lookingSearchInput');
    if (!input) return;

    const form = input.closest('form') || input.parentElement;
    const searchBar = input.closest('.home-search-bar') || input.closest('.looking-search-bar');

    // Create search dropdown
    searchDropdown = document.createElement('div');
    searchDropdown.className = 'search-dropdown';
    searchDropdown.id = 'searchDropdown';
    form.appendChild(searchDropdown);

    // City autocomplete
    const cityInput = document.getElementById('lookingCityInput');
    if (cityInput) {
        cityDropdown = document.createElement('div');
        cityDropdown.className = 'city-dropdown';
        cityDropdown.id = 'cityDropdown';
        form.appendChild(cityDropdown);

        cityInput.addEventListener('focus', () => showCityDropdown(cityInput));
        cityInput.addEventListener('input', () => showCityDropdown(cityInput));
        cityInput.addEventListener('blur', () => {
            setTimeout(() => {
                cityDropdown.classList.remove('visible');
            }, 200);
        });
    }

    // Show default dropdown on focus (history + categories)
    input.addEventListener('focus', () => {
        if (input.value.length === 0) {
            showDefaultDropdown();
        } else {
            triggerSearch(input.value);
        }
    });

    // Live search on input
    input.addEventListener('input', () => {
        const q = input.value.trim();
        highlightedIndex = -1;

        if (q.length === 0) {
            showDefaultDropdown();
            return;
        }

        if (q.length < 2) {
            // Show filtered categories only
            showFilteredSuggestions(q);
            return;
        }

        // Show loading then debounced API search
        showLoading();
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => triggerSearch(q), 300);
    });

    // Keyboard navigation
    input.addEventListener('keydown', (e) => {
        const items = searchDropdown.querySelectorAll('.sd-item');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
            updateHighlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, -1);
            updateHighlight(items);
        } else if (e.key === 'Enter' && highlightedIndex >= 0) {
            e.preventDefault();
            items[highlightedIndex].click();
        } else if (e.key === 'Escape') {
            hideDropdown();
            input.blur();
        }
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (!form.contains(e.target)) {
            hideDropdown();
        }
    });
}

function updateHighlight(items) {
    items.forEach((item, i) => {
        item.classList.toggle('highlighted', i === highlightedIndex);
        if (i === highlightedIndex) {
            item.scrollIntoView({ block: 'nearest' });
        }
    });
}

function showDropdown() {
    if (!searchDropdown) return;
    searchDropdown.classList.add('visible');
    const bar = document.querySelector('.home-search-bar');
    if (bar) bar.classList.add('dropdown-open');
}

function hideDropdown() {
    if (!searchDropdown) return;
    searchDropdown.classList.remove('visible');
    const bar = document.querySelector('.home-search-bar');
    if (bar) bar.classList.remove('dropdown-open');
    highlightedIndex = -1;
}

function showLoading() {
    if (!searchDropdown) return;
    searchDropdown.innerHTML = `
        <div class="sd-loading">
            <div class="spinner"></div>
            Searching listings...
        </div>`;
    showDropdown();
}

function showDefaultDropdown() {
    if (!searchDropdown) return;
    let html = '';

    // Search history
    const history = getSearchHistory();
    if (history.length > 0) {
        html += `
            <div class="sd-section">
                <span class="sd-section-title"><i class="fas fa-clock"></i> Recent Searches</span>
                <button class="sd-section-action" onclick="clearSearchHistory();showDefaultDropdown()">Clear all</button>
            </div>`;
        history.slice(0, 4).forEach(term => {
            html += `
                <div class="sd-item" onclick="document.getElementById('lookingSearchInput').value='${escHtml(term)}';addSearchHistory('${escHtml(term)}');triggerSearch('${escHtml(term)}')">
                    <div class="sd-item-icon history"><i class="fas fa-clock"></i></div>
                    <div class="sd-item-text">
                        <div class="sd-item-title">${escHtml(term)}</div>
                    </div>
                    <button class="sd-item-remove" onclick="event.stopPropagation();removeSearchHistory('${escHtml(term)}');showDefaultDropdown()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>`;
        });
        html += '<div class="sd-divider"></div>';
    }

    // Popular categories
    html += `
        <div class="sd-section">
            <span class="sd-section-title"><i class="fas fa-fire"></i> Popular Categories</span>
        </div>`;
    CATEGORY_SUGGESTIONS.forEach(cat => {
        html += `
            <div class="sd-item" onclick="document.getElementById('lookingSearchInput').value='${cat.text}';addSearchHistory('${cat.text}');hideDropdown();searchListings(new Event('submit'))">
                <div class="sd-item-icon cat" style="background:${cat.color}10;color:${cat.color}">
                    <i class="fas ${cat.icon}"></i>
                </div>
                <div class="sd-item-text">
                    <div class="sd-item-title">${cat.text}</div>
                </div>
                <span class="sd-item-badge cat-badge">Category</span>
            </div>`;
    });

    // Keyboard hint footer
    html += `
        <div class="sd-footer">
            <span><kbd>↑</kbd> <kbd>↓</kbd> Navigate</span>
            <span><kbd>Enter</kbd> Select</span>
            <span><kbd>Esc</kbd> Close</span>
        </div>`;

    searchDropdown.innerHTML = html;
    showDropdown();
}

function showFilteredSuggestions(query) {
    if (!searchDropdown) return;
    const q = query.toLowerCase();
    const matches = CATEGORY_SUGGESTIONS.filter(c => c.text.toLowerCase().includes(q));
    const cityMatches = INDIAN_CITIES.filter(c => c.toLowerCase().includes(q)).slice(0, 3);

    if (matches.length === 0 && cityMatches.length === 0) {
        searchDropdown.innerHTML = `
            <div class="sd-empty">
                <i class="fas fa-search"></i>
                <p>Type at least 2 characters to search listings...</p>
            </div>`;
        showDropdown();
        return;
    }

    let html = '';
    if (matches.length > 0) {
        html += `<div class="sd-section"><span class="sd-section-title">Categories</span></div>`;
        matches.forEach(cat => {
            html += `
                <div class="sd-item" onclick="document.getElementById('lookingSearchInput').value='${cat.text}';addSearchHistory('${cat.text}');hideDropdown();searchListings(new Event('submit'))">
                    <div class="sd-item-icon cat" style="background:${cat.color}10;color:${cat.color}">
                        <i class="fas ${cat.icon}"></i>
                    </div>
                    <div class="sd-item-text"><div class="sd-item-title">${highlightMatch(cat.text, query)}</div></div>
                    <span class="sd-item-badge cat-badge">Category</span>
                </div>`;
        });
    }
    if (cityMatches.length > 0) {
        html += `<div class="sd-section"><span class="sd-section-title">Cities</span></div>`;
        cityMatches.forEach(city => {
            html += `
                <div class="sd-item" onclick="document.getElementById('lookingCityInput').value='${city}';document.getElementById('lookingSearchInput').focus();">
                    <div class="sd-item-icon city"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="sd-item-text"><div class="sd-item-title">${highlightMatch(city, query)}</div></div>
                    <span class="sd-item-badge city-badge">City</span>
                </div>`;
        });
    }
    searchDropdown.innerHTML = html;
    showDropdown();
}

async function triggerSearch(query) {
    if (!searchDropdown || !query || query.length < 2) return;

    try {
        const url = `${API_BASE}get_filtered_listings.php?search=${encodeURIComponent(query)}&limit=5`;
        const resp = await fetch(url);
        let text = await resp.text();
        if (text.includes('}{')) {
            text = text.substr(text.lastIndexOf('}{') + 1);
        }
        const data = JSON.parse(text);

        let html = '';

        // Category matches
        const catMatches = CATEGORY_SUGGESTIONS.filter(c => c.text.toLowerCase().includes(query.toLowerCase()));
        if (catMatches.length > 0) {
            catMatches.slice(0, 2).forEach(cat => {
                html += `
                    <div class="sd-item" onclick="document.getElementById('lookingSearchInput').value='${cat.text}';addSearchHistory('${cat.text}');hideDropdown();searchListings(new Event('submit'))">
                        <div class="sd-item-icon cat" style="background:${cat.color}10;color:${cat.color}">
                            <i class="fas ${cat.icon}"></i>
                        </div>
                        <div class="sd-item-text"><div class="sd-item-title">${highlightMatch(cat.text, query)}</div></div>
                        <span class="sd-item-badge cat-badge">Category</span>
                    </div>`;
            });
            html += '<div class="sd-divider"></div>';
        }

        // Live listing results
        if (data.success && data.data.listings && data.data.listings.length > 0) {
            const total = data.data.pagination?.total || data.data.listings.length;
            html += `<div class="sd-section"><span class="sd-section-title"><i class="fas fa-list"></i> Listings (${total} found)</span></div>`;
            data.data.listings.forEach(listing => {
                const detailUrl = listingDetailUrl(listing);
                const imgHtml = listing.image
                    ? `<img src="${listing.image}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\\'fas fa-hospital\\'></i>'" />`
                    : `<i class="fas fa-hospital"></i>`;
                html += `
                    <a href="${escHtml(detailUrl)}" class="sd-item" onclick="addSearchHistory('${escHtml(query)}')">
                        <div class="sd-item-icon listing">${imgHtml}</div>
                        <div class="sd-item-text">
                            <div class="sd-item-title">${highlightMatch(listing.name, query)}</div>
                            <div class="sd-item-sub">${escHtml(listing.category || '')} · ${escHtml(listing.address || listing.city || '')}</div>
                        </div>
                        ${listing.rating > 0 ? `<span class="sd-item-badge cat-badge">★ ${listing.rating}</span>` : ''}
                    </a>`;
            });

            // "View all results" link
            if (total > 5) {
                html += `
                    <div class="sd-item" onclick="addSearchHistory('${escHtml(query)}');hideDropdown();searchListings(new Event('submit'))" style="justify-content:center;color:var(--blue);font-weight:600;font-size:13px;gap:6px;">
                        <i class="fas fa-arrow-right"></i> View all ${total} results
                    </div>`;
            }
        } else if (!catMatches.length) {
            html += `
                <div class="sd-empty">
                    <i class="fas fa-search-minus"></i>
                    <p>No listings found for "<strong>${escHtml(query)}</strong>"</p>
                </div>`;
        }

        // Keyboard hint
        html += `
            <div class="sd-footer">
                <span><kbd>↑</kbd> <kbd>↓</kbd> Navigate</span>
                <span><kbd>Enter</kbd> Select</span>
                <span><kbd>Esc</kbd> Close</span>
            </div>`;

        searchDropdown.innerHTML = html;
        showDropdown();
    } catch (e) {
        console.error('Search autocomplete failed:', e);
        searchDropdown.innerHTML = `
            <div class="sd-empty">
                <i class="fas fa-exclamation-circle"></i>
                <p>Search failed. Try again.</p>
            </div>`;
        showDropdown();
    }
}

function highlightMatch(text, query) {
    if (!query) return escHtml(text);
    const escaped = escHtml(text);
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return escaped.replace(regex, '<strong style="color:var(--blue)">$1</strong>');
}

function showCityDropdown(input) {
    if (!cityDropdown) return;
    const q = input.value.toLowerCase();

    let cities;
    if (q.length === 0) {
        cities = INDIAN_CITIES.slice(0, 8);
    } else {
        cities = INDIAN_CITIES.filter(c => c.toLowerCase().includes(q)).slice(0, 8);
    }

    if (cities.length === 0) {
        cityDropdown.classList.remove('visible');
        return;
    }

    let html = `<div class="sd-section"><span class="sd-section-title"><i class="fas fa-map-marker-alt"></i> ${q ? 'Matching' : 'Popular'} Cities</span></div>`;
    cities.forEach(city => {
        html += `
            <div class="sd-item" onmousedown="event.preventDefault();document.getElementById('lookingCityInput').value='${city}';document.getElementById('cityDropdown').classList.remove('visible');">
                <div class="sd-item-icon city"><i class="fas fa-map-marker-alt"></i></div>
                <div class="sd-item-text"><div class="sd-item-title">${q ? highlightMatch(city, q) : city}</div></div>
            </div>`;
    });

    cityDropdown.innerHTML = html;
    cityDropdown.classList.add('visible');
}

// ===== APP INSTALL BANNER =====
function initPWA() {
    // Don't show on download page itself
    if (window.location.pathname.includes('download')) return;
    // Don't show if dismissed recently
    if (sessionStorage.getItem('hd_install_dismissed')) return;
    
    // Show app download banner after 10 seconds
    setTimeout(() => {
        const banner = document.createElement('div');
        banner.className = 'pwa-install-banner';
        banner.style.display = 'block';
        banner.innerHTML = `
            <div class="pwa-install-inner">
                <div class="pwa-install-icon"><img src="assets/images/icon.png" alt="HD" /></div>
                <div class="pwa-install-text"><strong>Install HealthDial</strong><span>Add to home screen for quick access</span></div>
                <a href="download.php" class="pwa-install-btn">Install</a>
                <button class="pwa-install-close" onclick="sessionStorage.setItem('hd_install_dismissed','1');this.parentElement.parentElement.remove()">&times;</button>
            </div>`;
        document.body.appendChild(banner);
    }, 10000);
}

// Language translation is now handled by Google Translate (see footer.php)

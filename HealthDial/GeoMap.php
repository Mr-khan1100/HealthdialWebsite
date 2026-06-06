<?php
require_once 'connection.inc.php';
requireLogin();

// Get users with location 
$usersGeo = [];
$uRes = $conn->query("SELECT id, name, state, latitude, longitude FROM users WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0 AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180");
if($uRes) while($u = $uRes->fetch_assoc()) $usersGeo[] = $u;

// Get listings with location
$listingsGeo = [];
$lRes = $conn->query("SELECT l.id, l.name, l.city, l.status, l.latitude, l.longitude, c.name as category FROM listings l LEFT JOIN categories c ON l.category_id = c.id WHERE l.latitude IS NOT NULL AND l.longitude IS NOT NULL AND l.latitude != 0 AND l.longitude != 0 AND l.latitude BETWEEN -90 AND 90 AND l.longitude BETWEEN -180 AND 180");
if($lRes) while($l = $lRes->fetch_assoc()) $listingsGeo[] = $l;

// State-wise distribution
$stateDistrib = [];
$stRes = $conn->query("SELECT state, COUNT(*) as cnt FROM users WHERE state != '' AND state IS NOT NULL GROUP BY state ORDER BY cnt DESC");
if($stRes) while($s = $stRes->fetch_assoc()) $stateDistrib[] = $s;

// City-wise listings
$cityDistrib = [];
$cRes = $conn->query("SELECT city, COUNT(*) as cnt FROM listings WHERE city != '' AND city IS NOT NULL GROUP BY city ORDER BY cnt DESC LIMIT 15");
if($cRes) while($c = $cRes->fetch_assoc()) $cityDistrib[] = $c;

// Status distribution
$statusApproved = (int)$conn->query("SELECT COUNT(*) as c FROM listings WHERE status='approved' AND latitude IS NOT NULL")->fetch_assoc()['c'];
$statusPending = (int)$conn->query("SELECT COUNT(*) as c FROM listings WHERE status='pending' AND latitude IS NOT NULL")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geolocation Map — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <style>
        #map { width:100%;height:500px;border-radius:var(--radius-lg);z-index:1; }
        .map-controls { position:absolute;top:16px;right:16px;z-index:1000;display:flex;flex-direction:column;gap:6px; }
        .map-btn { padding:8px 14px;border-radius:var(--radius-sm);font-size:12px;font-weight:600;border:none;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all var(--transition-fast);background:var(--bg-card);color:var(--text-primary);box-shadow:var(--shadow-sm); }
        .map-btn:hover { box-shadow:var(--shadow-md); }
        .map-btn.active { background:var(--primary);color:#fff; }
        .map-legend { position:absolute;bottom:16px;left:16px;z-index:1000;background:var(--bg-card);padding:12px 16px;border-radius:var(--radius-md);box-shadow:var(--shadow-md);font-size:11px; }
        .legend-item { display:flex;align-items:center;gap:8px;margin:4px 0; }
        .legend-dot { width:12px;height:12px;border-radius:50%; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-map-marked-alt" style="color:var(--primary);margin-right:8px;"></i>Geolocation Map</h1>
                    <p class="page-subtitle"><?php echo count($usersGeo); ?> users and <?php echo count($listingsGeo); ?> listings with location data</p>
                </div>

                <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
                    <div class="stat-card emerald fade-in">
                        <div class="stat-info"><h3>Users on Map</h3><p class="stat-value"><?php echo count($usersGeo); ?></p></div>
                        <div class="stat-icon emerald"><i class="fas fa-map-marker-alt"></i></div>
                    </div>
                    <div class="stat-card blue fade-in fade-in-delay-1">
                        <div class="stat-info"><h3>Listings on Map</h3><p class="stat-value"><?php echo count($listingsGeo); ?></p></div>
                        <div class="stat-icon blue"><i class="fas fa-hospital"></i></div>
                    </div>
                    <div class="stat-card amber fade-in fade-in-delay-2">
                        <div class="stat-info"><h3>States Covered</h3><p class="stat-value"><?php echo count($stateDistrib); ?></p></div>
                        <div class="stat-icon amber"><i class="fas fa-map"></i></div>
                    </div>
                    <div class="stat-card purple fade-in fade-in-delay-3">
                        <div class="stat-info"><h3>Cities Covered</h3><p class="stat-value"><?php echo count($cityDistrib); ?></p></div>
                        <div class="stat-icon purple"><i class="fas fa-city"></i></div>
                    </div>
                </div>

                <!-- Map -->
                <div class="card fade-in" style="position:relative;">
                    <div class="card-header">
                        <h3 class="card-title">Interactive Map</h3>
                        <div style="display:flex;gap:6px;">
                            <button class="map-btn active" id="btnMarkers" onclick="showMarkers()"><i class="fas fa-map-pin"></i> Markers</button>
                            <button class="map-btn" id="btnHeat" onclick="showHeatmap()"><i class="fas fa-fire"></i> Heatmap</button>
                            <button class="map-btn" id="btnCluster" onclick="showClusters()"><i class="fas fa-object-group"></i> Clusters</button>
                        </div>
                    </div>
                    <div style="position:relative;">
                        <div id="map"></div>
                        <div class="map-legend">
                            <strong style="font-size:12px;">Legend</strong>
                            <div class="legend-item"><div class="legend-dot" style="background:#10b981;"></div> Users</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#3b82f6;"></div> Approved Listings</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#f59e0b;"></div> Pending Listings</div>
                        </div>
                    </div>
                </div>

                <!-- Bottom: State & City Distribution -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-map" style="margin-right:8px;color:var(--primary);"></i>Users by State</h3></div>
                        <div style="max-height:320px;overflow-y:auto;">
                            <?php foreach($stateDistrib as $idx => $st): 
                                $maxCnt = !empty($stateDistrib) ? $stateDistrib[0]['cnt'] : 1;
                                $pct = round(($st['cnt'] / $maxCnt) * 100);
                            ?>
                            <div style="display:flex;align-items:center;gap:12px;padding:10px 24px;border-bottom:1px solid var(--border-light);">
                                <span style="width:20px;text-align:center;font-size:11px;font-weight:700;color:var(--text-muted);"><?php echo $idx+1; ?></span>
                                <div style="flex:1;">
                                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                        <span style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($st['state']); ?></span>
                                        <span style="font-size:12px;font-weight:700;color:var(--primary);"><?php echo $st['cnt']; ?></span>
                                    </div>
                                    <div style="height:4px;background:var(--border-light);border-radius:2px;overflow:hidden;">
                                        <div style="height:100%;width:<?php echo $pct; ?>%;background:var(--primary);border-radius:2px;transition:width 0.5s;"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if(empty($stateDistrib)): ?>
                            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No state data</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-city" style="margin-right:8px;color:var(--accent);"></i>Listings by City</h3></div>
                        <div style="max-height:320px;overflow-y:auto;">
                            <?php foreach($cityDistrib as $idx => $ct): 
                                $maxC = !empty($cityDistrib) ? $cityDistrib[0]['cnt'] : 1;
                                $pctC = round(($ct['cnt'] / $maxC) * 100);
                            ?>
                            <div style="display:flex;align-items:center;gap:12px;padding:10px 24px;border-bottom:1px solid var(--border-light);">
                                <span style="width:20px;text-align:center;font-size:11px;font-weight:700;color:var(--text-muted);"><?php echo $idx+1; ?></span>
                                <div style="flex:1;">
                                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                                        <span style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($ct['city']); ?></span>
                                        <span style="font-size:12px;font-weight:700;color:var(--accent);"><?php echo $ct['cnt']; ?></span>
                                    </div>
                                    <div style="height:4px;background:var(--border-light);border-radius:2px;overflow:hidden;">
                                        <div style="height:100%;width:<?php echo $pctC; ?>%;background:var(--accent);border-radius:2px;transition:width 0.5s;"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if(empty($cityDistrib)): ?>
                            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No city data</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Data
    const usersData = <?php echo json_encode(array_map(function($u) { return ['lat'=>(float)$u['latitude'],'lng'=>(float)$u['longitude'],'name'=>$u['name'],'state'=>$u['state']??'','id'=>$u['id']]; }, $usersGeo)); ?>;
    const listingsData = <?php echo json_encode(array_map(function($l) { return ['lat'=>(float)$l['latitude'],'lng'=>(float)$l['longitude'],'name'=>$l['name'],'city'=>$l['city']??'','category'=>$l['category']??'','status'=>$l['status'],'id'=>$l['id']]; }, $listingsGeo)); ?>;

    // Map init (centered on India)
    const map = L.map('map').setView([20.5937, 78.9629], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 18
    }).addTo(map);

    // Create icon helpers
    function createIcon(color) {
        return L.divIcon({
            className: 'custom-pin',
            html: `<div style="width:12px;height:12px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3);"></div>`,
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });
    }

    const userIcon = createIcon('#10b981');
    const listingApprovedIcon = createIcon('#3b82f6');
    const listingPendingIcon = createIcon('#f59e0b');

    // Layers
    let markersLayer = L.layerGroup();
    let heatLayer = null;
    let clusterLayer = null;

    // Build markers with error handling
    usersData.forEach(u => {
        try {
            if(isNaN(u.lat) || isNaN(u.lng) || u.lat < -90 || u.lat > 90 || u.lng < -180 || u.lng > 180) return;
            const marker = L.marker([u.lat, u.lng], {icon: userIcon})
                .bindPopup(`<strong>${u.name}</strong><br><small>User · ${u.state}</small><br><a href="UserDetail.php?id=${u.id}" style="color:#10b981;">View Profile →</a>`);
            markersLayer.addLayer(marker);
        } catch(e) { console.warn('Skipping invalid user marker:', u.id, e); }
    });

    listingsData.forEach(l => {
        try {
            if(isNaN(l.lat) || isNaN(l.lng) || l.lat < -90 || l.lat > 90 || l.lng < -180 || l.lng > 180) return;
            const icon = l.status === 'approved' ? listingApprovedIcon : listingPendingIcon;
            const marker = L.marker([l.lat, l.lng], {icon: icon})
                .bindPopup(`<strong>${l.name}</strong><br><small>${l.category} · ${l.city}</small><br><span style="color:${l.status==='approved'?'#10b981':'#f59e0b'};font-weight:600;">${l.status}</span><br><a href="view-listing.php?id=${l.id}" style="color:#3b82f6;">View Listing →</a>`);
            markersLayer.addLayer(marker);
        } catch(e) { console.warn('Skipping invalid listing marker:', l.id, e); }
    });

    markersLayer.addTo(map);

    // Force map to recalculate size (fix for sidebar layout)
    setTimeout(() => { map.invalidateSize(); }, 300);

    // Heatmap data — filter out invalid points
    const heatPoints = [
        ...usersData.filter(u => !isNaN(u.lat) && !isNaN(u.lng)).map(u => [u.lat, u.lng, 0.6]),
        ...listingsData.filter(l => !isNaN(l.lat) && !isNaN(l.lng)).map(l => [l.lat, l.lng, 0.8])
    ];

    // View modes
    function clearAll() {
        if(markersLayer) map.removeLayer(markersLayer);
        if(heatLayer) map.removeLayer(heatLayer);
        if(clusterLayer) map.removeLayer(clusterLayer);
        document.querySelectorAll('.map-btn').forEach(b => b.classList.remove('active'));
    }

    function showMarkers() {
        clearAll();
        markersLayer.addTo(map);
        document.getElementById('btnMarkers').classList.add('active');
    }

    function showHeatmap() {
        clearAll();
        if(!heatLayer) {
            heatLayer = L.heatLayer(heatPoints, {radius: 25, blur: 20, maxZoom: 12, gradient: {0.2:'#10b981',0.4:'#3b82f6',0.6:'#f59e0b',0.8:'#ef4444',1:'#dc2626'}});
        }
        heatLayer.addTo(map);
        document.getElementById('btnHeat').classList.add('active');
    }

    function showClusters() {
        clearAll();
        if(!clusterLayer) {
            clusterLayer = L.markerClusterGroup();
            markersLayer.eachLayer(l => clusterLayer.addLayer(l));
        }
        map.addLayer(clusterLayer);
        document.getElementById('btnCluster').classList.add('active');
    }

    // Auto-fit bounds if we have data
    if(heatPoints.length > 0) {
        try {
            const bounds = L.latLngBounds(heatPoints.map(p => [p[0], p[1]]));
            map.fitBounds(bounds, { padding: [30, 30] });
        } catch(e) { console.warn('Could not fit bounds:', e); }
    }
    </script>
</body>
</html>

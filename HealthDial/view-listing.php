<?php
require_once 'connection.inc.php';
requireLogin();

// Accept both 'id' and 'view_id' parameters
$rawId = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['view_id']) ? $_GET['view_id'] : null);
if (!$rawId || !is_numeric($rawId)) {
    $_SESSION['error'] = "Invalid listing ID.";
    header("Location: Listing-Management.php");
    exit();
}

$view_id = intval($rawId);

// Fetch listing
$stmt = $conn->prepare("SELECT * FROM listings WHERE id=?");
$stmt->bind_param("i", $view_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Listing not found!";
    header("Location: Listing-Management.php");
    exit();
}
$listing = $result->fetch_assoc();
$stmt->close();

// Fetch category name
$catName = 'Unknown';
$catRes = $conn->query("SELECT name FROM categories WHERE id=" . intval($listing['category_id']));
if ($catRes && $catRow = $catRes->fetch_assoc()) {
    $catName = $catRow['name'];
}

// Fetch images
$images = $conn->query("SELECT * FROM listing_images WHERE listing_id={$view_id} ORDER BY is_primary DESC, id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Listing - HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .detail-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .detail-label { font-size: 12px; font-weight: 600; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .detail-value { font-size: 15px; color: #1A1D26; font-weight: 500; }
        .detail-section { padding: 20px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-section:last-child { border-bottom: none; }
        .detail-section-title { font-size: 14px; font-weight: 700; color: var(--primary, #0782ca); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .image-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .image-thumb { width: 120px; height: 120px; border-radius: 10px; object-fit: cover; border: 1px solid #E8ECF0; }
        .listing-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .status-badge { padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .status-badge.approved { background: #ECFDF5; color: #059669; }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.rejected { background: #FEF2F2; color: #DC2626; }
        @media (max-width: 768px) {
            .detail-grid, .detail-grid-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">

                <div class="card" style="max-width: 900px; margin: 0 auto; padding: 32px; border-radius: 14px;">

                    <div class="listing-header">
                        <div>
                            <h2 style="font-size: 22px; font-weight: 800; color: #1A1D26; margin: 0;">
                                <i class="fas fa-hospital" style="color: var(--primary, #0782ca); margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($listing['name']); ?>
                            </h2>
                            <p style="color: #9CA3AF; font-size: 13px; margin-top: 4px;">
                                Listing ID: #<?php echo $view_id; ?> &bull; 
                                Created: <?php echo date('d M Y, h:i A', strtotime($listing['created_at'])); ?>
                            </p>
                        </div>
                        <span class="status-badge <?php echo $listing['status'] ?? 'pending'; ?>">
                            <?php echo ucfirst($listing['status'] ?? 'pending'); ?>
                        </span>
                    </div>

                    <!-- Category & Basic Info -->
                    <div class="detail-section">
                        <div class="detail-section-title"><i class="fas fa-tag"></i> Basic Information</div>
                        <div class="detail-grid">
                            <div>
                                <div class="detail-label">Category</div>
                                <div class="detail-value"><?php echo htmlspecialchars($catName); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">City</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['city'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="detail-section">
                        <div class="detail-section-title"><i class="fas fa-align-left"></i> Description</div>
                        <div class="detail-value" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($listing['description'] ?? 'No description')); ?></div>
                    </div>

                    <!-- Address & Location -->
                    <div class="detail-section">
                        <div class="detail-section-title"><i class="fas fa-map-marker-alt"></i> Location</div>
                        <div style="margin-bottom: 12px;">
                            <div class="detail-label">Address</div>
                            <div class="detail-value"><?php echo htmlspecialchars($listing['address'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="detail-grid">
                            <div>
                                <div class="detail-label">Latitude</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['latitude'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Longitude</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['longitude'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($listing['latitude']) && !empty($listing['longitude'])): ?>
                        <a href="https://maps.google.com/?q=<?php echo $listing['latitude']; ?>,<?php echo $listing['longitude']; ?>" 
                           target="_blank" class="btn btn-secondary btn-sm" style="margin-top: 12px;">
                            <i class="fas fa-external-link-alt"></i> Open in Google Maps
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Contact Info -->
                    <div class="detail-section">
                        <div class="detail-section-title"><i class="fas fa-phone-alt"></i> Contact Information</div>
                        <div class="detail-grid-3">
                            <div>
                                <div class="detail-label">Mobile</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['mobile'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">WhatsApp</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['whatsapp'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['email'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Opening Hours -->
                    <div class="detail-section">
                        <div class="detail-section-title"><i class="fas fa-clock"></i> Operating Hours</div>
                        <div class="detail-grid-3">
                            <div>
                                <div class="detail-label">Open Time</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['open_time'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Close Time</div>
                                <div class="detail-value"><?php echo htmlspecialchars($listing['close_time'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div class="detail-label">Open 24×7</div>
                                <div class="detail-value">
                                    <?php if (!empty($listing['is_24x7'])): ?>
                                        <span style="color: #059669;"><i class="fas fa-check-circle"></i> Yes</span>
                                    <?php else: ?>
                                        <span style="color: #9CA3AF;">No</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Images -->
                    <?php if ($images && $images->num_rows > 0): ?>
                    <div class="detail-section">
                        <div class="detail-section-title"><i class="fas fa-images"></i> Images (<?php echo $images->num_rows; ?>)</div>
                        <div class="image-grid">
                            <?php while ($img = $images->fetch_assoc()): ?>
                                <?php 
                                    $imgSrc = (!empty($img['is_external_url']) && $img['is_external_url']) 
                                        ? $img['image_path'] 
                                        : './uploads/listings/' . $img['image_path'];
                                ?>
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="image-thumb" alt="Listing image" loading="lazy">
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div style="display: flex; gap: 10px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <?php if (($listing['status'] ?? '') !== 'approved'): ?>
                        <a href="ListingVerification.php?action=approve&id=<?php echo $view_id; ?>" class="btn btn-primary">
                            <i class="fas fa-check"></i> Approve
                        </a>
                        <?php endif; ?>
                        <?php if (($listing['status'] ?? '') !== 'rejected'): ?>
                        <a href="ListingVerification.php?action=reject&id=<?php echo $view_id; ?>" class="btn btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3);">
                            <i class="fas fa-times"></i> Reject
                        </a>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>
    </div>
</body>
</html>

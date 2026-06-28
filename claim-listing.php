<?php
/**
 * Submit a "claim this listing" request for an unclaimed (guest) listing.
 * The claim is reviewed by an admin in HealthDial/ListingClaims.php; on approval
 * the listing's user_id is set to the claimant and the button disappears.
 */
require_once 'includes/db.php';
require_once 'includes/user_auth.php';

header('Content-Type: application/json; charset=UTF-8');

function hd_claim_json($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hd_claim_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$user = hd_current_user();
if (!$user) {
    hd_claim_json(['success' => false, 'message' => 'Please log in to claim a listing.', 'auth_required' => true], 401);
}
if (empty($user['phone_verified'])) {
    hd_claim_json([
        'success'        => false,
        'message'        => 'Please verify your phone number before claiming a listing.',
        'phone_required' => true,
        'redirect'       => 'profile.php?verify=required',
    ], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$listing_id = intval($input['listing_id'] ?? 0);
$note       = trim((string) ($input['note'] ?? ''));
if (strlen($note) > 2000) {
    $note = substr($note, 0, 2000);
}

if ($listing_id <= 0) {
    hd_claim_json(['success' => false, 'message' => 'Invalid listing.'], 400);
}

$conn = getDbConnection();
if (!$conn) {
    hd_claim_json(['success' => false, 'message' => 'Service temporarily unavailable. Please try again.'], 503);
}

// Self-heal the claims table (matches the migration / listing-detail.php).
$conn->query("CREATE TABLE IF NOT EXISTS listing_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL, user_id INT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    claimant_name VARCHAR(150) NULL, claimant_phone VARCHAR(30) NULL,
    claimant_email VARCHAR(190) NULL, note TEXT NULL, admin_note VARCHAR(255) NULL,
    reviewed_by INT NULL, reviewed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing (listing_id), INDEX idx_user (user_id), INDEX idx_status (status)
)");

// Listing must exist, be approved, and not already be owned.
$stmt = $conn->prepare("SELECT id, user_id FROM listings WHERE id = ? AND status = 'approved' LIMIT 1");
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$listing) {
    hd_claim_json(['success' => false, 'message' => 'Listing not found.'], 404);
}
if (!empty($listing['user_id'])) {
    hd_claim_json(['success' => false, 'message' => 'This listing has already been claimed.'], 409);
}

$uid = (int) $user['id'];

// One pending claim per user per listing.
$dup = $conn->prepare("SELECT id FROM listing_claims WHERE listing_id = ? AND user_id = ? AND status = 'pending' LIMIT 1");
$dup->bind_param('ii', $listing_id, $uid);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    $dup->close();
    hd_claim_json(['success' => true, 'message' => 'You already have a pending claim for this listing.']);
}
$dup->close();

$name  = $user['name'] ?? '';
$phone = $user['mobile'] ?? '';
$email = $user['email'] ?? '';

$ins = $conn->prepare("INSERT INTO listing_claims
    (listing_id, user_id, status, claimant_name, claimant_phone, claimant_email, note)
    VALUES (?, ?, 'pending', ?, ?, ?, ?)");
$ins->bind_param('iissss', $listing_id, $uid, $name, $phone, $email, $note);

if ($ins->execute()) {
    $ins->close();
    hd_claim_json(['success' => true, 'message' => 'Claim submitted for review.']);
}
$ins->close();
hd_claim_json(['success' => false, 'message' => 'Could not submit your claim. Please try again.'], 500);

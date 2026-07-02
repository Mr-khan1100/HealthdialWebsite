<?php
/**
 * delete_review.php
 * -----------------------------------------------------------------------------
 * Lets the OWNER of a listing delete a review left on that listing.
 *
 * Guards (all required):
 *   • POST request
 *   • logged-in website user (hd_current_user)
 *   • valid CSRF token
 *   • the review belongs to a listing whose user_id === the current user's id
 *
 * On finish it redirects back to the listing page (#reviews) with a flash note
 * that listing-detail.php renders once.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';

hd_session_start();

$user      = hd_current_user();
$listingId = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
$reviewId  = isset($_POST['review_id']) ? (int) $_POST['review_id'] : 0;

/* ---- Safe "back" URL: prefer the same-host referer, else the listing page ---- */
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$back = ($listingId > 0) ? ('listing-detail.php?id=' . $listingId) : 'index.php';
if ($referer !== '') {
    $rHost = parse_url($referer, PHP_URL_HOST);
    $host  = $_SERVER['HTTP_HOST'] ?? '';
    if ($rHost === null || $rHost === '' || $rHost === $host) {
        $back = $referer; // same-origin (or relative) — safe to return to
    }
}

function hd_review_redirect(string $url, string $msg): void
{
    $_SESSION['hd_review_flash'] = $msg;
    if (strpos($url, '#') === false) {
        $url .= '#reviews';
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hd_review_redirect($back, 'Invalid request.');
}
if (!$user) {
    // Not signed in — send to login, then straight back to the listing.
    header('Location: ' . hd_login_url($back));
    exit;
}
if (!hd_verify_csrf($_POST['csrf'] ?? null)) {
    hd_review_redirect($back, 'Security check failed. Please try again.');
}
if ($reviewId <= 0) {
    hd_review_redirect($back, 'Review not found.');
}

$conn = getDbConnection();
if (!$conn) {
    hd_review_redirect($back, 'Service unavailable. Please try again later.');
}

/* ---- Ownership check: the review's listing must be owned by this user ---- */
$uid  = (int) $user['id'];
$owns = false;
$stmt = $conn->prepare("
    SELECT r.id
    FROM reviews r
    JOIN listings l ON l.id = r.listing_id
    WHERE r.id = ? AND l.user_id = ? AND l.user_id > 0
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param('ii', $reviewId, $uid);
    $stmt->execute();
    $owns = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

if (!$owns) {
    hd_review_redirect($back, 'You can only delete reviews on your own listing.');
}

$del = $conn->prepare("DELETE FROM reviews WHERE id = ? LIMIT 1");
if ($del) {
    $del->bind_param('i', $reviewId);
    $ok = $del->execute();
    $del->close();
    hd_review_redirect($back, $ok ? 'Review deleted.' : 'Could not delete the review. Please try again.');
}

hd_review_redirect($back, 'Could not delete the review. Please try again.');

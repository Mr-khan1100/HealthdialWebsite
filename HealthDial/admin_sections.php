<?php
/**
 * admin_sections.php
 * -----------------------------------------------------------------------------
 * SINGLE SOURCE OF TRUTH for the staff-assignable sidebar sections.
 *
 * Add a new entry here and it AUTOMATICALLY shows up:
 *   • as a permission checkbox on the staff create/edit form  (staff.php)
 *   • as a sidebar link gated by that staff member's permissions (sidebar.php)
 *   • enforced at the page level via requireAccess()           (connection.inc.php)
 *
 * Each entry:  'key' => [
 *      'label' => menu text,
 *      'icon'  => font-awesome class (without the leading "fas "),
 *      'url'   => page the link points to,
 *      'match' => (optional) extra page filenames that count as "active",
 *  ]
 *
 * NOTE: Admin-only system pages (Users, Payment Gateway, Sponsored, Analytics,
 * Admin Users, Activity Log, System Health, Settings, Staff, Mobile Config, …)
 * are intentionally NOT listed here. They stay locked to the full admin role via
 * isAdmin()/requireAdmin() and are never delegatable to staff.
 */
function admin_sections()
{
    return [
        'dashboard'       => ['label' => 'Dashboard',       'icon' => 'fa-th-large',        'url' => 'Dashboard.php'],
        'listings'        => ['label' => 'Listings',        'icon' => 'fa-hospital',        'url' => 'Listing-Management.php', 'match' => ['Listing-Management.php', 'add-listing.php', 'update-listing.php', 'view-listing.php', 'bulk-upload.php']],
        'verification'    => ['label' => 'Verification',    'icon' => 'fa-clipboard-check', 'url' => 'ListingVerification.php'],
        'categories'      => ['label' => 'Categories',      'icon' => 'fa-layer-group',     'url' => 'Categories.php'],
        'support'         => ['label' => 'Support Tickets', 'icon' => 'fa-headset',         'url' => 'SupportTickets.php'],
        'claims'          => ['label' => 'Listing Claims',  'icon' => 'fa-handshake',       'url' => 'ListingClaims.php'],
        'reviews'         => ['label' => 'Reviews',         'icon' => 'fa-star',            'url' => 'Reviews.php'],
        'notification'    => ['label' => 'Notifications',   'icon' => 'fa-bell',            'url' => 'Notification.php'],
        'documents'       => ['label' => 'Documents',       'icon' => 'fa-file-medical',    'url' => 'Documents.php'],
        'news'            => ['label' => 'News',            'icon' => 'fa-newspaper',       'url' => 'News.php'],
        'banners'         => ['label' => 'Banners',         'icon' => 'fa-images',          'url' => 'Banners.php'],
        'website_banners' => ['label' => 'Website Banners', 'icon' => 'fa-globe',           'url' => 'WebsiteBanners.php'],
        'popups'          => ['label' => 'Popups',          'icon' => 'fa-window-restore',  'url' => 'Popups.php'],
        'medications'     => ['label' => 'Medications',     'icon' => 'fa-pills',           'url' => 'Medications.php'],
    ];
}

/** List of valid section keys (used to validate posted permissions). */
function admin_section_keys()
{
    return array_keys(admin_sections());
}

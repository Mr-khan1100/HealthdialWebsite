<?php
require_once '../config.php';

// Read About Us data from settings table, with defaults
$aboutKeys = [
    'about_tagline' => 'Your Trusted Healthcare Discovery Platform',
    'about_mission' => 'HealthDial aims to improve healthcare accessibility by helping users discover nearby hospitals, clinics, and medical professionals. We believe everyone deserves quick and easy access to quality healthcare services.',
    'about_vision' => 'To become a reliable digital healthcare directory empowering users to make informed healthcare decisions.',
    'about_stats_years' => '1+',
    'about_stats_users' => '0',
    'about_email' => 'healthdialofficial@gmail.com',
    'about_phone' => '+919911660669',
    'about_website' => 'https://healthdial.com',
    'about_address' => 'Faridabad, Haryana, India',
    'about_facebook' => 'https://facebook.com/healthdial',
    'about_twitter' => 'https://twitter.com/healthdial',
    'about_instagram' => 'https://instagram.com/healthdial',
    'about_linkedin' => 'https://linkedin.com/company/healthdial',
];

$savedSettings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'about_%'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $savedSettings[$row['setting_key']] = $row['setting_value'];
    }
} else {
    error_log("About: Failed to query settings table: " . $conn->error);
}

function getAbout($key, $default = '')
{
    global $savedSettings;
    return isset($savedSettings[$key]) && $savedSettings[$key] !== '' ? $savedSettings[$key] : $default;
}

// Get real user count from database
$usersCount = getAbout('about_stats_users', '0');
if ($usersCount === '0' || $usersCount === '') {
    $countRes = $conn->query("SELECT COUNT(*) as total FROM users");
    if ($countRes) {
        $row = $countRes->fetch_assoc();
        $total = (int)$row['total'];
        if ($total >= 1000) {
            $usersCount = round($total / 1000) . 'K+';
        } else {
            $usersCount = $total . '+';
        }
    }
}

// Get real listings count
$listingsCount = '0';
$listRes = $conn->query("SELECT COUNT(*) as total FROM listings WHERE status = 'approved'");
if ($listRes) {
    $row = $listRes->fetch_assoc();
    $listingsCount = (int)$row['total'];
}

$response = [
    "tagline" => getAbout('about_tagline', $aboutKeys['about_tagline']),
    "mission" => getAbout('about_mission', $aboutKeys['about_mission']),
    "vision" => getAbout('about_vision', $aboutKeys['about_vision']),
    "stats" => [
        "product_years" => getAbout('about_stats_years', $aboutKeys['about_stats_years']),
        "users_count" => $usersCount,
        "listings_count" => $listingsCount
    ],
    "contact" => [
        "email" => getAbout('about_email', $aboutKeys['about_email']),
        "phone" => getAbout('about_phone', $aboutKeys['about_phone']),
        "website" => getAbout('about_website', $aboutKeys['about_website']),
        "address" => getAbout('about_address', $aboutKeys['about_address'])
    ],
    "social" => [
        "facebook" => getAbout('about_facebook', $aboutKeys['about_facebook']),
        "twitter" => getAbout('about_twitter', $aboutKeys['about_twitter']),
        "instagram" => getAbout('about_instagram', $aboutKeys['about_instagram']),
        "linkedin" => getAbout('about_linkedin', $aboutKeys['about_linkedin'])
    ]
];

sendResponse(['success' => true, 'data' => $response]);
?>
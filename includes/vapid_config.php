<?php
/**
 * VAPID Keys for Web Push Notifications
 *
 * HOW TO GENERATE:
 *   1. Visit /HealthDial/generate_vapid_keys.php?key=healthdial_vapid_setup in your browser
 *   2. Copy the generated config block and paste it below
 *   3. Delete generate_vapid_keys.php from the server after use
 *   4. Run the SQL migration: db/migrations/create_web_push_subscriptions.sql
 */

define('VAPID_SUBJECT', 'mailto:support@healthdial.com');
define('VAPID_PRIVATE_KEY_PEM', '-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgXMXVTLzixziv3lk8
VHUn0Aic3mWTn3ro4u1ttJNJuQahRANCAAT1Yq/SFQ4dJneGwUtQaC5eOUrQ4TNe
m6uuh2gX1p0FqP1hh/8LTH66HnchCmnAOEWNhxtp7Qu5Flm2BOFh5pxm
-----END PRIVATE KEY-----');   // Paste PEM private key string here
define('VAPID_PUBLIC_KEY_BASE64', 'BPVir9IVDh0md4bBS1BoLl45StDhM16bq66HaBfWnQWo_WGH_wtMfroedyEKacA4RY2HG2ntC7kWWbYE4WHmnGY'); // Paste base64url public key here
<?php
/**
 * Firebase Web App configuration for the public website.
 *
 * HOW TO FILL THIS IN (one-time):
 *   1. Firebase console  ->  your existing HealthDial project  ->  Project settings
 *      ->  "Your apps"  ->  Add app  ->  Web (</>)  ->  register the app, then copy
 *      the firebaseConfig values into the array below.
 *   2. Authentication  ->  Sign-in method: enable **Google** and **Phone**.
 *   3. Authentication  ->  Settings  ->  Authorized domains: add `healthdial.com`
 *      (and `localhost` for local testing).
 *
 * Reuse the SAME project the mobile app uses so accounts are shared across the
 * app and website. `projectId` is also used server-side to verify ID tokens
 * (see includes/firebase_verify.php), so it must match the project that issued
 * the tokens.
 */

function hd_firebase_config(): array
{
    return [
        'apiKey' => '',
        'authDomain' => 'healthdial-199ef.firebaseapp.com',
        'projectId' => 'healthdial-199ef',
        'storageBucket' => 'healthdial-199ef.firebasestorage.app',
        'messagingSenderId' => '295172680854',
        'appId' => '1:295172680854:web:7d756070c63630328666b7',
        'measurementId' => 'G-H950E8DJWC',
    ];
}

/**
 * The Firebase project id used for server-side ID-token verification.
 */
function hd_firebase_project_id(): string
{
    $cfg = hd_firebase_config();
    return (string) ($cfg['projectId'] ?? '');
}

/**
 * True once real values have been filled in (used to show a helpful notice on
 * the login page instead of a broken Firebase init).
 */
function hd_firebase_is_configured(): bool
{
    $cfg = hd_firebase_config();
    foreach ($cfg as $v) {
        if (strpos((string) $v, 'REPLACE_WITH') === 0) {
            return false;
        }
    }
    return !empty($cfg['apiKey']) && !empty($cfg['projectId']);
}
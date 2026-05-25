<?php
function generateSignedUrl($documentId, $userId) {
    $secret = 'SUPER_SECRET_KEY_CHANGE_THIS';
    $expires = time() + 300; // 5 minutes

    $data = $documentId . '|' . $userId . '|' . $expires;
    $signature = hash_hmac('sha256', $data, $secret);

    return [
        'expires' => $expires,
        'signature' => $signature
    ];
}
?>
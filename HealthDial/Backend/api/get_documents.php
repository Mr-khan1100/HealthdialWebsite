<?php
require '../config.php';
require 'generate_signed_url.php';

$user = authenticateUser();
if (!$user) {
    echo json_encode(['success' => false]);
    exit;
}

$docs = [];
$result = mysqli_query($conn, "SELECT * FROM documents WHERE user_id = {$user['id']}");

while ($row = mysqli_fetch_assoc($result)) {
    $signed = generateSignedUrl($row['id'], $user['id']);

    $docs[] = [
        'id' => $row['id'],
        'name' => $row['file_name'],
        'view_url' => "/secure_view.php?id={$row['id']}&expires={$signed['expires']}&sig={$signed['signature']}",
        'download_url' => "/secure_download.php?id={$row['id']}&expires={$signed['expires']}&sig={$signed['signature']}",
    ];
}

echo json_encode(['success' => true, 'documents' => $docs]);

?>
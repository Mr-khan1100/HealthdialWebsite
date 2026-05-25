<?php
require '../config.php';

$id = $_GET['id'];
$expires = $_GET['expires'];
$sig = $_GET['sig'];

$user = authenticateUser();
if (!$user || time() > $expires) die('Expired');

$secret = 'SUPER_SECRET_KEY_CHANGE_THIS';
$data = $id . '|' . $user['id'] . '|' . $expires;

if (hash_hmac('sha256', $data, $secret) !== $sig) die('Invalid');

$res = mysqli_query($conn, "SELECT * FROM documents WHERE id=$id AND user_id={$user['id']}");
$doc = mysqli_fetch_assoc($res);

header("Content-Type: {$doc['mime_type']}");
readfile($doc['file_path']);

?>
<?php
require 'includes/db.php';
$data = fetch_api_data(API_BASE . 'get_listing_detail.php?id=2644129');
echo 'SUCCESS: ' . ($data['success'] ? 'YES' : 'NO') . PHP_EOL;
echo 'NAME: ' . ($data['data']['name'] ?? 'MISSING') . PHP_EOL;

<?php
// Lightweight health endpoint that avoids touching the database.
header('Content-Type: application/json');

$response = [
    'status' => 'ok',
    'service' => 'prism-wallet',
    'time' => time(),
];

http_response_code(200);
echo json_encode($response);

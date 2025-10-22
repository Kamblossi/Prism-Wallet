<?php
// Lightweight health endpoint for container/platform checks.
// Does not depend on the database to avoid flapping during startup.
header('Content-Type: application/json');
http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'service' => 'prism-wallet',
    'time' => time(),
]);
echo "\n";
?>


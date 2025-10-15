<?php
require_once __DIR__ . '/../../includes/connect.php';
require_once __DIR__ . '/../../includes/oidc_auth0.php';

// If OIDC not enabled or misconfigured, send back to normal login
$auth0 = prism_get_auth0_instance($pdo);
if (!$auth0) {
    header('Location: /login.php');
    exit;
}

// Build login URL and redirect. SDK handles state/nonce.
$params = [];
// Optionally request organization or connection via query string passthrough
if (isset($_GET['organization'])) { $params['organization'] = $_GET['organization']; }
if (isset($_GET['connection'])) { $params['connection'] = $_GET['connection']; }

$authUrl = $auth0->login($params);
header('Location: ' . $authUrl);
exit;
?>


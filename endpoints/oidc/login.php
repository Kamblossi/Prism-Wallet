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
// Optional passthroughs
if (isset($_GET['organization']) && $_GET['organization'] !== '') { $params['organization'] = $_GET['organization']; }
if (isset($_GET['connection']) && $_GET['connection'] !== '') { $params['connection'] = $_GET['connection']; }
if (isset($_GET['screen_hint']) && $_GET['screen_hint'] !== '') { $params['screen_hint'] = $_GET['screen_hint']; }
if (isset($_GET['login_hint']) && $_GET['login_hint'] !== '') { $params['login_hint'] = $_GET['login_hint']; }
if (isset($_GET['prompt']) && $_GET['prompt'] !== '') { $params['prompt'] = $_GET['prompt']; }

$authUrl = $auth0->login($params);
header('Location: ' . $authUrl);
exit;

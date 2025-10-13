<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

// Check that user is an admin
if ($userId !== 1) {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    $oidcName = isset($data['oidcName']) ? trim($data['oidcName']) : '';
    $oidcClientId = isset($data['oidcClientId']) ? trim($data['oidcClientId']) : '';
    $oidcClientSecret = isset($data['oidcClientSecret']) ? trim($data['oidcClientSecret']) : '';
    $oidcAuthUrl = isset($data['oidcAuthUrl']) ? trim($data['oidcAuthUrl']) : '';
    $oidcTokenUrl = isset($data['oidcTokenUrl']) ? trim($data['oidcTokenUrl']) : '';
    $oidcUserInfoUrl = isset($data['oidcUserInfoUrl']) ? trim($data['oidcUserInfoUrl']) : '';
    $oidcRedirectUrl = isset($data['oidcRedirectUrl']) ? trim($data['oidcRedirectUrl']) : '';
    $oidcLogoutUrl = isset($data['oidcLogoutUrl']) ? trim($data['oidcLogoutUrl']) : '';
    $oidcUserIdentifierField = isset($data['oidcUserIdentifierField']) ? trim($data['oidcUserIdentifierField']) : '';
    $oidcScopes = isset($data['oidcScopes']) ? trim($data['oidcScopes']) : '';
    $oidcAuthStyle = isset($data['oidcAuthStyle']) ? trim($data['oidcAuthStyle']) : '';
    $oidcAutoCreateUser = isset($data['oidcAutoCreateUser']) ? (int)$data['oidcAutoCreateUser'] : 0;
    $oidcPasswordLoginDisabled = isset($data['oidcPasswordLoginDisabled']) ? (int)$data['oidcPasswordLoginDisabled'] : 0;

    $checkStmt = $pdo->prepare('SELECT COUNT(*) as count FROM oauth_settings WHERE id = 1');
    $result = $checkStmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['count'] > 0) {
        // Update existing row
        $stmt = $pdo->prepare('UPDATE oauth_settings SET 
            name = :oidcName, 
            client_id = :oidcClientId, 
            client_secret = :oidcClientSecret, 
            authorization_url = :oidcAuthUrl, 
            token_url = :oidcTokenUrl, 
            user_info_url = :oidcUserInfoUrl, 
            redirect_url = :oidcRedirectUrl, 
            logout_url = :oidcLogoutUrl, 
            user_identifier_field = :oidcUserIdentifierField, 
            scopes = :oidcScopes, 
            auth_style = :oidcAuthStyle,
            auto_create_user = :oidcAutoCreateUser,
            password_login_disabled = :oidcPasswordLoginDisabled
            WHERE id = 1');
    } else {
        // Insert new row
        $stmt = $pdo->prepare('INSERT INTO oauth_settings (
            id, name, client_id, client_secret, authorization_url, token_url, user_info_url, redirect_url, logout_url, user_identifier_field, scopes, auth_style, auto_create_user, password_login_disabled
        ) VALUES (
            1, :oidcName, :oidcClientId, :oidcClientSecret, :oidcAuthUrl, :oidcTokenUrl, :oidcUserInfoUrl, :oidcRedirectUrl, :oidcLogoutUrl, :oidcUserIdentifierField, :oidcScopes, :oidcAuthStyle, :oidcAutoCreateUser, :oidcPasswordLoginDisabled 
        )');
    }

    $stmt->bindParam(':oidcName', $oidcName, PDO::PARAM_STR);
    $stmt->bindParam(':oidcClientId', $oidcClientId, PDO::PARAM_STR);
    $stmt->bindParam(':oidcClientSecret', $oidcClientSecret, PDO::PARAM_STR);
    $stmt->bindParam(':oidcAuthUrl', $oidcAuthUrl, PDO::PARAM_STR);
    $stmt->bindParam(':oidcTokenUrl', $oidcTokenUrl, PDO::PARAM_STR);
    $stmt->bindParam(':oidcUserInfoUrl', $oidcUserInfoUrl, PDO::PARAM_STR);
    $stmt->bindParam(':oidcRedirectUrl', $oidcRedirectUrl, PDO::PARAM_STR);
    $stmt->bindParam(':oidcLogoutUrl', $oidcLogoutUrl, PDO::PARAM_STR);
    $stmt->bindParam(':oidcUserIdentifierField', $oidcUserIdentifierField, PDO::PARAM_STR);
    $stmt->bindParam(':oidcScopes', $oidcScopes, PDO::PARAM_STR);
    $stmt->bindParam(':oidcAuthStyle', $oidcAuthStyle, PDO::PARAM_STR);
    $stmt->bindParam(':oidcAutoCreateUser', $oidcAutoCreateUser, PDO::PARAM_INT);  
    $stmt->bindParam(':oidcPasswordLoginDisabled', $oidcPasswordLoginDisabled, PDO::PARAM_INT);
    $stmt->execute();

    if ($db->changes() > 0) {
        $db->close();
        die(json_encode([
            "success" => true,
            "message" => translate('success', $i18n)
        ]));
    } else {
        $db->close();
        die(json_encode([
            "success" => false,
            "message" => translate('error', $i18n)
        ]));
    }

} else {
    die(json_encode([
        "success" => false,
        "message" => translate('error', $i18n)
    ]));
}
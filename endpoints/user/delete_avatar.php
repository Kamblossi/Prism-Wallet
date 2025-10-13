<?php

require_once '../../includes/connect_endpoint.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['avatar'])) {
    $baseDir = realpath("../../images/uploads/logos/avatars/");
    $avatar = $input['avatar'];

    $cleanAvatar = rawurldecode($avatar);
    $cleanAvatar = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $cleanAvatar);

    $filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $cleanAvatar);

    if ($filePath === false || strpos($filePath, $baseDir) !== 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid file path"
        ]);
        exit;
    }

    $sql = "SELECT avatar FROM users WHERE id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userAvatar = $stmt->fetch(PDO::FETCH_ASSOC)['avatar'];

    // Check if $avatar matches the avatar in the user table
    if ($avatar === $userAvatar) {
        echo json_encode(array("success" => false, "message" => "Avatar in use"));
    } else {
        if (file_exists($filePath)) {
            unlink($filePath);
            echo json_encode(array("success" => true, "message" => translate("success", $i18n)));
        } else {
            echo json_encode(array("success" => false, "message" => translate("error", $i18n)));
        }
    }
} else {
    echo json_encode(array("success" => false, "message" => translate("error", $i18n)));
}

?>
<?php

/**
 * This migration script updates the avatar field of the user table to use the new avatar path.
 */

$sql = "SELECT avatar FROM user";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $avatar = $row['avatar'];

    if (strlen($avatar) < 2) {
        $avatarFullPath = "images/avatars/" . $avatar . ".svg";
        $sql = "UPDATE users SET avatar = :avatarFullPath";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':avatarFullPath', $avatarFullPath, PDO::PARAM_STR);
        $stmt->execute();
    }
}

?>
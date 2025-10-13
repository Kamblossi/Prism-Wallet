<?php
$stmt = $pdo->query("SELECT COUNT(*) AS count FROM users");
$userCount = (int)$stmt->fetchColumn();
?>

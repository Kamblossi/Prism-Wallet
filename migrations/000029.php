<?php

// This migration adds a "api_key" column to the user table
// It also generates an API key for each user

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') where name='api_key'");
$columnRequired = $columnQuery->fetch(PDO::FETCH_ASSOC) === false;

if ($columnRequired) {
    $db->exec('ALTER TABLE user ADD COLUMN api_key TEXT');
}

$users = $db->query('SELECT * FROM user');
while ($user = $users->fetch(PDO::FETCH_ASSOC)) {
    if (empty($user['api_key'])) {
        $apiKey = bin2hex(random_bytes(32));
        $db->exec('UPDATE users SET api_key = "' . $apiKey . '" WHERE id = ' . $user['id']);
    }
}

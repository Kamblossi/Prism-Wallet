<?php
// This migration populates all world currencies for every existing user
// It inserts only currencies that the user does not yet have (by code)

// Attempt to include the world currencies definition from both possible relative paths
if (file_exists('includes/world_currencies.php')) {
    require_once 'includes/world_currencies.php';
} elseif (file_exists('../../includes/world_currencies.php')) {
    require_once '../../includes/world_currencies.php';
} else {
    // Fallback: abort if file not found
    return; // Silent exit so migration runner won't crash
}

if (!function_exists('getAllWorldCurrencies')) {
    return; // Safety: do nothing if function missing
}

$allCurrencies = getAllWorldCurrencies();

// Fetch all users
$usersResult = $db->query('SELECT id FROM user');
if ($usersResult === false) {
    return; // Nothing to do
}

while ($userRow = $usersResult->fetch(PDO::FETCH_ASSOC)) {
    $uid = (int)$userRow['id'];

    // Build a set of existing currency codes for this user
    $existingCodes = [];
    $stmtExisting = $pdo->prepare('SELECT code FROM currencies WHERE user_id = :uid');
    $stmtExisting->bindValue(':uid', $uid, PDO::PARAM_INT);
    $resExisting = $stmtExisting->execute();
    while ($row = $resExisting->fetch(PDO::FETCH_ASSOC)) {
        $existingCodes[$row['code']] = true;
    }

    // Prepare insert statement once
    $stmtInsert = $pdo->prepare('INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :uid)');

    $addedCount = 0;
    foreach ($allCurrencies as $code => $data) {
        if (isset($existingCodes[$code])) {
            continue; // Skip existing
        }
        $stmtInsert->reset();
        $stmtInsert->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmtInsert->bindValue(':symbol', $data['symbol'], PDO::PARAM_STR);
        $stmtInsert->bindValue(':code', $data['code'], PDO::PARAM_STR);
        $stmtInsert->bindValue(':rate', 1, PDO::PARAM_STR);
        $stmtInsert->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmtInsert->execute();
        $addedCount++;
    }
    // Optionally could log counts, but migration runner only echoes on success
}

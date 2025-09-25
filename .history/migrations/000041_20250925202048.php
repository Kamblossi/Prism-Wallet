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

while ($userRow = $usersResult->fetchArray(SQLITE3_ASSOC)) {
    $uid = (int)$userRow['id'];

    // Build a set of existing currency codes for this user
    $existingCodes = [];
    $stmtExisting = $db->prepare('SELECT code FROM currencies WHERE user_id = :uid');
    $stmtExisting->bindValue(':uid', $uid, SQLITE3_INTEGER);
    $resExisting = $stmtExisting->execute();
    while ($row = $resExisting->fetchArray(SQLITE3_ASSOC)) {
        $existingCodes[$row['code']] = true;
    }

    // Prepare insert statement once
    $stmtInsert = $db->prepare('INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :uid)');

    $addedCount = 0;
    foreach ($allCurrencies as $code => $data) {
        if (isset($existingCodes[$code])) {
            continue; // Skip existing
        }
        $stmtInsert->reset();
        $stmtInsert->bindValue(':name', $data['name'], SQLITE3_TEXT);
        $stmtInsert->bindValue(':symbol', $data['symbol'], SQLITE3_TEXT);
        $stmtInsert->bindValue(':code', $data['code'], SQLITE3_TEXT);
        $stmtInsert->bindValue(':rate', 1, SQLITE3_FLOAT);
        $stmtInsert->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $stmtInsert->execute();
        $addedCount++;
    }
    // Optionally could log counts, but migration runner only echoes on success
}

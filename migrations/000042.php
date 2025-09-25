<?php
// Migration 000042
// 1. Update Kenyan Shilling symbol to 'KES' for any existing records still using 'KSh'
// 2. Ensure all existing users have the complete world currency set (idempotent)

// Include world currencies definition
if (file_exists('includes/world_currencies.php')) {
    require_once 'includes/world_currencies.php';
} elseif (file_exists('../../includes/world_currencies.php')) {
    require_once '../../includes/world_currencies.php';
} else {
    return; // Cannot proceed without currency list
}

if (!function_exists('getAllWorldCurrencies')) {
    return;
}

$allCurrencies = getAllWorldCurrencies();

// 1. Update Kenyan Shilling symbol to 'KES'
$updateStmt = $db->prepare("UPDATE currencies SET symbol = :newSymbol WHERE code = 'KES' AND symbol != :newSymbol");
$updateStmt->bindValue(':newSymbol', 'KES', SQLITE3_TEXT);
$updateStmt->execute();

// 2. Insert missing currencies for each user
$usersResult = $db->query('SELECT id FROM user');
if ($usersResult === false) { return; }

while ($userRow = $usersResult->fetchArray(SQLITE3_ASSOC)) {
    $uid = (int)$userRow['id'];

    // Collect existing codes
    $existingCodes = [];
    $stmtExisting = $db->prepare('SELECT code FROM currencies WHERE user_id = :uid');
    $stmtExisting->bindValue(':uid', $uid, SQLITE3_INTEGER);
    $resExisting = $stmtExisting->execute();
    while ($row = $resExisting->fetchArray(SQLITE3_ASSOC)) {
        $existingCodes[$row['code']] = true;
    }

    $stmtInsert = $db->prepare('INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :uid)');

    foreach ($allCurrencies as $code => $data) {
        if (isset($existingCodes[$code])) { continue; }
        $stmtInsert->reset();
        $stmtInsert->bindValue(':name', $data['name'], SQLITE3_TEXT);
        $stmtInsert->bindValue(':symbol', $data['symbol'], SQLITE3_TEXT);
        $stmtInsert->bindValue(':code', $data['code'], SQLITE3_TEXT);
        $stmtInsert->bindValue(':rate', 1, SQLITE3_FLOAT);
        $stmtInsert->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $stmtInsert->execute();
    }
}

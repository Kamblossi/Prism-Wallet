<?php

    $currencies = array();
    $query = "SELECT * FROM currencies WHERE user_id = :userId";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currencyId = $row['id'];
        $currencies[$currencyId] = $row;
    }

    $members = array();
    $query = "SELECT * FROM household WHERE user_id = :userId";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $memberId = $row['id'];
        $members[$memberId] = $row;
        $members[$memberId]['count'] = 0;
    }

    $payment_methods = array();
    // Only enabled methods; boolean comparison for Postgres
    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE enabled = TRUE AND user_id = :userId ORDER BY \"order\" ASC");
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payment_methodId = $row['id'];
        $payment_methods[$payment_methodId] = $row;
        $payment_methods[$payment_methodId]['count'] = 0;
    }

    $categories = array();
    // Quote reserved column name for PostgreSQL
    $query = "SELECT * FROM categories WHERE user_id = :userId ORDER BY \"order\" ASC";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryId = $row['id'];
        $categories[$categoryId] = $row;
        $categories[$categoryId]['count'] = 0;
    }

    $cycles = array();
    try {
        $query = "SELECT * FROM cycles";
        $stmt = $db->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cycleId = $row['id'];
            $cycles[$cycleId] = $row;
        }
    } catch (Throwable $e) {
        // Fallback defaults if table missing during first boot; connect.php/migrate.php should create it
        $cycles = [
            1 => ['id' => 1, 'name' => 'Daily'],
            2 => ['id' => 2, 'name' => 'Weekly'],
            3 => ['id' => 3, 'name' => 'Monthly'],
            4 => ['id' => 4, 'name' => 'Yearly'],
        ];
    }

    $frequencies = array();
    for ($i = 1; $i <= 366; $i++) {
        $frequencies[$i] = array('id' => $i, 'name' => $i);
    }

?>

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
    $query = $pdo->prepare("SELECT * FROM payment_methods WHERE enabled=:enabled AND user_id = :userId ORDER BY `order` ASC");
    $query->bindValue(':enabled', 1, PDO::PARAM_INT);
    $query->bindValue(':userId', $userId, PDO::PARAM_INT);
    $result = $query->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payment_methodId = $row['id'];
        $payment_methods[$payment_methodId] = $row;
        $payment_methods[$payment_methodId]['count'] = 0;
    }

    $categories = array();
    $query = "SELECT * FROM categories WHERE user_id = :userId ORDER BY `order` ASC";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categoryId = $row['id'];
        $categories[$categoryId] = $row;
        $categories[$categoryId]['count'] = 0;
    }

    $cycles = array();
    $query = "SELECT * FROM cycles";
    $result = $db->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cycleId = $row['id'];
        $cycles[$cycleId] = $row;
    }

    $frequencies = array();
    for ($i = 1; $i <= 366; $i++) {
        $frequencies[$i] = array('id' => $i, 'name' => $i);
    }

?>
<?php
require_once '../../includes/connect_endpoint.php';

$shouldUpdate = true;

if (isset($_GET['force']) && $_GET['force'] === "true") {
    $shouldUpdate = true;
} else {
    $query = "SELECT date FROM last_exchange_update WHERE user_id = :userId";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();

    // PDO conversion - removed result check
        $lastUpdateDate = new DateTime($result);
        $currentDate = new DateTime();
        $lastUpdateDateString = $lastUpdateDate->format('Y-m-d');
        $currentDateString = $currentDate->format('Y-m-d');
        $shouldUpdate = $lastUpdateDateString < $currentDateString;
    }

    if (!$shouldUpdate) {
        echo "Rates are current, no need to update.";
        exit;
    }
}

$row = $pdo->query('SELECT fixer_api_key, fixer_provider FROM admin ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['fixer_api_key'])) {
        $apiKey = $row['fixer_api_key'];
        $provider = (int)$row['fixer_provider'];

        $codes = "";
        $query = "SELECT id, name, symbol, code FROM currencies WHERE user_id = :userId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codes .= $row['code'] . ",";
        }
        $codes = rtrim($codes, ',');
        $query = "SELECT u.main_currency, c.code FROM user u LEFT JOIN currencies c ON u.main_currency = c.id WHERE u.id = :userId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $mainCurrencyCode = $row['code'];
        $mainCurrencyId = $row['main_currency'];

        if ($provider === 1) {
            $api_url = "https://api.apilayer.com/fixer/latest?base=EUR&symbols=" . $codes;
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'apikey: ' . $apiKey,
                ]
            ]);
            $response = file_get_contents($api_url, false, $context);
        } else {
            $api_url = "http://data.fixer.io/api/latest?access_key=" . $apiKey . "&base=EUR&symbols=" . $codes;
            $response = file_get_contents($api_url);
        }

        $apiData = json_decode($response, true);

        $mainCurrencyToEUR = $apiData['rates'][$mainCurrencyCode];

        if ($apiData !== null && isset($apiData['rates'])) {
            foreach ($apiData['rates'] as $currencyCode => $rate) {
                if ($currencyCode === $mainCurrencyCode) {
                    $exchangeRate = 1.0;
                } else {
                    $exchangeRate = $rate / $mainCurrencyToEUR;
                }
                $updateQuery = "UPDATE currencies SET rate = :rate WHERE code = :code AND user_id = :userId";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':rate', $exchangeRate, PDO::PARAM_STR);
                $updateStmt->bindParam(':code', $currencyCode, PDO::PARAM_STR);
                $updateStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $updateResult = $updateStmt->execute();

                if (!$updateResult) {
                    echo "Error updating rate for currency: $currencyCode";
                }
            }
            $currentDate = new DateTime();
            $formattedDate = $currentDate->format('Y-m-d');

            $updateQuery = "UPDATE last_exchange_update SET date = :formattedDate WHERE user_id = :userId";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':formattedDate', $formattedDate, PDO::PARAM_STR);
            $updateStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $updateResult = $updateStmt->execute();

            $db->close();
            echo "Rates updated successfully!";
        }
    } else {
        echo "Exchange rates update skipped. No fixer.io api key provided";
        $apiKey = null;
    }
} else {
    echo "Exchange rates update skipped. No fixer.io api key provided";
    $apiKey = null;
}
?>

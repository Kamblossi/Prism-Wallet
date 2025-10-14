<?php
require_once 'validate.php';
require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

require 'settimezone.php';

// Get all user ids

if (php_sapi_name() == 'cli') {
    $date = new DateTime('now');
    echo "\n" . $date->format('Y-m-d') . " " . $date->format('H:i:s') . "<br />\n";
}

$query = "SELECT id, username FROM users";
$stmt = $pdo->prepare($query);
$stmt->execute();

while ($userToUpdateExchange = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $userId = $userToUpdateExchange['id'];
    echo "For user: " . $userToUpdateExchange['username'] . "<br />";

    $query = "SELECT api_key, provider FROM fixer WHERE user_id = :userId";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();

    // PDO conversion - removed result check
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $apiKey = $row['api_key'];
            $provider = $row['provider'];

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
                    $updateQuery = "UPDATE currencies SET rate = :rate WHERE code = :code";
                    $updateStmt = $pdo->prepare($updateQuery);
                    $updateStmt->bindParam(':rate', $exchangeRate, PDO::PARAM_STR);
                    $updateStmt->bindParam(':code', $currencyCode, PDO::PARAM_STR);
                    $updateResult = $updateStmt->execute();

                    if (!$updateResult) {
                        echo "Error updating rate for currency: $currencyCode <br />";
                    }
                }
                $currentDate = new DateTime();
                $formattedDate = $currentDate->format('Y-m-d');

                $deleteQuery = "DELETE FROM last_exchange_update WHERE user_id = :userId";
                $deleteStmt = $pdo->prepare($deleteQuery);
                $deleteStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $deleteResult = $deleteStmt->execute();

                $query = "INSERT INTO last_exchange_update (date, user_id) VALUES (:formattedDate, :userId)";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':formattedDate', $formattedDate, PDO::PARAM_STR);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();

                echo "Rates updated successfully!<br />";
            }
        } else {
            echo "Exchange rates update skipped. No fixer.io api key provided<br />";
            $apiKey = null;
        }
}

?>

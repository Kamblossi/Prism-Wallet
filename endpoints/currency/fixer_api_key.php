<?php
require_once '../../includes/connect_endpoint.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $newApiKey = isset($_POST["api_key"]) ? trim($_POST["api_key"]) : "";
        $provider = isset($_POST["provider"]) ? $_POST["provider"] : 0;

        $removeOldKey = "DELETE FROM fixer WHERE user_id = :userId";
        $stmt = $pdo->prepare($removeOldKey);
        $stmt->bindParam(":userId", $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($provider == 1) {
            $testKeyUrl = "https://api.apilayer.com/fixer/latest?base=USD&symbols=EUR";
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'apikey: ' . $newApiKey,
                ]
            ]);
            $response = file_get_contents($testKeyUrl, false, $context);
        } else {
            $testKeyUrl = "http://data.fixer.io/api/latest?access_key=$newApiKey";
            $response = file_get_contents($testKeyUrl);
        }

        $apiData = json_decode($response, true);
        if ($apiData['success'] && $apiData['success'] == 1) {
            if (!empty($newApiKey)) {
                $insertNewKey = "INSERT INTO fixer (api_key, provider, user_id) VALUES (:api_key, :provider, :userId)";
                $stmt = $pdo->prepare($insertNewKey);
                $stmt->bindParam(":api_key", $newApiKey, PDO::PARAM_STR);
                $stmt->bindParam(":provider", $provider, PDO::PARAM_INT);
                $stmt->bindParam(":userId", $userId, PDO::PARAM_INT);
                $stmt->execute();
                // PDO conversion - removed result check
                    echo json_encode(["success" => true, "message" => translate('api_key_saved', $i18n)]);
                } else {
                    $response = [
                        "success" => false,
                        "message" => translate('failed_to_store_api_key', $i18n)
                    ];
                    echo json_encode($response);
                }
            } else {
                echo json_encode(["success" => true, "message" => translate('apy_key_saved', $i18n)]);
            }
        } else {
            $response = [
                "success" => false,
                "message" => translate('invalid_api_key', $i18n)
            ];
            echo json_encode($response);
        }
    }
}

?>
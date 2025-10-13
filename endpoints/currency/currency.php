<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] == "add") {
        $currencyName = "Currency";
        $currencySymbol = "$";
        $currencyCode = "CODE";
        $currencyRate = 1;
        $sqlInsert = "INSERT INTO currencies (name, symbol, code, rate, user_id) VALUES (:name, :symbol, :code, :rate, :userId)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $currencyName, PDO::PARAM_STR);
        $stmtInsert->bindParam(':symbol', $currencySymbol, PDO::PARAM_STR);
        $stmtInsert->bindParam(':code', $currencyCode, PDO::PARAM_STR);
        $stmtInsert->bindParam(':rate', $currencyRate, PDO::PARAM_STR);
        $stmtInsert->bindParam(':userId', $userId, PDO::PARAM_INT);
        $resultInsert = $stmtInsert->execute();

        if ($resultInsert) {
            $currencyId = $db->lastInsertRowID();
            echo $currencyId;
        } else {
            echo translate('error_adding_currency', $i18n);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "edit") {
        if (isset($_GET['currencyId']) && $_GET['currencyId'] != "" && isset($_GET['name']) && $_GET['name'] != "" && isset($_GET['symbol']) && $_GET['symbol'] != "") {
            $currencyId = $_GET['currencyId'];
            $name = validate($_GET['name']);
            $symbol = validate($_GET['symbol']);
            $code = validate($_GET['code']);
            $sql = "UPDATE currencies SET name = :name, symbol = :symbol, code = :code WHERE id = :currencyId AND user_id = :userId";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':symbol', $symbol, PDO::PARAM_STR);
            $stmt->bindParam(':code', $code, PDO::PARAM_STR);
            $stmt->bindParam(':currencyId', $currencyId, PDO::PARAM_INT);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            // PDO conversion - removed result check
                $response = [
                    "success" => true,
                    "message" => $name . " " . translate('currency_saved', $i18n)
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "message" => translate('failed_to_store_currency', $i18n)
                ];
                echo json_encode($response);
            }
        } else {
            $response = [
                "success" => false,
                "message" => translate('fields_missing', $i18n)
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "delete") {
        if (isset($_GET['currencyId']) && $_GET['currencyId'] != "") {
            $query = "SELECT main_currency FROM users WHERE id = :userId";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $mainCurrencyId = $row['main_currency'];

            $currencyId = $_GET['currencyId'];
            $checkQuery = "SELECT COUNT(*) FROM subscriptions WHERE currency_id = :currencyId AND user_id = :userId";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':currencyId', $currencyId, PDO::PARAM_INT);
            $checkStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $checkResult = $checkStmt->execute();
            $row = $checkResult->fetchArray();
            $count = $row[0];

            if ($count > 0) {
                $response = [
                    "success" => false,
                    "message" => translate('currency_in_use', $i18n)
                ];
                echo json_encode($response);
                exit;
            } else {
                if ($currencyId == $mainCurrencyId) {
                    $response = [
                        "success" => false,
                        "message" => translate('currency_is_main', $i18n)
                    ];
                    echo json_encode($response);
                    exit;
                } else {
                    $sql = "DELETE FROM currencies WHERE id = :currencyId AND user_id = :userId";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':currencyId', $currencyId, PDO::PARAM_INT);
                    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                    $stmt->execute();
                    // PDO conversion - removed result check
                        echo json_encode(["success" => true, "message" => translate('currency_removed', $i18n)]);
                    } else {
                        $response = [
                            "success" => false,
                            "message" => translate('failed_to_remove_currency', $i18n)
                        ];
                        echo json_encode($response);
                    }
                }
            }
        } else {
            $response = [
                "success" => false,
                "message" => translate('fields_missing', $i18n)
            ];
            echo json_encode($response);
        }
    } else {
        echo "Error";
    }
} else {
    $response = [
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ];
    echo json_encode($response);
}

?>
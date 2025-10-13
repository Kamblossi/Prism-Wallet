<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] == "add") {
        $stmt = $pdo->prepare('SELECT MAX("order") as maxOrder FROM categories WHERE user_id = :userId');
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $maxOrder = $row['maxOrder'];

        if ($maxOrder === NULL) {
            $maxOrder = 0;
        }

        $order = $maxOrder + 1;

        $categoryName = "Category";
        $sqlInsert = 'INSERT INTO categories ("name", "order", "user_id") VALUES (:name, :order, :userId)';
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->bindParam(':name', $categoryName, PDO::PARAM_STR);
        $stmtInsert->bindParam(':order', $order, PDO::PARAM_INT);
        $stmtInsert->bindParam(':userId', $userId, PDO::PARAM_INT);
        $resultInsert = $stmtInsert->execute();

        if ($resultInsert) {
            $categoryId = $db->lastInsertRowID();
            $response = [
                "success" => true,
                "categoryId" => $categoryId
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('failed_add_category', $i18n)
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "edit") {
        if (isset($_GET['categoryId']) && $_GET['categoryId'] != "" && isset($_GET['name']) && $_GET['name'] != "") {
            $categoryId = $_GET['categoryId'];
            $name = validate($_GET['name']);
            $sql = "UPDATE categories SET name = :name WHERE id = :categoryId AND user_id = :userId";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();

            // PDO conversion - removed result check
                $response = [
                    "success" => true,
                    "message" => translate('category_saved', $i18n)
                ];
                echo json_encode($response);
            } else {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('failed_edit_category', $i18n)
                ];
                echo json_encode($response);
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('fill_all_fields', $i18n)
            ];
            echo json_encode($response);
        }
    } else if (isset($_GET['action']) && $_GET['action'] == "delete") {
        if (isset($_GET['categoryId']) && $_GET['categoryId'] != "" && $_GET['categoryId'] != 1) {
            $categoryId = $_GET['categoryId'];
            $checkCategory = "SELECT COUNT(*) FROM subscriptions WHERE category_id = :categoryId AND user_id = :userId";
            $checkStmt = $pdo->prepare($checkCategory);
            $checkStmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
            $checkStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $checkResult = $checkStmt->execute();
            $row = $checkResult->fetchArray();
            $count = $row[0];

            if ($count > 0) {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('category_in_use', $i18n)
                ];
                echo json_encode($response);
            } else {
                $sql = "DELETE FROM categories WHERE id = :categoryId AND user_id = :userId";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();
                // PDO conversion - removed result check
                    $response = [
                        "success" => true,
                        "message" => translate('category_removed', $i18n)
                    ];
                    echo json_encode($response);
                } else {
                    $response = [
                        "success" => false,
                        "errorMessage" => translate('failed_remove_category', $i18n)
                    ];
                    echo json_encode($response);
                }
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('failed_remove_category', $i18n)
            ];
            echo json_encode($response);
        }
    } else {
        echo translate('error', $i18n);
    }
} else {
    echo translate('error', $i18n);
}

?>
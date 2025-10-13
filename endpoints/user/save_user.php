<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (!file_exists('../../images/uploads/logos')) {
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}

function update_exchange_rate($db, $userId)
{
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
            $query = "SELECT id, name, symbol, code FROM currencies";
            $result = $db->query($query);
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
                }
                $currentDate = new DateTime();
                $formattedDate = $currentDate->format('Y-m-d');

                $query = "SELECT * FROM last_exchange_update WHERE user_id = :userId";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $query = "UPDATE last_exchange_update SET date = :formattedDate WHERE user_id = :userId";
                } else {
                    $query = "INSERT INTO last_exchange_update (date, user_id) VALUES (:formattedDate, :userId)";
                }

                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':formattedDate', $formattedDate, PDO::PARAM_STR);
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $resutl = $stmt->execute();

                $db->close();
            }
        }
    }
}

$demoMode = getenv('DEMO_MODE');

$query = "SELECT main_currency FROM users WHERE id = :userId";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$mainCurrencyId = $row['main_currency'];

function sanitizeFilename($filename)
{
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    $filename = str_replace(" ", "-", $filename);
    $filename = str_replace(".", "", $filename);
    return $filename;
}

function validateFileExtension($fileExtension)
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'jtif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function resizeAndUploadAvatar($uploadedFile, $uploadDir, $name)
{
    $targetWidth = 80;
    $targetHeight = 80;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-avatars-' . sanitizeFilename($name) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $fileName;

    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        $fileInfo = getimagesize($uploadFile);

        if ($fileInfo !== false) {
            $width = $fileInfo[0];
            $height = $fileInfo[1];

            // Load the image based on its format
            if ($fileExtension === 'png') {
                $image = imagecreatefrompng($uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $image = imagecreatefromjpeg($uploadFile);
            } elseif ($fileExtension === 'gif') {
                $image = imagecreatefromgif($uploadFile);
            } elseif ($fileExtension === 'webp') {
                $image = imagecreatefromwebp($uploadFile);
            } else {
                // Handle other image formats as needed
                return "";
            }

            // Enable alpha channel (transparency) for PNG images
            if ($fileExtension === 'png') {
                imagesavealpha($image, true);
            }

            $newWidth = $width;
            $newHeight = $height;

            if ($width > $targetWidth) {
                $newWidth = (int)$targetWidth;
                $newHeight = (int)(($targetWidth / $width) * $height);
            }

            if ($newHeight > $targetHeight) {
                $newWidth = (int)(($targetHeight / $newHeight) * $newWidth);
                $newHeight = (int)$targetHeight;
            }

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($resizedImage, true);
            $transparency = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
            imagefill($resizedImage, 0, 0, $transparency);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            if ($fileExtension === 'png') {
                imagepng($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                imagejpeg($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'gif') {
                imagegif($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'webp') {
                imagewebp($resizedImage, $uploadFile);
            } else {
                return "";
            }

            imagedestroy($image);
            imagedestroy($resizedImage);
            return "images/uploads/logos/avatars/" . $fileName;
        }
    }

    return "";
}

if (
    isset($_SESSION['username']) 
    && isset($_POST['firstname'])
    && isset($_POST['lastname'])
    && isset($_POST['email']) && $_POST['email'] !== ""
    && isset($_POST['avatar']) && $_POST['avatar'] !== ""
    && isset($_POST['main_currency']) && $_POST['main_currency'] !== ""
    && isset($_POST['language']) && $_POST['language'] !== ""
) {

    $firstname = validate($_POST['firstname']);
    $lastname = validate($_POST['lastname']);
    $email = validate($_POST['email']);

    $query = "SELECT email FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $oldEmail = $user['email'];

    if ($oldEmail != $email) {
        $query = "SELECT email FROM users WHERE email = :email AND id != :userId";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($otherUser) {
            $response = [
                "success" => false,
                "errorMessage" => translate('email_exists', $i18n)
            ];
            echo json_encode($response);
            exit();
        }
    }

    $avatar = filter_var($_POST['avatar'], FILTER_SANITIZE_URL);
    $main_currency = $_POST['main_currency'];
    $language = $_POST['language'];

    if (!empty($_FILES['profile_pic']["name"])) {
        $file = $_FILES['profile_pic'];

        $fileType = mime_content_type($_FILES['profile_pic']['tmp_name']);
        if (strpos($fileType, 'image') === false) {
            $response = [
                "success" => false,
                "errorMessage" => translate('fill_all_fields', $i18n)
            ];
            echo json_encode($response);
            exit();
        }
        $name = $file['name'];
        $avatar = resizeAndUploadAvatar($_FILES['profile_pic'], '../../images/uploads/logos/avatars/', $name);
    }

    if (isset($_POST['password']) && $_POST['password'] != "" && !$demoMode) {
        $password = $_POST['password'];
        if (isset($_POST['confirm_password'])) {
            $confirm = $_POST['confirm_password'];
            if ($password != $confirm) {
                $response = [
                    "success" => false,
                    "errorMessage" => translate('passwords_dont_match', $i18n)
                ];
                echo json_encode($response);
                exit();
            }
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('passwords_dont_match', $i18n)
            ];
            echo json_encode($response);
            exit();
        }
    }

    if (isset($_POST['password']) && $_POST['password'] != "" && !$demoMode) {
        $sql = "UPDATE users SET avatar = :avatar, firstname = :firstname, lastname = :lastname, email = :email, password = :password, main_currency = :main_currency, language = :language WHERE id = :userId";
    } else {
        $sql = "UPDATE users SET avatar = :avatar, firstname = :firstname, lastname = :lastname, email = :email, main_currency = :main_currency, language = :language WHERE id = :userId";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':avatar', $avatar, PDO::PARAM_STR);
    $stmt->bindParam(':firstname', $firstname, PDO::PARAM_STR);
    $stmt->bindParam(':lastname', $lastname, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':main_currency', $main_currency, PDO::PARAM_INT);
    $stmt->bindParam(':language', $language, PDO::PARAM_STR);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if (isset($_POST['password']) && $_POST['password'] != "" && !$demoMode) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
    }

    $stmt->execute();

    // PDO conversion - removed result check
        $cookieExpire = time() + (30 * 24 * 60 * 60);
        $oldLanguage = isset($_COOKIE['language']) ? $_COOKIE['language'] : "en";
        $root = str_replace('/endpoints/user', '', dirname($_SERVER['PHP_SELF']));
        $root = $root == '' ? '/' : $root;
        setcookie('language', $language, [
            'path' => $root,
            'expires' => $cookieExpire,
            'samesite' => 'Strict'
        ]);
        $_SESSION['firstname'] = $firstname;
        $_SESSION['avatar'] = $avatar;
        $_SESSION['main_currency'] = $main_currency;

        if ($main_currency != $mainCurrencyId) {
            update_exchange_rate($db, $userId);
        }

        $reload = $oldLanguage != $language;

        $response = [
            "success" => true,
            "message" => translate('user_details_saved', $i18n),
            "reload" => $reload
        ];
        echo json_encode($response);
    } else {
        $response = [
            "success" => false,
            "errorMessage" => translate('error_updating_user_data', $i18n)
        ];
        echo json_encode($response);
    }

    exit();
} else {
    $response = [
        "success" => false,
        "errorMessage" => translate('fill_all_fields', $i18n)
    ];
    echo json_encode($response);
    exit();
}
?>

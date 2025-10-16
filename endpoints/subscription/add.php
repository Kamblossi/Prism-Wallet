<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';

if (!file_exists('../../images/uploads/logos')) {
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}

function sanitizeFilename($filename)
{
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    $filename = str_replace(" ", "-", $filename);
    $filename = str_replace(".", "", $filename);
    return $filename;
}

function validateFileExtension($fileExtension)
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function getLogoFromUrl($url, $uploadDir, $name, $settings, $i18n)
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        $response = [
            "success" => false,
            "errorMessage" => "Invalid URL format."
        ];
        echo json_encode($response);
        exit();
    }

    $host = parse_url($url, PHP_URL_HOST);
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $response = [
            "success" => false,
            "errorMessage" => "Invalid IP Address."
        ];
        echo json_encode($response);
        exit();
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

    $imageData = curl_exec($ch);

    if ($imageData !== false) {
        $timestamp = time();
        $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
        $uploadDir = '../../images/uploads/logos/';
        $uploadFile = $uploadDir . $fileName;

        if (saveLogo($imageData, $uploadFile, $name, $settings)) {
            curl_close($ch);
            return $fileName;
        } else {
            echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
            curl_close($ch);
            return "";
        }

    } else {
        echo translate('error_fetching_image', $i18n) . ": " . curl_error($ch);
        curl_close($ch);
        return "";
    }
}


function saveLogo($imageData, $uploadFile, $name, $settings)
{
    $image = imagecreatefromstring($imageData);
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';
    if ($image !== false) {
        $tempFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng($image, $tempFile);
        imagedestroy($image);

        if (extension_loaded('imagick')) {
            $imagick = new Imagick($tempFile);
            if ($removeBackground) {
                $fuzz = Imagick::getQuantum() * 0.1; // 10%
                $imagick->transparentPaintImage("rgb(247, 247, 247)", 0, $fuzz, false);
            }
            $imagick->setImageFormat('png');
            $imagick->writeImage($uploadFile);

            $imagick->clear();
            $imagick->destroy();
        } else {
            // Alternative method if Imagick is not available
            $newImage = imagecreatefrompng($tempFile);
            if ($newImage !== false) {
                if ($removeBackground) {
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                    imagefill($newImage, 0, 0, $transparent);  // Fill the entire image with transparency
                    imagepng($newImage, $uploadFile);
                    imagedestroy($newImage);
                }
                imagepng($newImage, $uploadFile);
                imagedestroy($newImage);
            } else {
                unlink($tempFile);
                return false;
            }
        }
        unlink($tempFile);

        return true;
    } else {
        return false;
    }
}

function resizeAndUploadLogo($uploadedFile, $uploadDir, $name, $settings)
{
    $targetWidth = 135;
    $targetHeight = 42;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-' . sanitizeFilename($name) . '.' . $fileExtension;
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
                $newWidth = (int) $targetWidth;
                $newHeight = (int) (($targetWidth / $width) * $height);
            }

            if ($newHeight > $targetHeight) {
                $newWidth = (int) (($targetHeight / $newHeight) * $newWidth);
                $newHeight = (int) $targetHeight;
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

            return $fileName;
        }
    }

    return "";
}

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $isEdit = isset($_POST['id']) && $_POST['id'] !== "";
        $name = validate($_POST["name"] ?? "");
        $priceRaw = trim($_POST['price'] ?? '');
        $currencyId = (int)($_POST["currency_id"] ?? 0);
        $frequency = (int)($_POST["frequency"] ?? 0);
        $cycle = (int)($_POST["cycle"] ?? 0);
        $nextPayment = trim($_POST["next_payment"] ?? '');
        $startDate = trim($_POST["start_date"] ?? '');
        $autoRenew = isset($_POST['auto_renew']) ? 1 : 0;
        $paymentMethodId = (int)($_POST["payment_method_id"] ?? 0);
        $payerUserId = (int)($_POST["payer_user_id"] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $notes = validate($_POST["notes"] ?? "");
        $notes = $notes === "" ? null : $notes;
        $url = validate($_POST['url'] ?? "");
        $url = $url === "" ? null : $url;
        $logoUrl = validate($_POST['logo-url'] ?? "");
        $logo = null;
        $notify = isset($_POST['notifications']) ? 1 : 0;
        $notifyDaysBeforeRaw = $_POST['notify_days_before'] ?? null;
        if (!$notify) {
            $notifyDaysBefore = -1;
        } elseif ($notifyDaysBeforeRaw === '' || $notifyDaysBeforeRaw === null) {
            $notifyDaysBefore = -1;
        } else {
            $notifyDaysBefore = (int)$notifyDaysBeforeRaw;
        }
        $inactive = isset($_POST['inactive']) ? 1 : 0;
        $cancellationDate = $_POST['cancellation_date'] ?? null;
        if ($cancellationDate === '') {
            $cancellationDate = null;
        }
        $replacementSubscriptionId = $_POST['replacement_subscription_id'] ?? null;
        if (empty($replacementSubscriptionId) || $replacementSubscriptionId == 0 || $inactive === 0) {
            $replacementSubscriptionId = null;
        } else {
            $replacementSubscriptionId = (int)$replacementSubscriptionId;
        }

        if ($name === "" || $priceRaw === '' || $currencyId === 0 || $frequency === 0 || $cycle === 0 || $nextPayment === '' || $startDate === '' || $paymentMethodId === 0 || $payerUserId === 0 || $categoryId === 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'Error',
                'message' => translate("fill_all_fields", $i18n)
            ]);
            exit();
        }

        if ($logoUrl !== "") {
            $logoFromUrl = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name, $settings, $i18n);
            $logo = $logoFromUrl !== "" ? $logoFromUrl : null;
        } elseif (!empty($_FILES['logo']['name'])) {
            $fileType = mime_content_type($_FILES['logo']['tmp_name']);
            if (strpos($fileType, 'image') === false) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'Error',
                    'message' => translate("fill_all_fields", $i18n)
                ]);
                exit();
            }
            $uploadedLogo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name, $settings);
            $logo = $uploadedLogo !== "" ? $uploadedLogo : null;
        }

        $nextPayment = $nextPayment === '' ? null : $nextPayment;
        $startDate = $startDate === '' ? null : $startDate;
        $price = $priceRaw;

        if (!$isEdit) {
            $sql = "INSERT INTO subscriptions (
                        name, logo, price, currency_id, next_payment, cycle, frequency, notes, 
                        payment_method_id, payer_user_id, category_id, notify, inactive, url, 
                        notify_days_before, user_id, cancellation_date, replacement_subscription_id,
                        auto_renew, start_date
                    ) VALUES (
                        :name, :logo, :price, :currencyId, :nextPayment, :cycle, :frequency, :notes, 
                        :paymentMethodId, :payerUserId, :categoryId, :notify, :inactive, :url, 
                        :notifyDaysBefore, :userId, :cancellationDate, :replacement_subscription_id,
                        :autoRenew, :startDate
                    )";
        } else {
            $id = $_POST['id'];
            $sql = "UPDATE subscriptions SET 
                        name = :name, 
                        price = :price, 
                        currency_id = :currencyId,
                        next_payment = :nextPayment, 
                        auto_renew = :autoRenew,
                        start_date = :startDate,
                        cycle = :cycle, 
                        frequency = :frequency, 
                        notes = :notes, 
                        payment_method_id = :paymentMethodId,
                        payer_user_id = :payerUserId, 
                        category_id = :categoryId, 
                        notify = :notify, 
                        inactive = :inactive, 
                        url = :url, 
                        notify_days_before = :notifyDaysBefore, 
                        cancellation_date = :cancellationDate, 
                        replacement_subscription_id = :replacement_subscription_id";

            if ($logo !== null) {
                $sql .= ", logo = :logo";
            }

            $sql .= " WHERE id = :id AND user_id = :userId";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        if ($isEdit) {
            if ($logo !== null) {
                $stmt->bindValue(':logo', $logo, PDO::PARAM_STR);
            }
        } else {
            $stmt->bindValue(':logo', $logo, $logo !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        }
        $stmt->bindValue(':price', $price, PDO::PARAM_STR);
        $stmt->bindValue(':currencyId', $currencyId, PDO::PARAM_INT);
        $stmt->bindValue(':nextPayment', $nextPayment, $nextPayment !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':autoRenew', $autoRenew, PDO::PARAM_INT);
        $stmt->bindValue(':startDate', $startDate, $startDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':cycle', $cycle, PDO::PARAM_INT);
        $stmt->bindValue(':frequency', $frequency, PDO::PARAM_INT);
        $stmt->bindValue(':notes', $notes, $notes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':paymentMethodId', $paymentMethodId, PDO::PARAM_INT);
        $stmt->bindValue(':payerUserId', $payerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':notify', $notify, PDO::PARAM_INT);
        $stmt->bindValue(':inactive', $inactive, PDO::PARAM_INT);
        $stmt->bindValue(':url', $url, $url !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':notifyDaysBefore', $notifyDaysBefore, PDO::PARAM_INT);
        $stmt->bindValue(':cancellationDate', $cancellationDate, $cancellationDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        if ($replacementSubscriptionId !== null) {
            $stmt->bindValue(':replacement_subscription_id', $replacementSubscriptionId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':replacement_subscription_id', null, PDO::PARAM_NULL);
        }

        if ($isEdit) {
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        }

        try {
            $stmt->execute();
            $success['status'] = "Success";
            $text = $isEdit ? "updated" : "added";
            $success['message'] = translate('subscription_' . $text . '_successfuly', $i18n);
            $json = json_encode($success);
            header('Content-Type: application/json');
            echo $json;
            exit();
        } catch (PDOException $e) {
            error_log('Subscription save failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'Error',
                'message' => translate('error', $i18n) . ': ' . $e->getMessage()
            ]);
            exit();
        }
    }
}
$db->close();
?>

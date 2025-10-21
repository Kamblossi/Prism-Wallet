<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';
require_once '../../includes/endpoint_helpers.php';
require_once '../../includes/storage.php';

// Local temp dir for processing; final write via storage abstraction
$logosLocal = sys_get_temp_dir() . '/prism-logos';
if (!is_dir($logosLocal)) { @mkdir($logosLocal, 0777, true); }
$avatarsLocal = $logosLocal . '/avatars';
if (!is_dir($avatarsLocal)) { @mkdir($avatarsLocal, 0777, true); }

function sanitizeFilename(string $filename): string
{
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    $filename = str_replace(" ", "-", $filename);
    $filename = str_replace(".", "", $filename);
    return $filename;
}

function validateFileExtension(string $fileExtension): bool
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'jtif', 'webp'];
    return in_array(strtolower($fileExtension), $allowedExtensions, true);
}

function ensureImageString(string $data, string $errorMessage)
{
    $resource = @imagecreatefromstring($data);
    if ($resource === false) {
        json_error($errorMessage, 422);
    }
    return $resource;
}

function saveLogo(string $imageData, string $finalRelativePath, array $settings, string $errorMessage): string
{
    $image = ensureImageString($imageData, $errorMessage);
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';

    $tempFile = tempnam(sys_get_temp_dir(), 'logo');
    imagepng($image, $tempFile);
    imagedestroy($image);

    try {
        if (extension_loaded('imagick')) {
            $imagick = new Imagick($tempFile);
            if ($removeBackground) {
                $fuzz = Imagick::getQuantum() * 0.1;
                $imagick->transparentPaintImage("rgb(247, 247, 247)", 0, $fuzz, false);
            }
            $imagick->setImageFormat('png');
            // write to temp then push to storage
            $tmpOut = tempnam(sys_get_temp_dir(), 'logo_out');
            $imagick->writeImage($tmpOut);
            $imagick->clear();
            $imagick->destroy();
            storage_put_file($finalRelativePath, $tmpOut, 'image/png');
            @unlink($tmpOut);
        } else {
            $newImage = imagecreatefrompng($tempFile);
            if ($removeBackground) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                imagefill($newImage, 0, 0, $transparent);
            }
            $tmpOut = tempnam(sys_get_temp_dir(), 'logo_out');
            imagepng($newImage, $tmpOut);
            imagedestroy($newImage);
            storage_put_file($finalRelativePath, $tmpOut, 'image/png');
            @unlink($tmpOut);
        }
    } catch (Throwable $e) {
        unlink($tempFile);
        json_error($errorMessage, 500);
    }

    unlink($tempFile);
    return basename($finalRelativePath);
}

function resizeAndUploadLogo(array $uploadedFile, string $name, string $errorMessage): string
{
    if (!is_uploaded_file($uploadedFile['tmp_name'])) {
        json_error($errorMessage, 400);
    }

    $targetWidth = 70;
    $targetHeight = 48;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileExtension = validateFileExtension($fileExtension) ? strtolower($fileExtension) : 'png';
    $fileName = $timestamp . '-payments-' . sanitizeFilename($name) . '.' . $fileExtension;
    $tmpUpload = sys_get_temp_dir() . '/' . $fileName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $tmpUpload)) {
        json_error($errorMessage, 500);
    }

    $fileInfo = @getimagesize($tmpUpload);
    if ($fileInfo === false) {
        unlink($tmpUpload);
        json_error($errorMessage, 415);
    }

    [$width, $height] = $fileInfo;
    switch ($fileExtension) {
        case 'png':
            $image = imagecreatefrompng($tmpUpload);
            break;
        case 'jpg':
        case 'jpeg':
            $image = imagecreatefromjpeg($tmpUpload);
            break;
        case 'gif':
            $image = imagecreatefromgif($tmpUpload);
            break;
        case 'webp':
            $image = imagecreatefromwebp($tmpUpload);
            break;
        default:
            unlink($tmpUpload);
            json_error($errorMessage, 415);
    }

    if ($fileExtension === 'png') {
        imagesavealpha($image, true);
    }

    $newWidth = $width;
    $newHeight = $height;

    if ($width > $targetWidth) {
        $newWidth = $targetWidth;
        $newHeight = (int) round(($targetWidth / $width) * $height);
    }
    if ($newHeight > $targetHeight) {
        $newWidth = (int) round(($targetHeight / $newHeight) * $newWidth);
        $newHeight = $targetHeight;
    }

    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    imagesavealpha($resizedImage, true);
    $transparency = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
    imagefill($resizedImage, 0, 0, $transparency);
    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Always upload as original extension for now
    $tmpOut = tempnam(sys_get_temp_dir(), 'logo_out');
    switch ($fileExtension) {
        case 'png': imagepng($resizedImage, $tmpOut); break;
        case 'jpg':
        case 'jpeg': imagejpeg($resizedImage, $tmpOut); break;
        case 'gif': imagegif($resizedImage, $tmpOut); break;
        case 'webp': imagewebp($resizedImage, $tmpOut); break;
    }

    $relativePath = 'logos/' . $fileName;
    storage_put_file($relativePath, $tmpOut, mime_content_type($tmpOut) ?: null);
    @unlink($tmpOut);

    imagedestroy($image);
    imagedestroy($resizedImage);

    return $fileName;
}

function fetchLogoFromUrl(string $url, string $name, array $settings, $i18n): string
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        json_error('Invalid URL format for icon.', 400);
    }

    $host = parse_url($url, PHP_URL_HOST);
    $ip = gethostbyname($host);
    if (!$ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        json_error('Icon host resolves to a private or reserved IP address.', 400);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ]);

    $imageData = curl_exec($ch);
    if ($imageData === false) {
        $err = curl_error($ch);
        curl_close($ch);
        $message = translate('error_fetching_image', $i18n);
        if ($err) {
            $message .= ': ' . $err;
        }
        json_error($message, 502);
    }
    curl_close($ch);

    $fileName = time() . '-payments-' . sanitizeFilename($name) . '.png';
    $relativePath = 'logos/' . $fileName;
    return saveLogo($imageData, $relativePath, $settings, translate('error_fetching_image', $i18n));
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    json_error(translate('session_expired', $i18n), 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(translate('invalid_request_method', $i18n) ?? 'Invalid request', 405);
}

$name = validate($_POST['paymentname'] ?? '');
$iconUrl = validate($_POST['icon-url'] ?? '');

if ($name === '') {
    json_error(translate('fill_all_fields', $i18n), 422);
}

if ($iconUrl === '' && empty($_FILES['paymenticon']['name'])) {
    json_error(translate('fill_all_fields', $i18n), 422);
}

$icon = '';
if ($iconUrl !== '') {
    $icon = fetchLogoFromUrl($iconUrl, $name, $settings, $i18n);
} elseif (!empty($_FILES['paymenticon']['name'])) {
    $fileType = mime_content_type($_FILES['paymenticon']['tmp_name']);
    if (strpos($fileType, 'image') === false) {
        json_error(translate('fill_all_fields', $i18n), 415);
    }
    $icon = resizeAndUploadLogo($_FILES['paymenticon'], $name, translate('error', $i18n));
}

try {
    // Determine custom id namespace (legacy behaviour keeps predefined ids < 32)
    $maxIdStmt = $pdo->query('SELECT COALESCE(MAX(id), 31) FROM payment_methods');
    $newId = (int)$maxIdStmt->fetchColumn() + 1;
    if ($newId < 32) {
        $newId = 32;
    }

    $orderStmt = $pdo->prepare('SELECT COALESCE(MAX("order"), 0) FROM payment_methods WHERE user_id = :uid');
    $orderStmt->execute([':uid' => $userId]);
    $nextOrder = (int)$orderStmt->fetchColumn() + 1;

    $insert = $pdo->prepare('INSERT INTO payment_methods (id, name, icon, enabled, "order", user_id)
                              VALUES (:id, :name, :icon, TRUE, :ord, :uid)');
    $insert->bindValue(':id', $newId, PDO::PARAM_INT);
    $insert->bindValue(':name', $name, PDO::PARAM_STR);
    $insert->bindValue(':icon', $icon !== '' ? $icon : null, $icon !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $insert->bindValue(':ord', $nextOrder, PDO::PARAM_INT);
    $insert->bindValue(':uid', $userId, PDO::PARAM_INT);
    $insert->execute();
} catch (Throwable $e) {
    error_log('[payments/add] insert failed: ' . $e->getMessage());
    json_error(translate('error', $i18n) . ': ' . $e->getMessage(), 500);
}

json_success(translate('payment_method_added_successfuly', $i18n) ?? 'Payment method added', [
    'id' => $newId,
    'name' => $name,
    'icon' => $icon,
]);

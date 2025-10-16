<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- convert_currency: whether to convert to the main currency (boolean) default false.
- api_key: the API key of the user.

It returns a downloadable VCAL file with the active subscriptions
*/

require_once '../../includes/connect_endpoint.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    // if the parameters are not set, return an error

    $apiKey = $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;

    if (!$apiKey) {
        $response = [
            "success" => false,
            "title" => "Missing parameters"
        ];
        echo json_encode($response);
        exit;
    }

    function getPriceConverted($price, $currency, $database)
    {
        $query = "SELECT rate FROM currencies WHERE id = :currency";
        $stmt = $database->prepare($query);
        $stmt->bindParam(':currency', $currency, PDO::PARAM_INT);
        $stmt->execute();

        $exchangeRate = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($exchangeRate === false) {
            return $price;
        } else {
            $fromRate = $exchangeRate['rate'];
            return $price / $fromRate;
        }
    }

    // Get user from API key
    $sql = "SELECT * FROM users WHERE api_key = :apiKey";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':apiKey', $apiKey);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If the user is not found, return an error
    if (!$user) {
        $response = [
            "success" => false,
            "title" => "Invalid API key"
        ];
        echo json_encode($response);
        exit;
    }

    $userId = $user['id'];
    $userCurrencyId = $user['main_currency'];

    // Get last exchange update date for user
    $sql = "SELECT * FROM last_exchange_update WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $lastExchangeUpdate = $stmt->fetch(PDO::FETCH_ASSOC);

    $canConvertCurrency = empty($lastExchangeUpdate['date']) ? false : true;

    // Get currencies for user
    $sql = "SELECT * FROM currencies WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $currencies = [];
    while ($currency = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currencies[$currency['id']] = $currency;
    }

    // Get categories for user
    $sql = "SELECT * FROM categories WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $categories = [];
    while ($category = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[$category['id']] = $category['name'];
    }

    // Get members for user
    $sql = "SELECT * FROM household WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $members = [];
    while ($member = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $members[$member['id']] = $member['name'];
    }

    // Get payment methods for user
    $sql = "SELECT * FROM payment_methods WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $paymentMethods = [];
    while ($paymentMethod = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paymentMethods[$paymentMethod['id']] = $paymentMethod['name'];
    }

    $sql = "SELECT * FROM subscriptions WHERE user_id = :userId AND inactive = FALSE ORDER BY next_payment ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();

    // Fetch all active subscriptions
    $subscriptions = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subscriptions[] = $row;
    }

    $subscriptionsToReturn = array();

    // Get notification settings
    $notificationQuery = "SELECT days FROM notification_settings WHERE user_id = :userId";
    $notificationQueryStmt = $pdo->prepare($notificationQuery);
    $notificationQueryStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $notificationResult = $notificationQueryStmt->execute();
    $globalNotificationDays = 1; // Default value
    if ($row = $notificationResult->fetch(PDO::FETCH_ASSOC)) {
        $globalNotificationDays = $row['days'];
    }

    foreach ($subscriptions as $subscription) {
        $subscriptionToReturn = $subscription;

        if (isset($_REQUEST['convert_currency']) && $_REQUEST['convert_currency'] === 'true' && $canConvertCurrency && $subscription['currency_id'] != $userCurrencyId) {
            $subscriptionToReturn['price'] = getPriceConverted($subscription['price'], $subscription['currency_id'], $db);
        } else {
            $subscriptionToReturn['price'] = $subscription['price'];
        }

        $subscriptionToReturn['category_name'] = $categories[$subscription['category_id']];
        $subscriptionToReturn['payer_user_name'] = $members[$subscription['payer_user_id']];
        $subscriptionToReturn['payment_method_name'] = $paymentMethods[$subscription['payment_method_id']];

        $subscriptionsToReturn[] = $subscriptionToReturn;
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscriptions.ics"');

    $icsContent = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Wallos//iCalendar//EN\nNAME:Wallos\nX-WR-CALNAME:Wallos\n";

    foreach ($subscriptions as $subscription) {
        $subscription['payer_user'] = $members[$subscription['payer_user_id']];
        $subscription['category'] = $categories[$subscription['category_id']];
        $subscription['payment_method'] = $paymentMethods[$subscription['payment_method_id']];
        $subscription['currency'] = $currencies[$subscription['currency_id']]['symbol'];
        $subscription['trigger'] = ($subscription['notify_days_before'] == -1) ? $globalNotificationDays : ($subscription['notify_days_before'] ?: 1);
        $subscription['price'] = number_format($subscription['price'], 2);

        $uid = uniqid();
        $summary = html_entity_decode($subscription['name'], ENT_QUOTES, 'UTF-8');
        $description = "Price: {$subscription['currency']}{$subscription['price']}\\nCategory: {$subscription['category']}\\nPayment Method: {$subscription['payment_method']}\\nPayer: {$subscription['payer_user']}\\nNotes: {$subscription['notes']}";
        $dtstart = (new DateTime($subscription['next_payment']))->format('Ymd');
        $dtend = (new DateTime($subscription['next_payment']))->format('Ymd');
        $location = isset($subscription['url']) ? $subscription['url'] : '';
        $alarm_trigger = '-P' . $subscription['trigger'] . 'D';

        $icsContent .= <<<ICS
        BEGIN:VEVENT
        UID:$uid
        SUMMARY:$summary
        DESCRIPTION:$description
        DTSTART:$dtstart
        DTEND:$dtend
        LOCATION:$location
        STATUS:CONFIRMED
        TRANSP:OPAQUE
        BEGIN:VALARM
        ACTION:DISPLAY
        DESCRIPTION:Reminder
        TRIGGER:$alarm_trigger
        END:VALARM
        END:VEVENT
        
        ICS;
    }

    $icsContent .= "END:VCALENDAR\n";
    echo $icsContent;
    $db->close();
    exit;
        


} else {
    $response = [
        "success" => false,
        "title" => "Invalid request method"
    ];
    echo json_encode($response);
    exit;
}


?>

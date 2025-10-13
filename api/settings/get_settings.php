<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user.

It returns a JSON object with the following properties:
- success: whether the request was successful (boolean).
- title: the title of the response (string).
- settings: an object containing the user settings.
- notes: warning messages or additional information (array).

Example response:
{
  "success": true,
  "title": "settings",
  "settings": {
    "dark_theme": 0,
    "monthly_price": 1,
    "convert_currency": 1,
    "remove_background": 1,
    "color_theme": "red",
    "hide_disabled": 0,
    "disabled_to_bottom": 1,
    "show_original_price": 0,
    "mobile_nav": 1,
    "custom_css": {
      "css": ""
    }
  },
  "notes": []
}
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

    $sql = "SELECT * FROM settings WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($settings) {
        unset($settings['user_id']);
    }

    $sql = "SELECT * FROM custom_colors WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $custom_colors = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($custom_colors) {
        unset($custom_colors['user_id']);
        $settings['custom_colors'] = $custom_colors;
    }
    

    $sql = "SELECT * FROM custom_css_style WHERE user_id = :userId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':userId', $userId);
    $stmt->execute();
    $custom_css = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($custom_css) {
        unset($custom_css['user_id']);
        $settings['custom_css'] = $custom_css;
    }

    $response = [
        "success" => true,
        "title" => "settings",
        "settings" => $settings,
        "notes" => []
    ];

    echo json_encode($response);

    $db->close();

} else {
    $response = [
        "success" => false,
        "title" => "Invalid request method"
    ];
    echo json_encode($response);
    exit;
}

?>
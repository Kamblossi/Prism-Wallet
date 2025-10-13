<?php

// Try to extract first and last name from "name"
$fullName = $userInfo['name'] ?? '';
$parts = explode(' ', trim($fullName), 2);
$firstname = $parts[0] ?? '';
$lastname = $parts[1] ?? '';

// Defaults
$language = 'en';
$avatar = "images/avatars/0.svg";
$budget = 0;
$main_currency_id = 1; // Euro
$password = bin2hex(random_bytes(16)); // 32-character random password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$query = "INSERT INTO userss (username, email, oidc_sub, main_currency, avatar, language, budget, firstname, lastname, password)
          VALUES (:username, :email, :oidc_sub, :main_currency, :avatar, :language, :budget, :firstname, :lastname, :password)";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':username', $username, PDO::PARAM_STR);
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->bindValue(':oidc_sub', $oidcSub, PDO::PARAM_STR);
$stmt->bindValue(':main_currency', $main_currency_id, PDO::PARAM_INT);
$stmt->bindValue(':avatar', $avatar, PDO::PARAM_STR);
$stmt->bindValue(':language', $language, PDO::PARAM_STR);
$stmt->bindValue(':budget', $budget, PDO::PARAM_INT);
$stmt->bindValue(':firstname', $firstname, PDO::PARAM_STR);
$stmt->bindValue(':lastname', $lastname, PDO::PARAM_STR);
$stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);

if (!$stmt->execute()) {
    die("Failed to create user");
}

// Get the user data into $userData
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
$stmt->bindValue(':username', $username, PDO::PARAM_STR);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$newUserId = $userData['id'];

// Household
$stmt = $pdo->prepare("INSERT INTO household (name, user_id) VALUES (:name, :user_id)");
$stmt->bindValue(':name', $username, PDO::PARAM_STR);
$stmt->bindValue(':user_id', $newUserId, PDO::PARAM_INT);
$stmt->execute();

// Categories
$categories = [
    'No category', 'Entertainment', 'Music', 'Utilities', 'Food & Beverages',
    'Health & Wellbeing', 'Productivity', 'Banking', 'Transport', 'Education',
    'Insurance', 'Gaming', 'News & Magazines', 'Software', 'Technology',
    'Cloud Services', 'Charity & Donations'
];

$stmt = $pdo->prepare("INSERT INTO categories (name, \"order\", user_id) VALUES (:name, :order, :user_id)");
foreach ($categories as $index => $name) {
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':order', $index + 1, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $newUserId, PDO::PARAM_INT);
    $stmt->execute();
}

// Payment Methods
$payment_methods = [
    ['name' => 'PayPal', 'icon' => 'images/uploads/icons/paypal.png'],
    ['name' => 'Credit Card', 'icon' => 'images/uploads/icons/creditcard.png'],
    ['name' => 'Bank Transfer', 'icon' => 'images/uploads/icons/banktransfer.png'],
    ['name' => 'Direct Debit', 'icon' => 'images/uploads/icons/directdebit.png'],
    ['name' => 'Money', 'icon' => 'images/uploads/icons/money.png'],
    ['name' => 'Google Pay', 'icon' => 'images/uploads/icons/googlepay.png'],
    ['name' => 'Samsung Pay', 'icon' => 'images/uploads/icons/samsungpay.png'],
    ['name' => 'Apple Pay', 'icon' => 'images/uploads/icons/applepay.png'],
    ['name' => 'Crypto', 'icon' => 'images/uploads/icons/crypto.png'],
    ['name' => 'Klarna', 'icon' => 'images/uploads/icons/klarna.png'],
    ['name' => 'Amazon Pay', 'icon' => 'images/uploads/icons/amazonpay.png'],
    ['name' => 'SEPA', 'icon' => 'images/uploads/icons/sepa.png'],
    ['name' => 'Skrill', 'icon' => 'images/uploads/icons/skrill.png'],
    ['name' => 'Sofort', 'icon' => 'images/uploads/icons/sofort.png'],
    ['name' => 'Stripe', 'icon' => 'images/uploads/icons/stripe.png'],
    ['name' => 'Affirm', 'icon' => 'images/uploads/icons/affirm.png'],
    ['name' => 'AliPay', 'icon' => 'images/uploads/icons/alipay.png'],
    ['name' => 'Elo', 'icon' => 'images/uploads/icons/elo.png'],
    ['name' => 'Facebook Pay', 'icon' => 'images/uploads/icons/facebookpay.png'],
    ['name' => 'GiroPay', 'icon' => 'images/uploads/icons/giropay.png'],
    ['name' => 'iDeal', 'icon' => 'images/uploads/icons/ideal.png'],
    ['name' => 'Union Pay', 'icon' => 'images/uploads/icons/unionpay.png'],
    ['name' => 'Interac', 'icon' => 'images/uploads/icons/interac.png'],
    ['name' => 'WeChat', 'icon' => 'images/uploads/icons/wechat.png'],
    ['name' => 'Paysafe', 'icon' => 'images/uploads/icons/paysafe.png'],
    ['name' => 'Poli', 'icon' => 'images/uploads/icons/poli.png'],
    ['name' => 'Qiwi', 'icon' => 'images/uploads/icons/qiwi.png'],
    ['name' => 'ShopPay', 'icon' => 'images/uploads/icons/shoppay.png'],
    ['name' => 'Venmo', 'icon' => 'images/uploads/icons/venmo.png'],
    ['name' => 'VeriFone', 'icon' => 'images/uploads/icons/verifone.png'],
    ['name' => 'WebMoney', 'icon' => 'images/uploads/icons/webmoney.png'],
];

$stmt = $pdo->prepare("INSERT INTO payment_methods (name, icon, \"order\", user_id) VALUES (:name, :icon, :order, :user_id)");
foreach ($payment_methods as $index => $method) {
    $stmt->bindValue(':name', $method['name'], PDO::PARAM_STR);
    $stmt->bindValue(':icon', $method['icon'], PDO::PARAM_STR);
    $stmt->bindValue(':order', $index + 1, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $newUserId, PDO::PARAM_INT);
    $stmt->execute();
}

// Currencies
$currencies = [
    ['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR'],
    ['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD'],
    ['name' => 'Japanese Yen', 'symbol' => '¥', 'code' => 'JPY'],
    ['name' => 'Bulgarian Lev', 'symbol' => 'лв', 'code' => 'BGN'],
    ['name' => 'Czech Republic Koruna', 'symbol' => 'Kč', 'code' => 'CZK'],
    ['name' => 'Danish Krone', 'symbol' => 'kr', 'code' => 'DKK'],
    ['name' => 'British Pound Sterling', 'symbol' => '£', 'code' => 'GBP'],
    ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'code' => 'HUF'],
    ['name' => 'Polish Zloty', 'symbol' => 'zł', 'code' => 'PLN'],
    ['name' => 'Romanian Leu', 'symbol' => 'lei', 'code' => 'RON'],
    ['name' => 'Swedish Krona', 'symbol' => 'kr', 'code' => 'SEK'],
    ['name' => 'Swiss Franc', 'symbol' => 'Fr', 'code' => 'CHF'],
    ['name' => 'Icelandic Króna', 'symbol' => 'kr', 'code' => 'ISK'],
    ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'code' => 'NOK'],
    ['name' => 'Russian Ruble', 'symbol' => '₽', 'code' => 'RUB'],
    ['name' => 'Turkish Lira', 'symbol' => '₺', 'code' => 'TRY'],
    ['name' => 'Australian Dollar', 'symbol' => '$', 'code' => 'AUD'],
    ['name' => 'Brazilian Real', 'symbol' => 'R$', 'code' => 'BRL'],
    ['name' => 'Canadian Dollar', 'symbol' => '$', 'code' => 'CAD'],
    ['name' => 'Chinese Yuan', 'symbol' => '¥', 'code' => 'CNY'],
    ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'code' => 'HKD'],
    ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'code' => 'IDR'],
    ['name' => 'Israeli New Sheqel', 'symbol' => '₪', 'code' => 'ILS'],
    ['name' => 'Indian Rupee', 'symbol' => '₹', 'code' => 'INR'],
    ['name' => 'South Korean Won', 'symbol' => '₩', 'code' => 'KRW'],
    ['name' => 'Mexican Peso', 'symbol' => 'Mex$', 'code' => 'MXN'],
    ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'code' => 'MYR'],
    ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'code' => 'NZD'],
    ['name' => 'Philippine Peso', 'symbol' => '₱', 'code' => 'PHP'],
    ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'code' => 'SGD'],
    ['name' => 'Thai Baht', 'symbol' => '฿', 'code' => 'THB'],
    ['name' => 'South African Rand', 'symbol' => 'R', 'code' => 'ZAR'],
    ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'code' => 'UAH'],
    ['name' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'code' => 'TWD'],
];

$stmt = $pdo->prepare("INSERT INTO currencies (name, symbol, code, rate, user_id) 
                      VALUES (:name, :symbol, :code, :rate, :user_id)");
foreach ($currencies as $currency) {
    $stmt->bindValue(':name', $currency['name'], PDO::PARAM_STR);
    $stmt->bindValue(':symbol', $currency['symbol'], PDO::PARAM_STR);
    $stmt->bindValue(':code', $currency['code'], PDO::PARAM_STR);
    $stmt->bindValue(':rate', 1.0, PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $newUserId, PDO::PARAM_INT);
    $stmt->execute();
}

// Get actual Euro currency ID
$stmt = $pdo->prepare("SELECT id FROM currencies WHERE code = 'EUR' AND user_id = :user_id");
$stmt->bindValue(':user_id', $newUserId, PDO::PARAM_INT);
$stmt->execute();
$currency = $stmt->fetch(PDO::FETCH_ASSOC);
if ($currency) {
    $stmt = $pdo->prepare("UPDATE users SET main_currency = :main_currency WHERE id = :user_id");
    $stmt->bindValue(':main_currency', $currency['id'], PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $newUserId, PDO::PARAM_INT);
    $stmt->execute();
}

$userData['main_currency'] = $currency['id'];

// Insert settings
$stmt = $pdo->prepare("INSERT INTO settings (dark_theme, monthly_price, convert_currency, remove_background, color_theme, hide_disabled, user_id, disabled_to_bottom, show_original_price, mobile_nav) 
                      VALUES (2, 0, 0, 0, 'blue', 0, :user_id, 0, 0, 0)");
$stmt->bindValue(':user_id', $newUserId, PDO::PARAM_INT);
$stmt->execute();

// Log the user in
require_once('oidc_login.php');
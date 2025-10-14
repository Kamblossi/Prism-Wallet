<?php
echo "<h1>Environment Debug</h1>";
echo "<h2>getenv() results:</h2>";
echo "DB_HOST (getenv): " . (getenv('DB_HOST') ?: 'NOT SET') . "<br>";
echo "DB_PORT (getenv): " . (getenv('DB_PORT') ?: 'NOT SET') . "<br>";
echo "DB_NAME (getenv): " . (getenv('DB_NAME') ?: 'NOT SET') . "<br>";
echo "DB_USER (getenv): " . (getenv('DB_USER') ?: 'NOT SET') . "<br>";
echo "AUTH_PROVIDER (getenv): " . (getenv('AUTH_PROVIDER') ?: 'NOT SET') . "<br>";

echo "<h2>\$_ENV results:</h2>";
echo "DB_HOST (\$_ENV): " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "<br>";
echo "DB_PORT (\$_ENV): " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "<br>";
echo "DB_NAME (\$_ENV): " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "<br>";
echo "DB_USER (\$_ENV): " . ($_ENV['DB_USER'] ?? 'NOT SET') . "<br>";
echo "AUTH_PROVIDER (\$_ENV): " . ($_ENV['AUTH_PROVIDER'] ?? 'NOT SET') . "<br>";

echo "<h2>After loading .env:</h2>";
require_once __DIR__ . '/vendor/autoload.php';
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "Dotenv loaded successfully<br>";
    echo "DB_HOST after dotenv: " . ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'NOT SET') . "<br>";
    echo "DB_NAME after dotenv: " . ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'NOT SET') . "<br>";
    echo "AUTH_PROVIDER after dotenv: " . ($_ENV['AUTH_PROVIDER'] ?? getenv('AUTH_PROVIDER') ?? 'NOT SET') . "<br>";
} catch (Exception $e) {
    echo "Error loading dotenv: " . $e->getMessage() . "<br>";
}
?>

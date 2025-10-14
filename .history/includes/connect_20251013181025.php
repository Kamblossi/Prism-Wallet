<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Support both .env file and Docker environment variables
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '5432';
$dbname = $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'prism_wallet_prod';
$user = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'postgres';
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? '1234';

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection to the database failed: ' . $e->getMessage());
}

require_once __DIR__ . '/db_wrapper.php';

$db = new Database($pdo);

// Clerk Configuration
$clerk_publishable_key = $_ENV['CLERK_PUBLISHABLE_KEY'] ?? getenv('CLERK_PUBLISHABLE_KEY');
$clerk_secret_key = $_ENV['CLERK_SECRET_KEY'] ?? getenv('CLERK_SECRET_KEY');
$app_url = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? 'http://localhost:8081';

// Initialize Clerk Auth (simplified)
require_once __DIR__ . '/clerk_auth_simple.php';
$clerkAuth = new ClerkAuthSimple($clerk_secret_key, $clerk_publishable_key, $pdo, $app_url);

// Initialize Clerk Session
require_once __DIR__ . '/clerk_session.php';
$session = new ClerkSession($clerkAuth);

?>

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

// Debug: log the connection parameters
error_log("DB Connection attempt: host=$host, port=$port, dbname=$dbname, user=$user");

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection to the database failed: ' . $e->getMessage());
}

require_once __DIR__ . '/db_wrapper.php';

$db = new Database($pdo);

// Select authentication provider: 'clerk' (default) or 'local'
$auth_provider = $_ENV['AUTH_PROVIDER'] ?? getenv('AUTH_PROVIDER') ?? 'local';

if ($auth_provider === 'local') {
    // Local session-based auth
    require_once __DIR__ . '/local_session.php';
    $session = new LocalSession($pdo);
}

?>

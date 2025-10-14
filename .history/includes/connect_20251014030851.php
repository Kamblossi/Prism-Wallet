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

// Try to resolve hostname to IP for libpq compatibility
// Use getent hosts command which reads /etc/hosts
$cmd = "getent hosts $host 2>&1";
$output = shell_exec($cmd);
$hostaddr = null;

if ($output) {
    // Parse the output: "IP hostname"
    $parts = preg_split('/\s+/', trim($output));
    if (isset($parts[0]) && filter_var($parts[0], FILTER_VALIDATE_IP)) {
        $hostaddr = $parts[0];
        error_log("Resolved $host to $hostaddr using getent");
    }
}

// Fallback to PHP's gethostbyname if getent failed
if (!$hostaddr) {
    $hostaddr = @gethostbyname($host);
    if ($hostaddr && $hostaddr !== $host) {
        error_log("Resolved $host to $hostaddr using gethostbyname");
    } else {
        $hostaddr = null;
    }
}

if ($hostaddr) {
    // Successfully resolved to IP, use hostaddr parameter to bypass libpq DNS
    $dsn = "pgsql:hostaddr=$hostaddr;port=$port;dbname=$dbname";
} else {
    // Could not resolve, use host as-is 
    error_log("WARNING: Could not resolve $host to IP, using hostname directly");
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
}

try {
    error_log("About to create PDO connection with DSN: $dsn");
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("PDO connection successful!");
} catch (PDOException $e) {
    error_log("PDO connection failed: " . $e->getMessage());
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

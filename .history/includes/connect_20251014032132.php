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

// Debug: log the connection parameters with request URI to distinguish web vs cron
$request_uri = $_SERVER['REQUEST_URI'] ?? 'CLI/CRON';
error_log("DB Connection attempt [$request_uri]: host=$host, port=$port, dbname=$dbname, user=$user");

// Try to resolve hostname to IP for libpq compatibility
// Method 1: Read from pre-resolved IP file (set by startup.sh)
$hostaddr = null;
$ip_file = '/tmp/postgres_ip.txt';
if (file_exists($ip_file)) {
    $hostaddr = trim(file_get_contents($ip_file));
    if ($hostaddr && filter_var($hostaddr, FILTER_VALIDATE_IP)) {
        error_log("[$request_uri] Resolved $host to $hostaddr from $ip_file");
    } else {
        error_log("[$request_uri] IP file exists but contains invalid IP: $hostaddr");
        $hostaddr = null;
    }
} else {
    error_log("[$request_uri] IP file $ip_file does not exist");
}

// Method 2: Use getent hosts command which reads /etc/hosts
if (!$hostaddr) {
    $cmd = "getent hosts $host 2>&1";
    error_log("Executing: $cmd");
    $output = shell_exec($cmd);
    error_log("shell_exec output: " . var_export($output, true));
    
    if ($output) {
        // Parse the output: "IP hostname"
        $parts = preg_split('/\s+/', trim($output));
        error_log("Parsed parts: " . json_encode($parts));
        if (isset($parts[0]) && filter_var($parts[0], FILTER_VALIDATE_IP)) {
            $hostaddr = $parts[0];
            error_log("Resolved $host to $hostaddr using getent");
        }
    }
}

// Method 3: Fallback to PHP's gethostbyname
if (!$hostaddr) {
    error_log("getent failed, trying gethostbyname");
    $hostaddr = @gethostbyname($host);
    if ($hostaddr && $hostaddr !== $host) {
        error_log("Resolved $host to $hostaddr using gethostbyname");
    } else {
        error_log("gethostbyname also failed: " . var_export($hostaddr, true));
        $hostaddr = null;
    }
}

if ($hostaddr) {
    // Successfully resolved to IP, use hostaddr parameter to bypass libpq DNS
    error_log("[$request_uri] Using hostaddr=$hostaddr in DSN");
    $dsn = "pgsql:hostaddr=$hostaddr;port=$port;dbname=$dbname";
} else {
    // Could not resolve, use host as-is 
    error_log("[$request_uri] WARNING: Could not resolve $host to IP, using hostname directly");
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
}

try {
    error_log("[$request_uri] About to create PDO connection with DSN: $dsn");
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("[$request_uri] PDO connection successful!");
} catch (PDOException $e) {
    error_log("[$request_uri] PDO connection FAILED: " . $e->getMessage());
    die('Connection to the database failed [BUILD_v2025_10_14_03_12]: ' . $e->getMessage());
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

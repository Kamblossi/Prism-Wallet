<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env if present; in many PaaS envs variables are injected directly
try {
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
    }
} catch (Throwable $e) {
    // Non-fatal: continue with process envs
}

// Prefer a single DATABASE_URL when provided (e.g. postgres://user:pass@host:5432/db?sslmode=require)
$databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?? null;

// Support both .env file and Docker environment variables
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? null;
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? null;
$dbname = $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? null;
$user = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?? null;
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? null;

// SSL mode for managed Postgres (Supabase/Neon commonly require this)
$sslmode = $_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?? null;

// If DATABASE_URL exists, parse it to populate missing pieces
if ($databaseUrl) {
    try {
        $parts = parse_url($databaseUrl);
        if ($parts !== false) {
            $host = $host ?? ($parts['host'] ?? null);
            $port = $port ?? (isset($parts['port']) ? strval($parts['port']) : null);
            $user = $user ?? ($parts['user'] ?? null);
            $password = $password ?? ($parts['pass'] ?? null);
            if (!$dbname && isset($parts['path'])) {
                $dbname = ltrim($parts['path'], '/');
            }
            if (!$sslmode && isset($parts['query'])) {
                parse_str($parts['query'], $q);
                if (isset($q['sslmode'])) { $sslmode = $q['sslmode']; }
            }
        }
    } catch (Throwable $e) {
        // ignore and rely on individual env vars
    }
}

// Reasonable local defaults if still unset
$host = $host ?? 'localhost';
$port = $port ?? '5432';
$dbname = $dbname ?? 'prism_wallet_prod';
$user = $user ?? 'postgres';
$password = $password ?? '1234';

// If connecting to Supabase/Neon and sslmode not set, default to require
if (!$sslmode && (stripos($host, 'supabase.co') !== false || stripos($host, 'neon.tech') !== false)) {
    $sslmode = 'require';
}

// Debug: log the connection parameters with request URI to distinguish web vs cron
$request_uri = $_SERVER['REQUEST_URI'] ?? 'CLI/CRON';
error_log("DB Connection attempt [$request_uri]: host=$host, port=$port, dbname=$dbname, user=$user, sslmode=" . ($sslmode ?? 'none'));

// Try to resolve hostname to IP for libpq compatibility (useful on Docker networks)
// Skip for managed DBs and when DB_DISABLE_HOSTADDR=1
$hostaddr = null;
$disableHostaddr = (($_ENV['DB_DISABLE_HOSTADDR'] ?? getenv('DB_DISABLE_HOSTADDR') ?? '0') === '1');
$skipResolution = $disableHostaddr || stripos($host, 'supabase.co') !== false || stripos($host, 'neon.tech') !== false;
// Method 1: Read from pre-resolved IP file (set by startup.sh)
if (!$skipResolution) {
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
}

// Method 2: Use getent hosts command which reads /etc/hosts
if (!$hostaddr && !$skipResolution) {
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
if (!$hostaddr && !$skipResolution) {
    error_log("getent failed, trying gethostbyname");
    $hostaddr = @gethostbyname($host);
    if ($hostaddr && $hostaddr !== $host) {
        error_log("Resolved $host to $hostaddr using gethostbyname");
    } else {
        error_log("gethostbyname also failed: " . var_export($hostaddr, true));
        $hostaddr = null;
    }
}

// Append SSL fragment when applicable
$sslFragment = $sslmode ? ";sslmode=$sslmode" : '';

if ($hostaddr) {
    // Successfully resolved to IP, use hostaddr parameter to bypass libpq DNS
    error_log("[$request_uri] Using hostaddr=$hostaddr in DSN");
    $dsn = "pgsql:hostaddr=$hostaddr;port=$port;dbname=$dbname$sslFragment";
} else {
    // Could not resolve, use host as-is 
    error_log("[$request_uri] WARNING: Could not resolve $host to IP, using hostname directly");
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname$sslFragment";
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

// Ensure critical schema pieces exist (idempotent, Postgres-specific)
try {
    // Users table may be created by migrations, but some environments might miss newer columns.
    // Keep these ALTER TABLE calls lightweight and safe to run on every request.
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash TEXT");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified SMALLINT DEFAULT 0");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token TEXT");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expires_at TIMESTAMPTZ");

    // Minimal dependent tables for first-run paths used by registration
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            dark_theme SMALLINT DEFAULT 2,
            color_theme TEXT,
            monthly_price BOOLEAN DEFAULT TRUE,
            convert_currency BOOLEAN DEFAULT TRUE,
            remove_background BOOLEAN DEFAULT FALSE,
            hide_disabled BOOLEAN DEFAULT FALSE,
            disabled_to_bottom BOOLEAN DEFAULT FALSE,
            show_original_price BOOLEAN DEFAULT TRUE,
            mobile_nav BOOLEAN DEFAULT FALSE,
            show_subscription_progress BOOLEAN DEFAULT FALSE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS currencies (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            symbol TEXT NOT NULL,
            code TEXT NOT NULL,
            rate NUMERIC(18,8) NOT NULL DEFAULT 1
        )
    ");

    // Admin table stores SMTP/URL settings used for verification emails
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin (
            id BIGSERIAL PRIMARY KEY,
            login_disabled BOOLEAN DEFAULT FALSE,
            update_notification BOOLEAN DEFAULT FALSE,
            latest_version TEXT
        )
    ");
    // Add optional columns if missing
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_address TEXT"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_port INTEGER"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_username TEXT"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_password TEXT"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS from_email TEXT"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS encryption TEXT"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS server_url TEXT"); } catch (Throwable $e) {}

    // Ensure cycles table exists and is seeded with defaults
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cycles (
            id SMALLINT PRIMARY KEY,
            name TEXT NOT NULL UNIQUE
        )
    ");
    try {
        $cnt = (int)$pdo->query('SELECT COUNT(*) FROM cycles')->fetchColumn();
        if ($cnt === 0) {
            $stmt = $pdo->prepare('INSERT INTO cycles (id, name) VALUES (1, :d), (2, :w), (3, :m), (4, :y)');
            $stmt->execute([':d' => 'Daily', ':w' => 'Weekly', ':m' => 'Monthly', ':y' => 'Yearly']);
        }
    } catch (Throwable $e) {
        error_log('[schema_guard] cycles seed failed: ' . $e->getMessage());
    }

    // Ensure historical totals table exists (used by dashboard graphs)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS total_yearly_cost (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            date DATE NOT NULL,
            cost NUMERIC(14,2) NOT NULL DEFAULT 0,
            currency BIGINT REFERENCES currencies(id) ON DELETE SET NULL
        )
    ");
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_total_yearly_cost_user_date ON total_yearly_cost (user_id, date)"); } catch (Throwable $e) { }
} catch (Throwable $e) {
    // Non-fatal: log and continue; explicit migrations endpoint remains available for full setup
    error_log('[schema_guard] Warning while ensuring schema: ' . $e->getMessage());
}

require_once __DIR__ . '/db_wrapper.php';

$db = new Database($pdo);

// Use local session-based auth exclusively
require_once __DIR__ . '/local_session.php';
$session = new LocalSession($pdo);

?>

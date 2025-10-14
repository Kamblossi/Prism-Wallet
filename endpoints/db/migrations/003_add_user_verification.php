<?php
// Adds user verification columns to users table (idempotent)
// Uses $pdo from migrate.php when included.

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified SMALLINT DEFAULT 0");
} catch (Throwable $e) { /* ignore */ }
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token TEXT");
} catch (Throwable $e) { /* ignore */ }
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expires_at TIMESTAMPTZ");
} catch (Throwable $e) { /* ignore */ }

echo "Applied 003_add_user_verification migration\n";

?>


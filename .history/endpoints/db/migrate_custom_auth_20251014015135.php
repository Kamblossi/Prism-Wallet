<?php
/**
 * Migration: Custom Authentication System
 * - Makes clerk_id nullable
 * - Adds unique constraint to email
 * - Ensures password_hash column exists
 */

require_once __DIR__ . '/../../includes/connect.php';

header('Content-Type: text/plain');
echo "=== Custom Authentication Migration ===\n\n";

try {
    // 1. Make clerk_id nullable (for backwards compatibility during transition)
    echo "Making clerk_id nullable...\n";
    $pdo->exec("ALTER TABLE users ALTER COLUMN clerk_id DROP NOT NULL");
    echo "✓ clerk_id is now nullable\n\n";
    
    // 2. Add unique constraint to email if it doesn't exist
    echo "Adding unique constraint to email...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_email_key UNIQUE (email)");
        echo "✓ Email unique constraint added\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "✓ Email unique constraint already exists\n\n";
        } else {
            throw $e;
        }
    }
    
    // 3. Ensure password_hash column exists
    echo "Ensuring password_hash column exists...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash TEXT");
        echo "✓ password_hash column ensured\n\n";
    } catch (PDOException $e) {
        echo "✓ password_hash column already exists\n\n";
    }
    
    // 4. Add email column if it doesn't exist
    echo "Ensuring email column is NOT NULL...\n";
    try {
        // First check if there are any NULL emails
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE email IS NULL");
        $nullCount = $stmt->fetchColumn();
        
        if ($nullCount > 0) {
            echo "⚠ Warning: Found $nullCount users with NULL emails. Skipping NOT NULL constraint.\n\n";
        } else {
            // Safe to add NOT NULL constraint
            try {
                $pdo->exec("ALTER TABLE users ALTER COLUMN email SET NOT NULL");
                echo "✓ Email column is now NOT NULL\n\n";
            } catch (PDOException $e) {
                echo "✓ Email column already NOT NULL\n\n";
            }
        }
    } catch (PDOException $e) {
        echo "Note: " . $e->getMessage() . "\n\n";
    }
    
    echo "=== Migration completed successfully! ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    http_response_code(500);
}

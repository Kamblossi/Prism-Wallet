<?php
// One-off data migration helper: migrate rows from legacy `user` table into `users`.
// - Inserts rows that do not already exist in `users` (by lower(email))
// - Optionally updates empty/missing usernames in users from legacy
// - Writes a summary to stdout; on conflicts, records an audit entry

require_once __DIR__ . '/../includes/connect.php';

header_remove();
ini_set('display_errors', '1');

function say($m){ echo $m, PHP_EOL; }

try {
    // Check if legacy table exists
    $tbl = $pdo->prepare("SELECT to_regclass('public.user')");
    $tbl->execute();
    $exists = $tbl->fetchColumn();
    if (!$exists) {
        say('No legacy table `user` found. Nothing to migrate.');
        exit(0);
    }

    // Fetch legacy rows
    $legacy = $pdo->query('SELECT id, username, email FROM "user" ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if (!$legacy) { say('Legacy table has 0 rows.'); exit(0); }

    $inserted = 0; $skipped = 0; $updated = 0; $conflicts = 0;
    $pdo->beginTransaction();
    try {
        foreach ($legacy as $row) {
            $email = trim($row['email'] ?? '');
            $username = trim($row['username'] ?? '');
            if ($email === '') { $skipped++; continue; }

            // Does users already contain this email (case-insensitive, active or deleted)?
            $q = $pdo->prepare('SELECT id, username FROM users WHERE lower(email) = lower(:e) ORDER BY id ASC LIMIT 1');
            $q->execute([':e'=>$email]);
            $u = $q->fetch(PDO::FETCH_ASSOC);

            if ($u) {
                // If username missing on users, fill from legacy
                if ((!isset($u['username']) || $u['username'] === null || $u['username'] === '') && $username !== '') {
                    $pdo->prepare('UPDATE users SET username = :u WHERE id = :id')->execute([':u'=>$username, ':id'=>$u['id']]);
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            // Insert a minimal user; other fields will be defaults
            $ins = $pdo->prepare('INSERT INTO users (username, email, is_active, is_verified, created_at, updated_at) VALUES (:u, :e, TRUE, 0, NOW(), NOW())');
            try {
                $ins->execute([':u'=>$username !== '' ? $username : null, ':e'=>$email]);
                $inserted++;
            } catch (Throwable $e) {
                $conflicts++;
                // Record in admin_audit if table exists
                try {
                    $pdo->prepare('INSERT INTO admin_audit (actor_user_id, action, details) VALUES (NULL, :a, :d)')
                        ->execute([':a'=>'migration.user_legacy_conflict', ':d'=>json_encode(['email'=>$email,'error'=>$e->getMessage()])]);
                } catch (Throwable $e2) { /* ignore */ }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    say("Migrated from legacy 'user': inserted=$inserted, updated=$updated, skipped=$skipped, conflicts=$conflicts");

    // Create compatibility view if legacy table does not exist anymore
    // (We do NOT drop the legacy table here; this tool is non-destructive.)
    say('Note: Compatibility view not created because legacy table still exists. Drop it later to enable a view.');
    exit(0);
} catch (Throwable $e) {
    http_response_code(500);
    say('Migration failed: ' . $e->getMessage());
    exit(1);
}


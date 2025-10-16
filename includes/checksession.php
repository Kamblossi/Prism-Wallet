<?php
// Authentication middleware for Local provider only
// Local provider uses PHP sessions

// Development override: allow bypassing auth when DISABLE_AUTH=1
$disableAuth = getenv('DISABLE_AUTH');
if ($disableAuth && (int)$disableAuth === 1) {
    // Ensure at least one user exists
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $pdo->beginTransaction();
        try {
            $pdo->exec("INSERT INTO users (username, email, is_admin, avatar, language, budget, is_verified) VALUES ('dev', 'dev@example.com', TRUE, 'images/avatars/0.svg', 'en', 0, 1)");
            $userIdNew = (int)$pdo->lastInsertId();

            // Create settings row with safe defaults
            $stmt = $pdo->prepare("INSERT INTO settings (user_id, dark_theme, color_theme, monthly_price, convert_currency, remove_background, hide_disabled, disabled_to_bottom, show_original_price, mobile_nav, show_subscription_progress) VALUES (:uid, 2, 'blue', TRUE, TRUE, FALSE, FALSE, FALSE, TRUE, FALSE, FALSE)");
            $stmt->execute(['uid' => $userIdNew]);

            // Ensure a USD currency exists and set as main_currency
            $stmt = $pdo->prepare("INSERT INTO currencies (user_id, name, symbol, code, rate) VALUES (:uid, 'US Dollar', '$', 'USD', 1) RETURNING id");
            $stmt->execute(['uid' => $userIdNew]);
            $usdId = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare('UPDATE users SET main_currency = :cur WHERE id = :uid');
            $stmt->execute(['cur' => $usdId, 'uid' => $userIdNew]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // Best-effort: continue; downstream may still work without defaults
        }
    }
    $userData = $pdo->query('SELECT * FROM users ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $userId = (int)$userData['id'];
    $username = $userData['email'];
    $main_currency = $userData['main_currency'] ?? 'USD';
} else {
    // Local provider
        if (!$session->isLoggedIn()) {
            header('Location: /login.php');
            exit();
        }
        $userData = $session->getUser();
        if (!$userData) {
            header('Location: /login.php');
            exit();
        }
        $userId = (int)$userData['id'];
        // Prefer username; default to firstname or email
        if (empty($userData['username']) && !empty($userData['firstname'])) {
            try {
                $stmt = $pdo->prepare('UPDATE users SET username = :u WHERE id = :id');
                $stmt->execute([':u' => $userData['firstname'], ':id' => $userId]);
                $userData['username'] = $userData['firstname'];
            } catch (Throwable $e) { /* continue */ }
        }
        $username = $userData['username'] ?: $userData['email'];
        $main_currency = $userData['main_currency'] ?? 'USD';

        // Enforce session_version for force-logout
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $sessVer = isset($_SESSION['session_version']) ? (int)$_SESSION['session_version'] : 0;
        try {
            $stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = :id');
            $stmt->execute([':id'=>$userId]);
            $dbVer = (int)$stmt->fetchColumn();
            if ($sessVer !== $dbVer) {
                session_unset();
                session_destroy();
                header('Location: /login.php');
                exit();
            }
        } catch (Throwable $e) { /* ignore */ }

        // Ensure at least one default household entry exists
        try {
            $stmtTmp = $pdo->prepare('SELECT COUNT(*) FROM household WHERE user_id = :id');
            $stmtTmp->execute([':id' => $userId]);
            $cnt = (int)$stmtTmp->fetchColumn();
        } catch (Throwable $e2) { $cnt = 0; }
        if ($cnt === 0) {
            $display = trim($username) ? $username : 'My';
            $name = $display . "'s Household";
            try {
                $stmt = $pdo->prepare('INSERT INTO household (user_id, name, email) VALUES (:uid, :name, :email)');
                $stmt->execute([':uid' => $userId, ':name' => $name, ':email' => $userData['email'] ?? null]);
            } catch (Throwable $e) { /* ignore */ }
        } elseif ($cnt === 1) {
            // Provide one extra editable slot for convenience on first run
            try {
                $stmt = $pdo->prepare('INSERT INTO household (user_id, name, email) VALUES (:uid, :name, :email)');
                $stmt->execute([':uid' => $userId, ':name' => 'Member', ':email' => null]);
            } catch (Throwable $e) { /* ignore */ }
        }

        // Seed default categories if none exist
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE user_id = :id');
            $stmt->execute([':id' => $userId]);
            $countCats = (int)$stmt->fetchColumn();
            if ($countCats === 0) {
                $defaults = [
                    'No category', 'Entertainment', 'Music', 'Utilities', 'Food & Beverages',
                    'Health & Wellbeing', 'Productivity', 'Banking', 'Transport', 'Education',
                    'Insurance', 'Gaming', 'News & Magazines', 'Software', 'Technology',
                    'Cloud Services', 'Charity & Donations'
                ];
                $ins = $pdo->prepare('INSERT INTO categories (name, "order", user_id) VALUES (:name, :ord, :uid)');
                $ord = 1;
                foreach ($defaults as $name) {
                    $ins->execute([':name' => $name, ':ord' => $ord++, ':uid' => $userId]);
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // Seed at least one currency if missing for the user
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM currencies WHERE user_id = :id');
            $stmt->execute([':id' => $userId]);
            $countCur = (int)$stmt->fetchColumn();
            if ($countCur === 0) {
                $ins = $pdo->prepare("INSERT INTO currencies (user_id, name, symbol, code, rate) VALUES (:uid, 'US Dollar', '$', 'USD', 1) RETURNING id");
                $ins->execute([':uid' => $userId]);
                $usdId = (int)$ins->fetchColumn();
                $pdo->prepare('UPDATE users SET main_currency = :cid WHERE id = :uid')->execute([':cid' => $usdId, ':uid' => $userId]);
            }
        } catch (Throwable $e) { /* ignore */ }

        // Seed default payment methods if none exist
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM payment_methods WHERE user_id = :id');
            $stmt->execute([':id' => $userId]);
            $countPm = (int)$stmt->fetchColumn();
            if ($countPm === 0) {
                $defaults = [
                    ['PayPal', 'images/uploads/icons/paypal.png'],
                    ['Credit Card', 'images/uploads/icons/creditcard.png'],
                    ['Bank Transfer', 'images/uploads/icons/banktransfer.png'],
                    ['Money', 'images/uploads/icons/money.png'],
                    ['Google Pay', 'images/uploads/icons/googlepay.png']
                ];
                $ins = $pdo->prepare('INSERT INTO payment_methods (name, icon, "order", user_id, enabled) VALUES (:name, :icon, :ord, :uid, TRUE)');
                $ord = 1;
                foreach ($defaults as $pm) {
                    $ins->execute([':name' => $pm[0], ':icon' => $pm[1], ':ord' => $ord++, ':uid' => $userId]);
                }
            }
        } catch (Throwable $e) { /* ignore */ }
}

// Set safe defaults for optional fields
if (empty($userData['avatar'])) {
    // Use a valid bundled avatar path (index 0)
    $userData['avatar'] = 'images/avatars/0.svg';
}
// Normalize legacy avatar values (e.g., 'user.svg' -> images/avatars/0.svg)
if (!empty($userData['avatar']) && strpos($userData['avatar'], '/') === false) {
    $candidate = 'images/avatars/' . $userData['avatar'];
    // If legacy 'user.svg' was stored, map to 0.svg (bundled)
    if (basename($candidate) === 'user.svg' && !file_exists(__DIR__ . '/../' . $candidate)) {
        $userData['avatar'] = 'images/avatars/0.svg';
    } else {
        $userData['avatar'] = $candidate;
    }
}
if (!isset($userData['totp_enabled'])) {
    $userData['totp_enabled'] = false;
}

// No more cookie or session handling beyond local auth
?>

<?php
// Helpers for endpoint responses and admin authorization

if (!function_exists('json_success')) {
    function json_success($message = 'ok', $data = [], $status = 200) {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
        exit;
    }
}

if (!function_exists('json_error')) {
    function json_error($message = 'error', $status = 400, $code = null, $data = []) {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        $payload = ['success' => false, 'message' => $message];
        if ($code !== null) { $payload['code'] = $code; }
        if (!empty($data)) { $payload['data'] = $data; }
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(PDO $pdo = null) {
        // Prefer session abstraction if available
        global $session;
        if (isset($session) && $session && method_exists($session, 'getUserId')) {
            $uid = $session->getUserId();
            if ($uid) { return (int)$uid; }
        }
        // Fallback to PHP session
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (!empty($_SESSION['user_id'])) { return (int)$_SESSION['user_id']; }
        // As a last resort, if middleware populated $userData
        if (isset($GLOBALS['userData']['id'])) { return (int)$GLOBALS['userData']['id']; }
        return null;
    }
}

if (!function_exists('current_user_is_admin')) {
    function current_user_is_admin(PDO $pdo) {
        global $session;
        // If session abstraction exposes isAdmin, use it
        if (isset($session) && $session && method_exists($session, 'isAdmin')) {
            try { return (bool)$session->isAdmin(); } catch (Throwable $e) { /* continue */ }
        }
        $uid = get_current_user_id($pdo);
        if (!$uid) { return false; }
        try {
            $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id');
            $stmt->execute([':id' => $uid]);
            $flag = $stmt->fetchColumn();
            return (bool)$flag;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(PDO $pdo) {
        if (!current_user_is_admin($pdo)) {
            json_error('Forbidden', 403, 'forbidden');
        }
    }
}

if (!function_exists('audit_admin_action')) {
    function audit_admin_action(PDO $pdo, string $action, ?int $target_user_id = null, array $details = []) {
        try {
            $actor = get_current_user_id($pdo);
            $stmt = $pdo->prepare('INSERT INTO admin_audit (actor_user_id, action, target_user_id, details, ip_address, user_agent) VALUES (:actor, :action, :target, :details, :ip, :ua)');
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $stmt->execute([
                ':actor' => $actor,
                ':action' => $action,
                ':target' => $target_user_id,
                ':details' => !empty($details) ? json_encode($details) : null,
                ':ip' => $ip,
                ':ua' => $ua,
            ]);
        } catch (Throwable $e) {
            // Non-fatal
        }
    }
}

// Very small CSRF helper: expects token in session and header X-CSRF-Token
if (!function_exists('require_csrf')) {
    function require_csrf() {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $expected = $_SESSION['csrf_token'] ?? null;
        $got = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($expected && $got && hash_equals($expected, $got)) { return; }
        // Allow GET/HEAD without CSRF; enforce for state-changing methods
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array(strtoupper($method), ['POST','PUT','PATCH','DELETE'])) {
            json_error('Invalid CSRF token', 403, 'csrf');
        }
    }
}

// Simple DB-backed rate limit: limit requests per key per minute
if (!function_exists('rate_limit')) {
    function rate_limit(PDO $pdo, string $key, int $limitPerMinute = 120) {
        $windowStart = (new DateTimeImmutable('now'))->setTime((int)date('H'), (int)date('i'))->format('Y-m-d H:i:00P');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO admin_rate_limits (rate_key, window_start, count) VALUES (:k, :w, 1)
                                    ON CONFLICT (rate_key, window_start) DO UPDATE SET count = admin_rate_limits.count + 1 RETURNING count');
            $stmt->execute([':k'=>$key, ':w'=>$windowStart]);
            $count = (int)$stmt->fetchColumn();
            $pdo->commit();
            if ($count > $limitPerMinute) {
                json_error('Rate limit exceeded', 429, 'rate_limited');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Fail-open to avoid hard outages
        }
    }
}

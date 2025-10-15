<?php
// Postgres-first migration runner for Prism Wallet
// Creates required tables/columns if missing using PDO (pgsql)

require_once __DIR__ . '/../../includes/connect.php';

header('Content-Type: text/plain');

function execSql(PDO $pdo, string $sql)
{
    $pdo->exec($sql);
}

try {
    // Enable standard public schema
    execSql($pdo, "SET search_path TO public");

    // users (no Clerk dependency)
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS users (
            id BIGSERIAL PRIMARY KEY,
            username TEXT,
            email TEXT,
            firstname TEXT,
            lastname TEXT,
            main_currency BIGINT,
            avatar TEXT DEFAULT 'images/avatars/0.svg',
            is_admin BOOLEAN DEFAULT FALSE,
            language TEXT DEFAULT 'en',
            budget NUMERIC(12,2) DEFAULT 0,
            password_hash TEXT,
            is_verified SMALLINT DEFAULT 0,
            verification_token TEXT,
            token_expires_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");

    // Ensure clerk_id removed/nullable and password_hash exists (idempotent)
    try { $pdo->exec("ALTER TABLE users ALTER COLUMN clerk_id DROP NOT NULL"); } catch (Throwable $e) { }
    try {
        $idx = $pdo->query("SELECT indexname FROM pg_indexes WHERE tablename='users' AND indexdef ILIKE '%(clerk_id%' LIMIT 1")->fetchColumn();
        if ($idx) { $pdo->exec("DROP INDEX IF EXISTS \"$idx\""); }
    } catch (Throwable $e) { }
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash TEXT");
    } catch (Throwable $e) { /* ignore */ }

    // Add lifecycle columns to users (idempotent)
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMPTZ"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INTEGER DEFAULT 0"); } catch (Throwable $e) { }

    // Ensure case-insensitive unique email for non-deleted rows
    try {
        $exists = $pdo->query("SELECT 1 FROM pg_indexes WHERE schemaname='public' AND indexname='users_email_uniq'")->fetchColumn();
        if (!$exists) {
            $pdo->exec("CREATE UNIQUE INDEX users_email_uniq ON users (lower(email)) WHERE deleted_at IS NULL");
        }
    } catch (Throwable $e) { /* ignore */ }

    // updated_at trigger
    try {
        $pdo->exec("CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$ BEGIN NEW.updated_at = NOW(); RETURN NEW; END; $$ LANGUAGE plpgsql;");
        $hasTrig = $pdo->query("SELECT 1 FROM pg_trigger WHERE tgname = 'trg_users_set_updated_at'")->fetchColumn();
        if (!$hasTrig) {
            $pdo->exec("CREATE TRIGGER trg_users_set_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
        }
    } catch (Throwable $e) { /* ignore */ }

    // settings
    execSql($pdo, "
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

    // custom colors/css
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS custom_colors (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            main_color TEXT,
            accent_color TEXT,
            hover_color TEXT
        )
    ");
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS custom_css_style (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            css TEXT
        )
    ");

    // admin settings (single-row table, but no strict enforcement here)
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS admin (
            id BIGSERIAL PRIMARY KEY,
            login_disabled BOOLEAN DEFAULT FALSE,
            update_notification BOOLEAN DEFAULT FALSE,
            latest_version TEXT
        )
    ");

    // Ensure email/SMTP fields exist for cron jobs that read from admin
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_address TEXT"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_port INTEGER"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_username TEXT"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS smtp_password TEXT"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS from_email TEXT"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS encryption TEXT"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS server_url TEXT"); } catch (Throwable $e) { /* ignore */ }
    // Global Fixer & AI settings managed by admin
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS fixer_api_key TEXT"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS fixer_provider SMALLINT DEFAULT 0"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS ai_enabled SMALLINT DEFAULT 0"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS ai_type TEXT"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS ai_api_key TEXT"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS ai_model TEXT"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS ai_url TEXT"); } catch (Throwable $e) { }
    // Ensure registration controls exist (used by admin.php and endpoints)
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS registrations_open BOOLEAN DEFAULT FALSE"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS max_users INTEGER DEFAULT 0"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE admin ADD COLUMN IF NOT EXISTS require_email_verification SMALLINT DEFAULT 0"); } catch (Throwable $e) { /* ignore */ }

    // Seed a default admin row if table is empty so admin.php has values
    try {
        $cntAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn();
        if ($cntAdmin === 0) {
            $pdo->exec("INSERT INTO admin (login_disabled, update_notification, latest_version, registrations_open, max_users, require_email_verification, server_url) VALUES (FALSE, FALSE, NULL, FALSE, 0, 0, '')");
        }
    } catch (Throwable $e) { /* ignore */ }

    // Admin audit table for tracking privileged actions
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS admin_audit (
            id BIGSERIAL PRIMARY KEY,
            actor_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
            action TEXT NOT NULL,
            target_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
            target_table TEXT,
            target_id BIGINT,
            details JSONB,
            ip_address INET,
            user_agent TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )
    ");
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_audit_created_at ON admin_audit (created_at)"); } catch (Throwable $e) { }
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_audit_actor ON admin_audit (actor_user_id)"); } catch (Throwable $e) { }
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_audit_target_user ON admin_audit (target_user_id)"); } catch (Throwable $e) { }

    // Simple rate limits table (per key per minute window)
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS admin_rate_limits (
            id BIGSERIAL PRIMARY KEY,
            rate_key TEXT NOT NULL,
            window_start TIMESTAMPTZ NOT NULL,
            count INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT admin_rate_limits_key UNIQUE (rate_key, window_start)
        )
    ");

    // currencies
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS currencies (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            symbol TEXT NOT NULL,
            code TEXT NOT NULL,
            rate NUMERIC(18,8) NOT NULL DEFAULT 1
        )
    ");

    // payment methods
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS payment_methods (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            icon TEXT,
            enabled BOOLEAN DEFAULT TRUE,
            \"order\" INTEGER DEFAULT 0
        )
    ");

    // fixer settings (per user)
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS fixer (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            api_key TEXT,
            provider SMALLINT DEFAULT 0
        )
    ");

    // categories
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS categories (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            \"order\" INTEGER DEFAULT 0
        )
    ");

    // household
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS household (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            email TEXT
        )
    ");

    // cycles (payment intervals like Daily/Weekly/Monthly/Yearly)
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS cycles (
            id SMALLINT PRIMARY KEY,
            name TEXT NOT NULL UNIQUE
        )
    ");
    // Seed defaults if table is empty (idempotent)
    $count = (int)$pdo->query('SELECT COUNT(*) FROM cycles')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO cycles (id, name) VALUES (1, :d), (2, :w), (3, :m), (4, :y)');
        $stmt->execute([':d' => 'Daily', ':w' => 'Weekly', ':m' => 'Monthly', ':y' => 'Yearly']);
    }

    // subscriptions
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS subscriptions (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            logo TEXT,
            price NUMERIC(12,2) NOT NULL,
            currency_id BIGINT REFERENCES currencies(id) ON DELETE SET NULL,
            next_payment DATE,
            cycle INTEGER,
            frequency INTEGER,
            notes TEXT,
            payment_method_id BIGINT REFERENCES payment_methods(id) ON DELETE SET NULL,
            payer_user_id BIGINT REFERENCES household(id) ON DELETE SET NULL,
            category_id BIGINT REFERENCES categories(id) ON DELETE SET NULL,
            inactive BOOLEAN DEFAULT FALSE,
            auto_renew BOOLEAN DEFAULT TRUE,
            replacement_subscription_id BIGINT,
            notify BOOLEAN DEFAULT FALSE
        )
    ");

    // last_exchange_update captures last exchange-rate update per user
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS last_exchange_update (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            date DATE
        )
    ");

    // last_update_next_payment_date used by cron to record last run
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS last_update_next_payment_date (
            id BIGSERIAL PRIMARY KEY,
            date DATE
        )
    ");

    // notification settings
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS notification_settings (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            days INTEGER DEFAULT 1
        )
    ");

    // email/discord/pushover/telegram/pushplus/ntfy
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS email_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            smtp_address TEXT,
            smtp_port INTEGER,
            encryption TEXT,
            smtp_username TEXT,
            smtp_password TEXT,
            from_email TEXT,
            other_emails TEXT
        )
    ");
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS discord_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            webhook_url TEXT,
            bot_username TEXT,
            bot_avatar_url TEXT
        )
    ");
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS pushover_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            token TEXT,
            user_key TEXT
        )
    ");
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS telegram_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            bot_token TEXT,
            chat_id TEXT
        )
    ");
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS pushplus_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            token TEXT
        )
    ");
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS ntfy_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            host TEXT,
            topic TEXT
        )
    ");
    // Webhook notifications
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS webhook_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            url TEXT,
            request_method TEXT DEFAULT 'POST',
            headers TEXT,
            payload TEXT,
            cancelation_payload TEXT,
            ignore_ssl BOOLEAN DEFAULT FALSE
        )
    ");
    // Gotify notifications
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS gotify_notifications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            enabled BOOLEAN DEFAULT FALSE,
            url TEXT,
            token TEXT,
            ignore_ssl BOOLEAN DEFAULT FALSE
        )
    ");
    // Ensure optional columns exist for ntfy (used by settings page)
    try { $pdo->exec("ALTER TABLE ntfy_notifications ADD COLUMN IF NOT EXISTS headers TEXT"); } catch (Throwable $e) { /* ignore */ }
    try { $pdo->exec("ALTER TABLE ntfy_notifications ADD COLUMN IF NOT EXISTS ignore_ssl BOOLEAN DEFAULT FALSE"); } catch (Throwable $e) { /* ignore */ }

    // OAuth/OIDC settings (global or per-user). Ensure full column set used by admin UI
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS oauth_settings (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            oidc_oauth_enabled BOOLEAN DEFAULT FALSE,
            name TEXT,
            client_id TEXT,
            client_secret TEXT,
            authorization_url TEXT,
            token_url TEXT,
            user_info_url TEXT,
            redirect_url TEXT,
            logout_url TEXT,
            user_identifier_field TEXT,
            scopes TEXT,
            auth_style TEXT,
            auto_create_user SMALLINT DEFAULT 0,
            password_login_disabled BOOLEAN DEFAULT FALSE
        )
    ");
    // Add missing columns if upgrading from older minimal schema
    foreach ([
        'name TEXT', 'client_id TEXT', 'client_secret TEXT', 'authorization_url TEXT', 'token_url TEXT',
        'user_info_url TEXT', 'redirect_url TEXT', 'logout_url TEXT', 'user_identifier_field TEXT', 'scopes TEXT',
        'auth_style TEXT', 'auto_create_user SMALLINT'
    ] as $col) {
        try { $pdo->exec("ALTER TABLE oauth_settings ADD COLUMN IF NOT EXISTS $col"); } catch (Throwable $e) { }
    }
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS ai_settings (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            type TEXT NOT NULL,
            enabled BOOLEAN NOT NULL DEFAULT FALSE,
            api_key TEXT,
            model TEXT NOT NULL,
            url TEXT,
            run_schedule TEXT NOT NULL DEFAULT 'manual',
            last_successful_run TIMESTAMPTZ,
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS ai_recommendations (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            savings TEXT NOT NULL DEFAULT '',
            created_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");

    // password reset requests used by cron
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS password_resets (
            id BIGSERIAL PRIMARY KEY,
            email TEXT NOT NULL,
            token TEXT NOT NULL,
            email_sent SMALLINT NOT NULL DEFAULT 0,
            requested_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");

    echo "OK: migrations applied for Postgres.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Migration error: ' . $e->getMessage() . "\n";
    exit(1);
}

// Include and run additional migrations from endpoints/db/migrations (if any)
try {
    $migrationsDir = __DIR__ . '/migrations';
    if (is_dir($migrationsDir)) {
        $files = glob($migrationsDir . '/*.php');
        sort($files, SORT_NATURAL);
        foreach ($files as $file) {
            require $file; // Each migration can use $pdo and should be idempotent
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Migration runner error: ' . $e->getMessage() . "\n";
    exit(1);
}

?>

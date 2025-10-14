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

    // users
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS users (
            id BIGSERIAL PRIMARY KEY,
            clerk_id TEXT UNIQUE NOT NULL,
            username TEXT,
            email TEXT,
            firstname TEXT,
            lastname TEXT,
            main_currency BIGINT,
            avatar TEXT DEFAULT 'user.svg',
            is_admin BOOLEAN DEFAULT FALSE,
            language TEXT DEFAULT 'en',
            budget NUMERIC(12,2) DEFAULT 0,
            password_hash TEXT,
            created_at TIMESTAMPTZ DEFAULT NOW(),
            updated_at TIMESTAMPTZ DEFAULT NOW()
        )
    ");

    // Ensure password_hash column exists for local auth (idempotent)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash TEXT");
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

    // oauth/ai minimal tables to avoid references breaking
    execSql($pdo, "
        CREATE TABLE IF NOT EXISTS oauth_settings (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            oidc_oauth_enabled BOOLEAN DEFAULT FALSE,
            password_login_disabled BOOLEAN DEFAULT FALSE
        )
    ");
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

    echo "OK: migrations applied for Postgres.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Migration error: ' . $e->getMessage() . "\n";
    exit(1);
}

?>


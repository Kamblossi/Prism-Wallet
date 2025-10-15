Admin Users API

Auth & headers
- All endpoints require an authenticated admin session.
- Send CSRF token via header: X-CSRF-Token: <value from window.csrfToken>
- JSON responses: { success: boolean, message: string, data: any }

Endpoints
- POST endpoints/admin/users/list.php
  - Body: { page?: number, per_page?: number, q?: string, filter?: { is_admin?: boolean, is_verified?: number, is_active?: boolean, include_deleted?: boolean } }
  - Returns: { page, per_page, total, items: [{ id, username, email, is_admin, is_verified, is_active, created_at, last_login, subscription_count }] }

- GET or POST endpoints/admin/users/read.php?id=ID
  - Returns: { user: { fields... }, stats: { subscription_count, payment_methods_count, categories_count, currencies_count } }

- POST endpoints/admin/users/create.php
  - Body: { username: string, email: string, password?: string }
  - On local auth, generates a temporary password if missing or too short.
  - Returns: { id, email, username, temporary_password? }

- POST endpoints/admin/users/update.php
  - Body: { id: number, username?, email?, language?, avatar?, budget?, is_verified?, is_active? }

- POST endpoints/admin/users/toggle_admin.php
  - Body: { user_id: number, make_admin: boolean }

- POST endpoints/admin/users/toggle_active.php
  - Body: { user_id: number, is_active: boolean }

- POST endpoints/admin/users/verify.php
  - Body: { id?: number, email?: string }

- POST endpoints/admin/users/resend_verification.php
  - Body: { email: string }

- POST endpoints/admin/users/reset_password.php
  - Body: { user_id: number, new_password?: string }
  - Only supported on local auth; if new_password missing/short, a temporary password is generated.

- POST endpoints/admin/users/force_logout.php
  - Body: { user_id: number }
  - Increases users.session_version; active sessions with older version are forced to re-login.

- POST endpoints/admin/users/delete.php
  - Body: { user_id: number, transfer_to?: number }
  - Transfers related data to transfer_to before deleting or deletes with the user when omitted.

- POST endpoints/admin/users/export.php
  - Body: { user_id: number }
  - Returns JSON bundle for user-related tables.

Rate limiting
- Per-minute counters per action and actor; exceeding limits returns HTTP 429.

Audit logging
- All mutations add rows to admin_audit with actor_user_id, action, target_user_id and details.


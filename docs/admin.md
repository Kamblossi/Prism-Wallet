Admin Panel Usage

Overview
- Access the Admin panel from the user dropdown (Admin) when logged in as an admin.
- The panel includes: Users, Registration/SMTP, Maintenance, Backup/Restore, Health and Logs.

Users
- Search/filter using the toolbar (Admin only, Active only, Verified only) and paginate.
- View opens a drawer with details and actions:
  - Promote/Demote admin
  - Activate/Deactivate
  - Verify/Resend verification email
  - Reset password (local auth). If left blank, a temporary password is generated.
  - Force logout (revokes sessions)
  - Export data (JSON)
  - Delete user (optionally transfer data to another user first)

Security
- All admin endpoints enforce server-side admin checks, CSRF tokens, and lightweight rate limits.
- All mutations are recorded in the admin_audit table with actor, target and details.

Health & Logs
- Health section shows app version, PHP version, DB connectivity, counts, disk free space and last cron runs if tracked.
- Logs button fetches the last lines of the PHP error_log for quick inspection.

Notes
- Authentication is local-only; Clerk integration has been removed. Use /login.php and /register.php.
- Configure SMTP in the Admin panel to enable verification emails and admin resend actions.


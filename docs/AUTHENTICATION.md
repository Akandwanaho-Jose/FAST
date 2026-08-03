# Authentication and Administrator Setup

The authentication system is implemented and covered by automated tests.

## Security controls

- Passwords are stored only with `password_hash(PASSWORD_DEFAULT)`.
- Login uses `password_verify()` and a timing-safe dummy hash for unknown
  accounts.
- Login errors do not reveal whether an email address exists.
- Sessions use strict cookie-only mode, `HttpOnly`, `SameSite=Lax`, an
  application-specific cookie path, idle expiry, and ID regeneration.
- Secure cookies are enabled automatically when HTTPS is active.
- Every state-changing authentication form requires a CSRF token.
- Five failed attempts within 15 minutes trigger a 15-minute lock.
- Existing accounts are locked in `users`; unknown-account attempts are limited
  through hashed files in `storage/cache/login`.
- Deleted, inactive, and locked users cannot sign in.
- Temporary-password users are forced to change their password before reaching
  `/admin`.
- Logout requires POST plus CSRF validation and destroys the session.
- Successful login, failed login, blocked login, password change, administrator
  creation, and logout actions are recorded in `audit_logs`.
- Passwords, password hashes, CSRF tokens, and session identifiers are not
  written to application logs.

## First administrator

Check whether the one-time setup is available:

```powershell
php bin\create-admin.php --status
```

If no account exists, create the first administrator with:

```powershell
php bin\create-admin.php --name="Verified Name" --email="verified@example.org"
```

The utility prompts twice for a temporary password using a hidden console
prompt. The password must contain at least 12 characters, uppercase and
lowercase letters, a number, and a symbol.

The utility:

1. Refuses to run if any user already exists.
2. Creates an active user with `must_change_password = 1`.
3. Assigns the existing `super_admin` role.
4. Records `auth.admin_created` in `audit_logs`.
5. Stores only a password hash.

After one user exists, the setup utility disables itself.

## Browser test

1. Open `http://localhost/fast/public/login`.
2. Sign in with the temporary password.
3. Confirm redirection to `/password/change`.
4. Enter the temporary password and a new strong password.
5. Confirm redirection to `/admin`.
6. Sign out using the POST-based logout button.
7. Confirm `/admin` redirects back to `/login`.

## Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/login` | Login form |
| POST | `/login` | Authenticate and throttle failures |
| GET | `/password/change` | Authenticated password-change form |
| POST | `/password/change` | Verify current password and store new hash |
| GET | `/admin` | Protected, permission-aware dashboard |
| POST | `/logout` | Audit and destroy the session |

## Database impact

The phase uses the existing `users`, `roles`, `user_roles`, and `audit_logs`
tables. No table or column was added, renamed, removed, or altered.

Creating the real administrator will insert:

- One `users` row
- One `user_roles` row
- One `audit_logs` row

Authentication then updates only account security timestamps/counters and adds
audit records.

## Deployment requirement

Use a restricted application database account. Never run the deployed
application with a database administrator account or `ALL PRIVILEGES`.

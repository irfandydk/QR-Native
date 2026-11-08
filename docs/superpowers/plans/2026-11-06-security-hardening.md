# Security Hardening (A2) Implementation Plan

> Executed inline (superpowers:executing-plans). Source: security audit run-1 report (`~/security-audit-skill/QR-SAR/run-1/REPORT.md`, section 4) and user decisions on 2026-11-06.

**Goal:** Close the open audit leads so QR-SAR is safe to run on public hosting.

**Global constraints**
- Uncommitted work on `main`. Do not commit; the user commits when ready.
- Every fix has a test that fails first. Tests run against `qrsar_test` (`DB_DATABASE=qrsar_test php tests/run_all.php`, plus `node tests/js/bra_templates.test.js`).
- HTTP-level checks go in `tests/test_http_security.php`. They run against a server set by `QRSAR_TEST_URL` and skip when that variable is unset.
- User decisions:
  - `APP_KEY`: when empty, generate a random key once and persist it in `storage/app.key`.
  - Default password: force a password change at login (the user expressed no preference, so I chose this).

## Tasks
1. **Session tied to credential.**
   - `AuthHelper::setSession` stores a hash fingerprint and the login time.
   - `AuthHelper::isLoggedIn` re-checks the Users row once per request. It requires the account to be active, the fingerprint to match, and an absolute lifetime of 12 h.
   - `isAdmin()` requires `isLoggedIn()`.
   - A legacy session without a fingerprint adopts the current one.
   - Test: `tests/test_session_credential_binding.php`.
2. **APP_KEY.** `qrsar_resolve_app_key(string $env, string $file): string` in `config/app.php`.
   - No sha1(__DIR__) fallback.
   - The literal salts in `DocVerifyHelper`/`BraModel` are removed.
   - Test: `tests/test_app_key_resolution.php`.
3. **Forced password change.**
   - Migration adds `Users.must_change_password`. The migrate step flags accounts whose hash still verifies the default.
   - Reset sets the flag; `changePassword`, `save_user` and `save_admin` with a new password clear it.
   - Login stores the flag in the session.
   - `requireLogin` redirects flagged sessions to the change page: `profile.php` for USER, `admin.php?tab=admin` for ADMIN.
   - The API returns 403 `PASSWORD_CHANGE_REQUIRED`, except auth endpoints.
   - Tests: `tests/test_forced_password_change.php` and the HTTP test.
4. **CSV formula neutralisation** in `AuditHelper::exportCsv`. Test: `tests/test_audit_csv_formula.php`.
5. **Stored-data validation.**
   - GIS ids are `[A-Za-z0-9_.:-]`; colours pass a hex/rgb(a)/hsl(a)/named allowlist.
   - `status_korban` is restricted to `DP|S|MD|H`.
   - Test: `tests/test_stored_input_validation.php`.
6. **HTTP fixes (test first: `tests/test_http_security.php`):**
   - Router rejects dot segments, NUL and backslash.
   - The fallthrough include only serves root dispatchers.
   - Static files are served only from `assets/`, `uploads/` and the favicons.
   - `operasi.php` with no action requires login.
   - CORS requires a full-origin match.
   - SITREP/RENOPS edits are bound to their own operation.
7. **Client output encoding** (checked with grep and in the browser):
   - `showToast` uses `titleText`.
   - RENOPS import handlers use JSON-encoded arguments.
   - The BRA view inline script uses `jsonSafe`.
   - `radial` and the victim status label are escaped in the print/tab views.

# Wallet App Backend — Installation & Setup Guide

This guide takes you from a bare machine to a fully running Wallet App backend, and covers what you need for production deployment. It assumes Windows + XAMPP as the local dev target (matching this project's own environment) but notes Linux/macOS equivalents where they differ.

---

## 1. Required Software

| Software | Minimum version | Notes |
|---|---|---|
| PHP | 8.2 (8.3+ preferred) | With extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, **`gd`** (required for QR code generation) |
| Composer | 2.x | https://getcomposer.org |
| MySQL | 8.0+ (or MariaDB 10.6+) | A standalone MySQL Server install is recommended over XAMPP's bundled MySQL — see §26 "Common Errors" if XAMPP's MySQL fails to authenticate |
| Node.js | 18+ | Only needed once Mobile-App / Admin-Web are built |
| Git | any recent | |

Verify your setup:
```bash
php -v
php -m | grep -i gd        # must print "gd" — see §26 if missing
composer -V
mysql --version
```

---

## 2. Laravel Installation

The backend was scaffolded with:
```bash
cd Wallet-App/Backend
composer create-project laravel/laravel .
```
If PHP is below 8.3, Composer automatically installs the newest Laravel version compatible with your PHP (this project runs on **Laravel 12** for that reason). To get Laravel 13, upgrade PHP to 8.3+ first, then re-run the command in a fresh directory.

---

## 3. Composer Commands Reference

```bash
composer install              # install dependencies (fresh clone)
composer update                # update dependencies within composer.json constraints
composer dump-autoload          # regenerate the autoloader after adding new classes manually
composer require <package>      # add a new package
```

Key packages used by this project (already in `composer.json`):
- `laravel/sanctum` — API token authentication
- `spatie/laravel-permission` — roles & permissions (admin panel)
- `endroid/qr-code` — QR code image generation (requires the `gd` PHP extension)
- `predis/predis` — Redis client (optional, only used if `REDIS_*`/queue config points at Redis)

---

## 4. Environment Configuration

Copy the example file and generate an app key:
```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env` — see §18 for the full variable reference. At minimum, set:
- `DB_*` — your MySQL credentials (see §5)
- `APP_URL` — the base URL the API will be served from

---

## 5. MySQL Database Creation

Create the database and user (adjust credentials to your own):
```sql
CREATE DATABASE walletdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then in `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=walletdb
DB_USERNAME=root
DB_PASSWORD=your_password
```

> If you're on Windows with **XAMPP**, its bundled MySQL can fail with `Plugin caching_sha2_password could not be loaded` if the install is missing its `mysql/lib` folder. In that case, either repair/reinstall XAMPP, or point `DB_HOST`/`DB_PORT` at any other MySQL 8 instance (e.g. MySQL Workbench's server, a Docker container, or a cloud MySQL instance) — the app doesn't care which MySQL server it talks to as long as the credentials are correct.

---

## 6. Running Migrations

```bash
php artisan migrate            # run pending migrations
php artisan migrate:fresh       # drop all tables and re-run everything (dev only!)
php artisan migrate:status      # see what's been run
```

The migrations create (in order): users/sessions, cache, jobs, permission tables, Sanctum tokens, devices, OTPs, wallets, wallet_transactions (ledger), bank_accounts, transfers, scratch_cards, reward_points column, identity_verifications, virtual_cards, notifications.

---

## 7. Running Seeders

```bash
php artisan db:seed
```

This runs, in order:
1. **RolePermissionSeeder** — creates the `super-admin`, `admin`, `support` roles and their permissions.
2. **AdminUserSeeder** — creates a bootstrap super-admin account:
   - Phone: `9999999999` / PIN: `123456` (for the mobile-app side of this same account, if ever needed)
   - **Username:** `superadmin` / **Password:** `ChangeMe123!`
   - Sign in to Admin-Web with the username/password above — admins use `POST /admin/auth/login`, a separate credential set from the phone+OTP flow regular users go through (see `docs/API.md` §12). A role alone doesn't grant panel access; the account also needs credentials, which is exactly what this seeder sets.

   **⚠️ Change this account's password (and PIN) before deploying to production** — `PUT /admin/users/{id}/credentials` resets a username/password pair for any user; use it on this account first.

To reset and reseed everything in one step (dev only):
```bash
php artisan migrate:fresh --seed
```

---

## 8. Storage Linking

KYC documents (passport/citizen ID + selfie images) are stored on **Cloudinary** as private (`type: authenticated`) assets — see §14 for configuration. They are never publicly reachable by URL; `CloudinaryService::fetchImage()` downloads them server-side via a freshly signed delivery URL, and the admin panel only ever receives them proxied through `GET /admin/verifications/{id}/document-image` / `.../selfie-image` (Bearer-auth required).

If you add genuinely public local assets (e.g. marketing images) to the `public` disk, link it with:
```bash
php artisan storage:link
```

---

## 9. Sanctum Setup

Sanctum was installed via:
```bash
php artisan install:api
```
which published `config/sanctum.php` and the personal access tokens migration, and registered the `EnsureFrontendRequestsAreStateful`/token guard wiring automatically.

This project uses **token-based** auth only (mobile + admin panel both send `Authorization: Bearer {token}`), not Sanctum's cookie/SPA session mode — so `SANCTUM_STATEFUL_DOMAINS` and CSRF cookies are not relevant here. Tokens are issued with a 30-day expiry (`AuthService::verifyAndAuthenticate`); adjust that if you need shorter/longer sessions.

---

## 10. Queue Setup

`QUEUE_CONNECTION=database` by default (queue table created by the standard Laravel migrations). Nothing in this codebase currently dispatches queued jobs (notifications send synchronously), so a queue worker is **optional** today — but if you later mark `WalletNotification` or FCM sending as `ShouldQueue`, start a worker with:
```bash
php artisan queue:work
```
For production, run this under a process supervisor (see §11 and §20).

---

## 11. Scheduler Setup

`routes/console.php` is the place to register scheduled tasks (e.g. expiring old OTPs, expiring unredeemed scratch cards). None are registered by default. Once you add some, wire the scheduler via cron (Linux) or Task Scheduler (Windows):
```
* * * * * cd /path-to/Backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 12. Mail Configuration

`MAIL_MAILER=log` by default — emails are written to the log instead of sent, which is fine for local dev. For production, set:
```
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@yourapp.com
```
No email-based flows are implemented yet (auth is phone/OTP-based) — this is scaffolding for future features (receipts, statements-by-email, etc.) per the spec's "Email (future)" note.

---

## 13. FCM Configuration (Push Notifications)

Push notifications are sent via Firebase Cloud Messaging's HTTP v1 API. To enable real delivery:

1. Create a Firebase project → Project Settings → Service Accounts → generate a key.
2. Set in `.env`:
   ```
   FCM_PROJECT_ID=your-firebase-project-id
   FCM_ACCESS_TOKEN=a-valid-oauth2-access-token-for-the-service-account
   ```
   > `FCM_ACCESS_TOKEN` must be a short-lived OAuth2 bearer token minted from your service account JSON (Google's `googleapis/google-auth-library` or similar) — the HTTP v1 API does not accept a static server key like the legacy API did. For production, wire a small scheduled job that refreshes this token and updates config/cache, or swap `FcmService` to mint tokens on demand from a stored service-account credential file.
3. Leave both blank to run in **log-only mode** — the app still works end-to-end (in-app/database notifications are unaffected), it just won't push to real devices; a line is written to the log instead so you can verify what *would* have been sent.

---

## 14. Cloudinary Configuration (KYC Document Storage)

Identity verification photos (passport/citizen ID + selfie) are uploaded to Cloudinary as **private** assets (`type: authenticated`) via `App\Services\CloudinaryService`. They are never publicly reachable by URL — even someone with the exact Cloudinary `public_id` gets a 401 without a validly signed request.

1. Create a free account at cloudinary.com and grab your Cloud name, API key, and API secret from the dashboard.
2. Set in `.env`:
   ```
   CLOUDINARY_CLOUD_NAME=your-cloud-name
   CLOUDINARY_API_KEY=your-api-key
   CLOUDINARY_API_SECRET=your-api-secret
   ```
3. Uploads go through `CloudinaryService::uploadPrivateImage()`, which stores the returned Cloudinary `public_id` in the `identity_verifications.document_image_path` / `selfie_image_path` columns (naming is historical — they hold a Cloudinary public_id, not a filesystem path).
4. The admin panel never talks to Cloudinary directly. `GET /admin/verifications/{id}/document-image` and `.../selfie-image` fetch the bytes server-side via `CloudinaryService::fetchImage()` (a freshly signed URL, requested with `Http::get()`) and stream them back through our own Bearer-authenticated endpoint — the same access-control model as before, just backed by Cloudinary instead of local disk.
5. There is no offline/local fallback — these credentials are required for the verification submit flow to work at all, since that's the only place in the app that accepts image uploads.

---

## 15. OTP Provider Configuration (placeholder)

OTPs are currently **logged only** (`Log::info(...)` in `OtpService::generate()`) and, in `local`/`testing` environments, returned in the API response as `debug_otp` for easy testing. To wire a real SMS provider (Twilio, MSG91, etc.):

1. Add the provider's SDK via Composer.
2. In `App\Services\OtpService::generate()`, replace/augment the `Log::info(...)` call with a call to your SMS provider, sending `$code` to `$phone`.
3. Remove the `debug_otp` inclusion for any non-local environment (already gated behind `app()->environment(['local','testing'])`, so no change needed there).
4. Add the provider's credentials to `.env` and `config/services.php` following the same pattern as `fcm`.

---

## 16. API Testing

Run the automated test suite (uses an in-memory SQLite database, configured in `phpunit.xml` — no setup needed):
```bash
php artisan test
```

For manual testing, start the dev server:
```bash
php artisan serve
```
and either use the Postman collection (§17) or curl, e.g.:
```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","phone":"9876543210"}'
```

---

## 17. Postman Collection

Import [`docs/postman_collection.json`](postman_collection.json) into Postman. It defines two collection variables:
- `base_url` — defaults to `http://localhost:8000/api/v1`
- `token` — paste a Bearer token here after calling **Auth → Verify OTP** (all other requests inherit it automatically via collection-level Bearer auth)

Endpoints are grouped into folders matching this document's sections (Auth, Devices, PIN, Wallet, Bank Accounts, Limits, Transfers, QR, Rewards, Verification, Virtual Card, Notifications, Admin).

---

## 18. Environment Variables Reference

| Variable | Purpose | Default |
|---|---|---|
| `APP_ENV` | `local` enables `debug_otp` in responses and verbose error JSON | `local` |
| `APP_DEBUG` | show stack traces in error responses | `true` |
| `DB_*` | MySQL connection | — |
| `CORS_ALLOWED_ORIGINS` | comma-separated allowed origins for browser clients (Admin-Web), or `*` | `*` |
| `FCM_PROJECT_ID` / `FCM_ACCESS_TOKEN` | push notifications (§13) | blank = log-only |
| `CLOUDINARY_CLOUD_NAME` / `CLOUDINARY_API_KEY` / `CLOUDINARY_API_SECRET` | KYC document/selfie storage (§14) — required, no fallback | — |
| `WALLET_CURRENCY` | default wallet currency code | `XOF` |
| `OTP_LENGTH` / `OTP_EXPIRY_MINUTES` / `OTP_MAX_ATTEMPTS` / `OTP_RESEND_COOLDOWN_SECONDS` | OTP behavior | 6 / 5 / 5 / 60 |
| `PIN_LENGTH` / `PIN_MAX_ATTEMPTS` / `PIN_LOCKOUT_MINUTES` | PIN behavior | 6 / 5 / 15 |
| `WALLET_QR_DOMAIN` | suffix used for generated UPI-style handles (`phone@domain`) | `wallet` |
| `LIMIT_BASIC_MONTHLY` / `LIMIT_VERIFIED_MONTHLY` | monthly transfer limit per tier — the only period tracked (`premium` is always unlimited) | see `.env.example` |
| `FEE_WALLET_TO_WALLET` / `FEE_ACCOUNT_TO_ACCOUNT` | platform fee for peer payments — deducted from what the recipient receives, not added on top | 0.01 / 0.01 |
| `FEE_WALLET_TO_BANK` / `FEE_BANK_TO_WALLET` / `FEE_ACCOUNT_TO_WALLET` | fee rate (fraction of amount) for withdrawals/deposits/self-moves — added on top of the debited amount where applicable | 0.005 / 0 / 0 |
| `REWARD_CASHBACK_RATE` / `REWARD_POINTS_PER_TXN` / `REWARD_MIN_TXN_AMOUNT` | scratch-card reward tuning | 0.01 / 10 / 100 |
| `PASSPORT_EXPIRY_BUFFER_MONTHS` | passport must be valid at least this many months out to be accepted | 6 |

All of the above are read via `config/wallet.php` and `config/services.php` — never reference `env()` directly outside config files (this keeps config cacheable in production via `php artisan config:cache`).

---

## 19. Folder Structure

```
app/
  Enums/                 Backed PHP enums (statuses, types, categories)
  Events/                Domain events (e.g. TransferCompleted)
  Exceptions/            ApiException (uniform business-error → JSON mapping)
  Http/
    Controllers/Api/V1/  One controller per resource; Admin/ subfolder for admin-only endpoints
    Middleware/          VerifyPin, EnsureUserIsAdmin
    Requests/            Form Request validation, grouped by module
    Resources/            JsonResource API transformers, Admin/ subfolder for admin variants
  Listeners/             Event listeners (auto-discovered by Laravel from this folder)
  Models/                Eloquent models
  Notifications/         WalletNotification (database + FCM)
  Observers/              UserObserver (auto-creates a wallet on user creation)
  Policies/                Ownership authorization (BankAccount, Device, ScratchCard)
  Providers/               AppServiceProvider (rate limiters, observers), RepositoryServiceProvider (DI bindings)
  Repositories/
    Contracts/             Interfaces
    Eloquent/              Implementations
  Services/                Business logic — one service per module, orchestrates repositories
  Traits/                  ApiResponse (uniform JSON envelope helper)
database/
  factories/               Model factories for tests/seeding
  migrations/               Schema, in dependency order
  seeders/                  RolePermissionSeeder, AdminUserSeeder
docs/
  API.md                   Full endpoint reference
  SETUP.md                  This file
  postman_collection.json   Importable Postman collection
routes/
  api.php                   Mounts /api/v1 → api_v1.php
  api_v1.php                All user-facing routes
  api_v1_admin.php           All /admin/* routes (separate file for clarity)
tests/
  Feature/                  HTTP-level tests per module
  Unit/                     Pure service-logic tests
```

**Architecture pattern:** Controller → Form Request (validation) → Service (business logic, transactions, authorization) → Repository (data access) → Model. Controllers never touch Eloquent directly; Services never touch `$request`. This keeps every layer independently testable and swappable (e.g., a repository could later move to a different data store without touching services or controllers).

---

## 20. Deployment Guide

1. **Server requirements:** PHP-FPM 8.2+ (with `gd`, `pdo_mysql`, `bcmath`), MySQL 8+, Nginx or Apache, a process manager (systemd/Supervisor) if you add queue workers.
2. **Deploy the code** (git pull or CI artifact), then:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan db:seed --force        # first deploy only
   php artisan config:cache
   php artisan route:cache
   php artisan event:cache
   php artisan storage:link           # only if you've added public assets
   ```
3. **Web server:** point the document root at `Backend/public`, not `Backend/`.
4. **Environment:** set `APP_ENV=production`, `APP_DEBUG=false`, real `DB_*`, `FCM_*`, `CORS_ALLOWED_ORIGINS` (never `*` in production — list your actual Mobile-App/Admin-Web origins), and a freshly generated `APP_KEY` (`php artisan key:generate` — do this once, then keep it stable; rotating it invalidates all encrypted data, including stored bank account numbers and virtual card numbers).
5. **Queue worker** (if you add queued jobs later): run `php artisan queue:work --tries=3` under Supervisor, and `php artisan schedule:run` via cron (§11).
6. **Zero-downtime tip:** run `php artisan up`/`down` around migrations if the app has active traffic, or use a proper blue-green/rolling deploy strategy.

---

## 21. Production Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Config, route, and event caches built (`config:cache`, `route:cache`, `event:cache`)
- [ ] Real MySQL credentials, not the local dev ones
- [ ] `CORS_ALLOWED_ORIGINS` restricted to actual frontend origins
- [ ] Default seeded admin PIN (`123456`) changed
- [ ] FCM configured with a real project (or explicitly accepted as log-only)
- [ ] Real SMS provider wired for OTP delivery (§15) — **do not ship to production relying on `debug_otp`**, which is already disabled outside `local`/`testing` but confirm your `APP_ENV` is correct
- [ ] `.env` file permissions locked down (not web-readable, not committed to git)
- [ ] HTTPS enforced at the load balancer / web server
- [ ] Database backups scheduled (§23)
- [ ] Log rotation configured (§24)

---

## 22. Security Checklist

- [x] Passwords/PINs never stored in plaintext — PIN uses Laravel's `hashed` cast (bcrypt)
- [x] Bank account numbers and virtual card numbers stored **encrypted** (`Crypt::encryptString`, backed by `APP_KEY`) — never returned in full via the API, only masked
- [x] KYC documents (passport/citizen ID/selfie) stored as **private** (`type: authenticated`) Cloudinary assets, never public
- [x] All mutating endpoints behind Sanctum token auth; sensitive actions additionally require the transaction PIN
- [x] Rate limiting on auth/OTP endpoints to slow brute-force and OTP-spam
- [x] Ownership checked both in the service layer and via Laravel Policies (defense in depth) for bank accounts, devices, and scratch cards
- [x] Admin routes gated by role (`super-admin`/`admin`/`support`) via dedicated middleware; log viewing further restricted to `super-admin`
- [x] Admin panel login uses a separate username/password credential (hashed, same `password` column/cast as the rest of Laravel's auth) — a role alone never grants panel access, and neither does a password alone without a role
- [x] Mass-assignment protected everywhere via explicit `$fillable`
- [x] All money movement wrapped in DB transactions with row-level locking (`lockForUpdate`) to prevent race conditions on concurrent transfers
- [ ] **You must still:** rotate the seeded admin password (and PIN), put a WAF/rate limiter in front of the whole API in production, and review `CORS_ALLOWED_ORIGINS` before going live.

---

## 23. Backup Strategy

- **Database:** schedule `mysqldump` (or your managed DB provider's automated backups) at least daily, with point-in-time recovery (binlog) enabled given this is financial ledger data. Test restores periodically — an untested backup is not a backup.
- **KYC documents:** live on Cloudinary, not local disk — back up your Cloudinary account per its own retention settings; `identity_verifications` stores only the `public_id` reference, so losing the Cloudinary account without a separate backup breaks the audit trail.
- **`.env`:** keep a secure, access-controlled copy outside the deploy pipeline (secrets manager, vault) — never in version control.

---

## 24. Logging Strategy

- Default channel is `stack` → `single` file (`storage/logs/laravel.log`). For production, consider switching to `daily` (in `config/logging.php`, set `LOG_CHANNEL=daily`) so logs rotate automatically, and/or shipping to an external aggregator (Papertrail, CloudWatch, ELK) for durability and searchability across server restarts.
- Business-relevant events already logged: OTP generation (`OtpService`), FCM send attempts/failures (`FcmService`).
- Admins can tail the last N lines via `GET /admin/logs?lines=200` (super-admin only) — useful for quick production triage without shell access, but treat it as a supplement to, not a replacement for, real log aggregation.

---

## 25. Performance Optimization

- Run `php artisan config:cache`, `route:cache`, `event:cache` in production (skip these in local dev — they make `.env`/route changes invisible until cleared).
- Add composite indexes as query patterns emerge; the migrations already index the hot paths: `wallet_transactions(wallet_id, created_at)`, `transfers(sender_user_id, created_at)`/`(receiver_user_id, created_at)`, `otps(phone, purpose)`, etc.
- Use `->with(...)` eager loading for admin list endpoints (already done for users/banks/transfers/rewards in the Admin repositories) to avoid N+1 queries.
- Consider Redis for `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION` at scale instead of the database driver used in dev.
- Paginate everything user-facing (already the default across all list endpoints) — never return unbounded collections.

---

## 26. Common Errors & Fixes

These are real issues hit while building this project — kept here so you don't have to rediscover them.

| Symptom | Cause | Fix |
|---|---|---|
| `Cannot use laravel/laravel's latest version ... requires php ^8.3` | Composer downgrading to Laravel 12 | Expected on PHP 8.2 — either accept Laravel 12, or upgrade PHP to 8.3+ first |
| `ERROR 1045: Plugin caching_sha2_password could not be loaded` | XAMPP's MySQL is missing its `mysql/lib/plugin` folder (broken/partial install) | Repair/reinstall XAMPP, or point `DB_HOST` at a different MySQL 8 server |
| `Unable to generate image: please check if the GD extension is enabled` | `gd` not enabled in `php.ini` | Uncomment `extension=gd` in `php.ini`, restart the server |
| `Carbon::rawAddUnit(): Argument #3 must be of type int|float, string given` | A config value like `config('wallet.otp.expiry_minutes')` was read as a **string** from `.env` (env vars are always strings) and passed straight into `addMinutes()` | Cast numeric config values explicitly in the config file, e.g. `(int) env('OTP_EXPIRY_MINUTES', 5)` — already done in `config/wallet.php`; if you add new numeric env-backed config, cast it too |
| `Cannot access protected property ...ServiceProvider::$bindings` | A custom `$bindings` property on a `ServiceProvider` was declared `protected` | Laravel's base `ServiceProvider` auto-registers a **public** `$bindings` array itself — declare it `public`, don't also write a manual `register()` loop for it |
| A scratch card / notification is created **twice** per transfer | An event listener was registered both via explicit `Event::listen()` **and** auto-discovered (Laravel auto-discovers any `app/Listeners/*` class whose `handle()` type-hints an event) | Pick one registration method — for listeners living in `app/Listeners`, rely on auto-discovery and don't also call `Event::listen()` |
| `Duplicate entry '...' for key 'wallet_transactions_reference_number_unique'` | A single money transfer legitimately produces **two** ledger rows (debit + credit) sharing one reference number, but the column had a global unique constraint | Use a composite unique constraint scoped to `(wallet_id, reference_number, type)` instead of a bare unique on `reference_number` |
| `429 Too Many Requests` immediately after a legitimate forgot-pin → reset-pin flow | `reset-pin`/`verify-otp` were grouped under the same strict per-phone "1 request/minute" limiter meant for **OTP generation** endpoints (resend-otp, forgot-pin) | Put OTP-*verifying* endpoints under a separate, more lenient limiter than OTP-*generating* endpoints |
| Bank-account edits from another user silently succeed / 500 instead of 403 | Ownership check missing or only partially applied | This project checks ownership in **both** the service layer (`assertOwnership`) and via a Laravel Policy + `$this->authorize()` in the controller — apply the same pattern to any new owned resource |
| A `bank_to_wallet` deposit shows as a debit (red, "-₹X") in transaction lists | `TransferResource`'s `direction` was computed purely from `sender_user_id === $userId`, but `wallet_to_bank`/`bank_to_wallet` both set `sender_user_id` to the acting user (self-transfers) — the comparison only distinguishes parties for `wallet_to_wallet` | Derive `direction` from `type` first (`bank_to_wallet` → always `credit`, `wallet_to_bank` → always `debit`), and only fall back to the sender/receiver comparison for `wallet_to_wallet` |
| `Route [login] not defined` (500) when hitting a protected endpoint with no token and no `Accept: application/json` header | Laravel's default `Authenticate` middleware redirects unauthenticated requests to a named `login` route when `$request->expectsJson()` is false — this app is JSON-only and has no such route | Override `redirectTo()` in a custom `App\Http\Middleware\Authenticate` (extending the framework class) to always return `null`, and alias it over the default `'auth'` middleware in `bootstrap/app.php` |
| `expo-secure-store` throws `getValueWithKeyAsync is not a function` when running `expo start --web` | `expo-secure-store` has no web implementation (native-only) | Branch on `Platform.OS === 'web'` and fall back to `@react-native-async-storage/async-storage` (backed by `localStorage`) for token storage on web |

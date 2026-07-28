# Wallet App — API Documentation

## Overview

- **Base URL:** `http://localhost:8000/api/v1` (adjust host/port per environment)
- **Format:** JSON only, on every request and response.
- **Auth scheme:** Bearer token (Laravel Sanctum personal access tokens), obtained from `POST /auth/verify-otp`.
- **Header:** `Authorization: Bearer {token}` on every authenticated request.
- **Content-Type:** `application/json` (except file uploads, which use `multipart/form-data`).

### Response envelope

Every response follows the same shape:

```json
{
  "success": true,
  "message": "Human readable message",
  "data": { }
}
```

Paginated endpoints add `meta` and `links`:

```json
{
  "success": true,
  "message": "Success",
  "data": [ ],
  "meta": { "current_page": 1, "total": 42, "per_page": 20 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

Errors always look like:

```json
{
  "success": false,
  "message": "Human readable error",
  "errors": { "field": ["Validation message"] }
}
```

### Standard HTTP status codes used

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Resource created |
| 401 | Unauthenticated (missing/invalid token) |
| 403 | Unauthorized (not the resource owner / not an admin) |
| 404 | Resource not found |
| 422 | Validation failed / business rule violation (insufficient balance, limit exceeded, etc.) |
| 423 | Locked (PIN locked after too many failed attempts) |
| 429 | Too many requests (rate limited) |
| 500 | Server error |

### Rate limiting

| Limiter | Applies to | Limit |
|---|---|---|
| `auth` | register, login, verify-otp, reset-pin | 10 / minute / IP |
| `otp` | resend-otp, forgot-pin (OTP generation) | 3/min/IP **and** 1/min/phone |
| `sensitive` | pin set/change, transfers | 15 / minute / user |
| `api` (default) | everything else | 120 / minute / user or IP |

### PIN gate

Some endpoints require the user's 6-digit transaction PIN in addition to the bearer token:
- **GET** requests: pass it via header `X-Pin: 123456`
- **POST/PUT** requests: pass it via body field `"pin": "123456"`

Endpoints requiring PIN are marked **🔒 PIN required** below.

---

## 1. Authentication

### Register — `POST /auth/register`
Rate limit: `auth`

**Body**

| Field | Type | Rules |
|---|---|---|
| name | string | required, 2–100 chars |
| phone | string | required, 10–15 digits, optional leading `+` |
| nationality | string | required, must be one of `config('countries.list')` |

**Response 200**
```json
{ "success": true, "message": "OTP sent for registration verification.",
  "data": { "phone": "9876543210", "debug_otp": "123456" } }
```
> `debug_otp` is only included when `APP_ENV` is `local`/`testing`. In production, the OTP is delivered via SMS/log only (see setup guide for wiring a real SMS provider).

### Login — `POST /auth/login`
Rate limit: `auth`. Requires an already-registered, phone-verified account.

**Body:** `{ "phone": "9876543210" }`

**Response 200:** same shape as register (sends a login-purpose OTP).

### Verify OTP — `POST /auth/verify-otp`
Rate limit: `auth`. Finalizes registration or login and issues a Sanctum token.

**Body**

| Field | Type | Rules |
|---|---|---|
| phone | string | required |
| otp | string | required, 6 digits |
| purpose | string | required, `registration` \| `login` |
| device | object | optional |
| device.device_id | string | required if `device` present |
| device.device_name | string | optional |
| device.platform | string | optional, `ios`\|`android`\|`web` |
| device.fcm_token | string | optional |
| device.app_version | string | optional |

**Response 200**
```json
{
  "success": true,
  "message": "Authenticated successfully.",
  "data": {
    "user": { "id": 1, "name": "Alice", "phone": "9876543210", "phone_verified": true,
      "nationality": "Ivory Coast", "document_type": null, "document_number": null,
      "upi_handle": "9876543210@wallet", "status": "active", "tier": "basic",
      "has_pin": false, "created_at": "2026-07-17T07:25:27+00:00" },
    "token": "1|abcdef...",
    "requires_pin_setup": true
  }
}
```

### Resend OTP — `POST /auth/resend-otp`
Rate limit: `otp`. **Body:** `{ "phone": "...", "purpose": "registration|login|reset_pin" }`

### Forgot PIN — `POST /auth/forgot-pin`
Rate limit: `otp`. Sends an OTP to reset a forgotten PIN. **Body:** `{ "phone": "..." }`

### Reset PIN — `POST /auth/reset-pin`
Rate limit: `auth`.

**Body:** `{ "phone": "...", "otp": "123456", "pin": "222222", "pin_confirmation": "222222" }`

### Me — `GET /auth/me` 🔑 auth
Returns the authenticated user's profile (`UserResource`).

### Update nationality — `PUT /auth/nationality` 🔑 auth
**Body:** `{ "nationality": "Ivory Coast" }` — must be one of `config('countries.list')`. Changeable anytime after registration; only affects which document type is required on *future* verification submissions (past submissions keep the `document_type` they were submitted with).

### Logout — `POST /auth/logout` 🔑 auth
Revokes the current access token only.

### Logout all devices — `POST /auth/logout-all` 🔑 auth
Revokes every token issued to the user.

### Refresh token — `POST /auth/refresh` 🔑 auth
Issues a new token and revokes the one used to authenticate the request.
**Response:** `{ "data": { "token": "2|newtoken..." } }`

### Deactivate account — `POST /auth/deactivate` 🔑 auth
**Body:** `{ "pin": "123456" }` — verifies PIN, sets status to `deactivated`, revokes all tokens.

### Delete account — `DELETE /auth/account` 🔑 auth
**Body:** `{ "pin": "123456" }` — verifies PIN, anonymizes phone/email/upi_handle, soft-deletes the user.

---

## 2. Device Management

### List devices — `GET /devices` 🔑 auth
Returns all devices registered to the current user (`DeviceResource[]`).

### Update FCM token — `POST /devices/fcm-token` 🔑 auth
**Body:** `{ "device_id": "abc-123", "fcm_token": "..." }` — call this whenever the client's push token rotates.

### Remove device — `DELETE /devices/{device}` 🔑 auth
Revokes that device's session token and removes it. Owner-only (403 otherwise).

---

## 3. PIN

### Set PIN (first time) — `POST /pin` 🔑 auth
**Body:** `{ "pin": "123456", "pin_confirmation": "123456" }`
Fails with 422 if a PIN is already set (use Change PIN instead).

### Change PIN — `PUT /pin` 🔑 auth
**Body:** `{ "current_pin": "123456", "pin": "654321", "pin_confirmation": "654321" }`
5 wrong attempts locks the PIN for 15 minutes (423 response) — see `wallet.pin.*` config.

---

## 4. Wallet

### Get balance — `GET /wallet` 🔑 auth 🔒 PIN required
**Response**
```json
{ "data": { "wallet_number": "WAL1234567890", "balance": 3500.00, "frozen_balance": 0,
  "available_balance": 3500.00, "currency": "XOF", "status": "active", "created_at": "..." } }
```

### Mini statement — `GET /wallet/statement/mini` 🔑 auth
Last 10 wallet ledger entries.

### Full statement — `GET /wallet/statement/full` 🔑 auth
Paginated ledger. **Query params:** `type` (credit\|debit), `category`, `from`, `to`, `per_page`.

---

## 5. Bank Accounts

### List — `GET /banks` 🔑 auth

### Add — `POST /banks` 🔑 auth
**Body**

| Field | Rules |
|---|---|
| bank_name | required, string |
| account_holder_name | required, string |
| account_number | required, 9–18 digits |
| ifsc_code | required, format `AAAA0XXXXXX` |
| account_type | optional, `savings`\|`current` |
| is_primary | optional, boolean |

First account added is always forced primary. Duplicate account numbers (per user) are rejected (422).

### Update — `PUT /banks/{bankAccount}` 🔑 auth
Editable: `bank_name`, `account_holder_name`, `account_type`, `is_primary`. Owner-only (403).

### Set primary — `POST /banks/{bankAccount}/primary` 🔑 auth

### Delete — `DELETE /banks/{bankAccount}` 🔑 auth
If the deleted account was primary, the next-oldest account is auto-promoted.

> Bank accounts must be verified by an admin (`POST /admin/banks/{id}/verify`) before they can be used for wallet-to-bank withdrawals.

---

## 6. Limits

### Get usage vs. limits — `GET /limits` 🔑 auth
```json
{ "data": { "monthly": 1200,
  "limits": { "monthly": 150000 } } }
```
There is only a monthly cap (no daily or yearly limits). Limits are tier-based (`basic` / `verified` / `premium`, premium = unlimited) and configurable via `.env` (`LIMIT_*` keys). Only outbound spending counts against usage — `wallet_to_wallet`, `wallet_to_bank`, and `account_to_account` — not `account_to_wallet`, since that's a self-move between a user's own two balances and the money never leaves their custody.

---

## 7. Money Transfers

All transfer endpoints are rate-limited (`sensitive`) and produce a `Transfer` with a unique `reference_number`.

### Wallet → Wallet (Send Money) — `POST /transfers/wallet-to-wallet` 🔑 auth 🔒 PIN in body
**Body**

| Field | Rules |
|---|---|
| receiver | required — UPI handle, phone, or wallet number |
| amount | required, numeric, min 1 |
| pin | required, 6 digits |
| note | optional, string, max 255 |

A 1% platform fee (`FEE_WALLET_TO_WALLET`) is deducted from what the recipient receives — the sender's wallet is debited exactly `amount`, and the receiver's Account is credited `amount - fee`. Same mechanic for `POST /transfers/account-to-account` (`FEE_ACCOUNT_TO_ACCOUNT`). Rejects: sending to self, insufficient balance, limit exceeded.

### Wallet → Bank (Withdraw) — `POST /transfers/wallet-to-bank` 🔑 auth 🔒 PIN in body
**Body:** `bank_account_id` (required, must belong to user and be verified), `amount`, `pin`, `note`.
A fee may apply (`FEE_WALLET_TO_BANK`, default 0.5%).

### Bank → Wallet (Top-up) — `POST /transfers/bank-to-wallet` 🔑 auth
**Body:** `bank_account_id`, `amount`, `note`. No PIN required (self-funding, low risk).

### History — `GET /transfers` 🔑 auth
Paginated, both sent and received. **Query:** `type`, `status`, `from`, `to`, `per_page`.

### Show one — `GET /transfers/{reference}` 🔑 auth
Only visible to sender or receiver.

**Example transfer object**
```json
{
  "reference_number": "TXN20260717081340959K",
  "type": "wallet_to_wallet",
  "direction": "debit",
  "status": "success",
  "amount": 150.00, "fee": 1.50, "total_amount": 150.00,
  "counterparty": { "name": "Bob", "upi_handle": "9000000002@wallet" },
  "sender_note": null, "receiver_note": null, "failure_reason": null,
  "completed_at": "...", "created_at": "..."
}
```

---

## 8. QR Payments

### My QR — `GET /qr` 🔑 auth
```json
{ "data": { "upi_handle": "9876543210@wallet", "wallet_number": "WAL...",
  "qr_image": "data:image/png;base64,..." } }
```

### Download QR — `GET /qr/download` 🔑 auth
Returns a raw `image/png` with `Content-Disposition: attachment`.

### Validate a scanned QR — `POST /qr/validate` 🔑 auth
**Body:** `{ "payload": "{\"v\":1,\"type\":\"wallet_pay\",\"upi\":\"9000000002@wallet\"}" }`
(Accepts a bare UPI handle string too.) Returns the receiver's public info so the app can show a confirmation screen before calling `wallet-to-wallet`.

---

## 9. Rewards (Scratch Cards)

A scratch card is created automatically on every successful `wallet_to_wallet`/`wallet_to_bank` transfer ≥ `REWARD_MIN_TXN_AMOUNT` (default 100 CFA).

### List — `GET /rewards` 🔑 auth
Reward type/value are hidden until scratched.

### Summary — `GET /rewards/summary` 🔑 auth
```json
{ "data": { "total_cashback_earned": 120.5, "unscratched_count": 2,
  "unredeemed_count": 1, "reward_points": 40 } }
```

### Scratch — `POST /rewards/{scratchCard}/scratch` 🔑 auth
Reveals the reward (`cashback`, `points`, `coupon`, or `lucky`). Owner-only.

### Redeem — `POST /rewards/{scratchCard}/redeem` 🔑 auth
Cashback/lucky rewards are credited to the wallet; points add to `reward_points`; coupons are marked redeemed. Must be scratched first, and not expired (30-day expiry) or already redeemed.

---

## 10. Identity Verification & Virtual Visa Card

### Submit — `POST /verification` 🔑 auth (multipart/form-data)

Document type is decided server-side from the user's `nationality` (set at registration), not sent by the client: **Ivory Coast** nationals submit their national citizen ID; everyone else submits a passport.

| Field | Rules |
|---|---|
| document_number | required, string, max 20 |
| document_expiry | required, date, must be > 6 months in the future |
| document_image | required, image, max 5MB |
| selfie_image | required, image, max 5MB |

Rejected if a verification is already `approved`, or another is still `pending`. The response's `document_type` field is `passport` or `citizen_id`.

### Current status — `GET /verification` 🔑 auth
### History — `GET /verification/history` 🔑 auth

On admin **approval**: user's `tier` becomes `verified` (raising transfer limits) and a virtual Visa card is activated automatically.

### Virtual card — `GET /virtual-card` 🔑 auth
```json
{ "data": { "masked_card_number": "4XXX XXXX XXXX 3795", "expiry_date": "07/29",
  "status": "active", "activated_at": "..." } }
```
> The virtual card is a **simulated / mock** card for demonstration — it is not connected to a real card network and cannot be used for real payments. Wiring a genuine card-issuing provider (e.g., Marqeta, Galileo) is a follow-up integration.

---

## 11. Notifications

### List — `GET /notifications` 🔑 auth (paginated)
### Unread count — `GET /notifications/unread-count` 🔑 auth
### Mark one as read — `POST /notifications/{notification}/read` 🔑 auth
### Mark all as read — `POST /notifications/read-all` 🔑 auth

Notifications are created automatically for: transfer sent/received, withdrawal/deposit, new scratch card, verification approved/rejected, bank account verified, and admin broadcasts. Push delivery uses FCM when `FCM_PROJECT_ID`/`FCM_ACCESS_TOKEN` are configured; otherwise pushes are logged only (in-app/database notifications always work regardless).

---

## 12. Admin API

Admins authenticate separately from regular users — with a **username and password**, not phone/OTP (the Admin-Web panel is not a phone in someone's hand; OTP delivery doesn't make sense for it). Every other route below is prefixed `/admin` and requires **both**:
1. A valid Bearer token
2. The authenticated user holding role `super-admin`, `admin`, or `support` (403 otherwise)

Roles/permissions are seeded via `RolePermissionSeeder` (see setup guide). Assign a role to a user with `PUT /admin/users/{id}/role`, and give them login credentials with `PUT /admin/users/{id}/credentials` — both are required before they can sign in (a role alone, with no username/password, cannot log in; credentials alone, with no admin role, are rejected at login).

### Admin login — `POST /admin/auth/login`
Rate limit: `auth`. **Not** behind the `admin` middleware (there's no token yet).

**Body:** `{ "username": "superadmin", "password": "..." }`

**Response 200**
```json
{ "data": { "user": { "...": "UserResource, includes roles[] and username" }, "token": "1|abc..." } }
```
Rejects with 401 for an unknown username or wrong password (same message either way, to avoid confirming which usernames exist), and 403 if the user has no admin role or is not `active`.

### Forgot admin password — `POST /admin/auth/forgot-password`
Rate limit: `otp`. Self-service alternative to having another admin reset your credentials via the endpoint below.

**Body:** `{ "username": "superadmin" }`

**Response 200**
```json
{ "data": { "masked_phone": "******5030", "debug_otp": "653608" }, "message": "An OTP has been sent to the phone number on file for this admin account." }
```
The OTP is delivered to the phone number already on file for that admin's user account (same OTP infrastructure as the mobile login/reset-PIN flows) — there is no email on file to fall back to. `debug_otp` is only populated outside production, where no real SMS provider is configured; in production it is `null` and the code must come from the actual SMS. Returns 404 for a username with no matching admin account (deliberately less guarded than login, since this is a recovery path an admin will use before they have a token).

### Reset admin password — `POST /admin/auth/reset-password`
Rate limit: `auth`. Completes the forgot-password flow above.

**Body:** `{ "username": "superadmin", "otp": "653608", "password": "NewPass123!", "password_confirmation": "NewPass123!" }`

**Response 200:** `{ "message": "Password reset successfully. You can now log in." }`. Reuses the standard OTP validation (expiry, max attempts, already-consumed) from `OtpService`.

### Set/reset admin credentials — `PUT /admin/users/{id}/credentials`
Body: `{ "username": "alice_support", "password": "min 8 chars" }`. Username must be unique (letters, numbers, `-`/`_` only). Overwrites any existing credentials for that user — use this to onboard a new admin, or as another admin to reset someone's forgotten password on their behalf (the self-service flow above is for resetting your own).

### Dashboard — `GET /admin/dashboard`
Aggregate counts: users, wallets (total/frozen balance), transactions (today/pending), verifications, rewards.

### Users
- `GET /admin/users` — paginated. Query: `status`, `tier`, `nationality`, `search` (matches name/phone/UPI handle), `per_page`.
- `POST /admin/users` — creates a user directly from the admin panel (walk-in customers, support-assisted signups). Body: `{ "name": "...", "phone": "...", "nationality": "..." }` (same rules as self-registration). The account is created **active and phone-verified** immediately — no OTP round-trip — so it can request a login OTP right away; the user still sets their own PIN on first login. Rejects a phone number that's already registered.
- `GET /admin/users/{id}`
- `PUT /admin/users/{id}/status` — body `{ "status": "active|deactivated|deleted" }`
- `PUT /admin/users/{id}/tier` — body `{ "tier": "basic|verified|premium" }`
- `PUT /admin/users/{id}/role` — body `{ "role": "super-admin|admin|support" }`

### Wallets
- `GET /admin/wallets` — query: `status`, `search`, `per_page`.
- `POST /admin/wallets/{wallet}/freeze`
- `POST /admin/wallets/{wallet}/unfreeze`
- `POST /admin/wallets/{wallet}/adjust` — body `{ "type": "credit|debit", "amount": 500, "reason": "..." }` (manual correction, fully audited in the wallet ledger under category `adjustment`)

### Transactions
- `GET /admin/transactions` — query: `type`, `status`, `from`, `to`, `per_page`.
- `GET /admin/transactions/{reference}`
- `POST /admin/transactions/{reference}/reverse` — body `{ "reason": "..." }`. Only `success` transfers can be reversed, and only if the affected wallet(s) still hold sufficient balance.

### Rewards
- `GET /admin/rewards` — query: `reward_type`, `is_redeemed`, `per_page`.

### Verifications (Passport Approval)
- `GET /admin/verifications` — query: `status`, `per_page`.
- `POST /admin/verifications/{verification}/approve`
- `POST /admin/verifications/{verification}/reject` — body `{ "reason": "..." }`
- `GET /admin/verifications/{verification}/document-image` — streams the uploaded passport/citizen ID photo (binary, `Bearer` auth required — not a public URL)
- `GET /admin/verifications/{verification}/selfie-image` — streams the uploaded selfie (binary, `Bearer` auth required)

### Bank Management
- `GET /admin/banks` — query: `is_verified`, `per_page`.
- `POST /admin/banks/{bankAccount}/verify`

### Reports & Analytics
- `GET /admin/reports/transactions-summary` — query: `from`, `to`, `status`.
  ```json
  { "data": { "total_volume": 12500.00, "total_fees_collected": 42.50,
    "volume_by_type": { "wallet_to_wallet": 8000, "wallet_to_bank": 3000, "bank_to_wallet": 1500 } } }
  ```

### Notifications
- `POST /admin/notifications/broadcast` — body `{ "title": "...", "body": "..." }`. Sends to every phone-verified user.

### Roles & Permissions
- `GET /admin/roles`
- `GET /admin/permissions`

### Settings & Limits
- `GET /admin/settings` — dumps the current `config('wallet')` tree (read-only; change via `.env` + `config:clear`).
- `GET /admin/limits` — the tier limit table only.

### Logs
- `GET /admin/logs` — tails `storage/logs/laravel.log` (super-admin only). Query: `lines` (default 200).

---

## Enumerations reference

| Enum | Values |
|---|---|
| User status | `active`, `deactivated`, `deleted` |
| User tier | `basic`, `verified`, `premium` |
| Wallet status | `active`, `frozen`, `closed` |
| Transfer type | `wallet_to_wallet`, `wallet_to_bank`, `bank_to_wallet` |
| Transfer status | `pending`, `success`, `failed`, `cancelled`, `refunded`, `reversed` |
| Ledger category | `wallet_to_wallet`, `wallet_to_bank`, `bank_to_wallet`, `reward`, `refund`, `fee`, `adjustment`, `reversal` |
| Reward type | `cashback`, `points`, `coupon`, `lucky` |
| Verification status | `pending`, `approved`, `rejected` |
| Device platform | `ios`, `android`, `web` |

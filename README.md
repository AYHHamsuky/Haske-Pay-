# Haske Pay

Electricity tokens (all 12 DisCos), airtime and data, resold through VTpass with Baxi as the backup route. Wallets are funded by Paystack dedicated virtual accounts. English and Hausa throughout.

```
backend/   Laravel 12 API + Inertia/Vue 3 web: public checkout, customer wallet, agent dashboard, admin
mobile/    Flutter customer app (Android first; iOS and web build from the same code)
```

## Run it locally

Everything runs in **sandbox mode** by default (`HASKE_PROVIDER_MODE=fake`): VTpass, Baxi, Paystack and Termii are simulated in-process, so no keys and no real money. Each can be switched to live on its own with `HASKE_BILLS_MODE`, `HASKE_PAYMENTS_MODE` and `HASKE_SMS_MODE`.

```bash
cd backend
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
composer run dev          # web server, queue worker, logs and Vite together
```

Open http://localhost:8000. Sandbox accounts (log in with an SMS code, which is shown on screen in sandbox mode; PIN `1234`):

| Who | Phone | Goes to |
| --- | --- | --- |
| Customer | 08031234567 | `/app` wallet |
| Agent (silver tier) | 08061234567 | `/app`, `/agent/commission`, `/agent/bulk` |
| Admin | 08010000001 | `/admin` (sets up 2FA on first login) |

`/admin/sandbox` is the "Behind the screen" panel from the HTML demo: choose what the provider does next (success, pending, failed, VTpass down), then buy something and watch the ledger.

Run the scheduler in a second terminal so pending purchases get requeried: `php artisan schedule:work`.

**Mobile:**

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_URL=http://10.0.2.2:8000   # Android emulator → your machine
```

**Tests:** `cd backend && php artisan test` (32 tests), and `cd mobile && flutter analyze && flutter test`.

## Connecting VTpass

Docs: https://vtpass.com/documentation/

1. Register a **sandbox** account at https://sandbox.vtpass.com/register.
2. Open **Profile → API Keys**. Set *API authentication type* to **API keys** (or *all*), then click to generate your public and secret keys. **They are shown once**, so copy them straight away.
3. In `backend/.env`:
   ```
   HASKE_BILLS_MODE=live          # real VTpass; Paystack and SMS can stay fake
   VTPASS_BASE_URL=https://sandbox.vtpass.com/api
   VTPASS_API_KEY=...
   VTPASS_PUBLIC_KEY=PK_...
   VTPASS_SECRET_KEY=SK_...
   HASKE_ALERT_PHONES=080XXXXXXXX # gets an SMS if VTpass rejects our keys, IP or balance
   ```
4. Run `php artisan config:clear && php artisan haske:vtpass-check`. It checks the balance (public key), a meter lookup (secret key) and data plans, and buys nothing.
5. Try purchases with the VTpass sandbox test numbers (the forms show them while you're on the sandbox):

   | What | Number | VTpass simulates |
   | --- | --- | --- |
   | Prepaid meter | `1111111111111` | success, with a token |
   | Postpaid meter | `1010101010101` | success |
   | Airtime / data | `08011111111` | success |
   | Airtime / data | `201000000000` | pending: watch the requery job resolve it |
   | Airtime / data | `300000000000` / `400000000000` | timeout / no response |
   | Anything else | | failure: watch the automatic refund |

   Fund the sandbox wallet from the VTpass sandbox dashboard first, or every purchase fails with `018 LOW WALLET BALANCE`.
6. Going live: change `VTPASS_BASE_URL` to `https://vtpass.com/api` and use the keys from your live profile at vtpass.com/account. Ask VTpass to whitelist your server IP if they require it (`027`).

Baxi is only used as a fallback once `BAXI_API_KEY` and `BAXI_AGENT_ID` are set; until then a VTpass outage refunds the customer instead.

## How money moves

```
validate → debit wallet → call VTpass ─┬─ delivered    → token on screen + SMS, agent commission credited
                                       ├─ failed       → full refund to wallet (ledger shows both entries)
                                       ├─ pending/lost → requery queue (20s, 40s, 60s, 2m, 5m…)
                                       └─ unreachable  → same request_id retried on Baxi
```

- **Ledger:** every naira is a row in `ledger_entries`, which are append-only (updates and deletes throw). `wallets.balance` is a cache; `haske:audit-ledger` rebuilds every balance from the ledger nightly and SMSes alert phones on any mismatch.
- **Idempotency:** each client sends an `idempotency_key`, so a double tap returns the same purchase. Ledger references are unique, so a refund, a commission or a Paystack credit can never post twice, even when a webhook is replayed.
- **Pending is never refunded blindly.** Only a definite "failed" from the provider (or proof the request never reached it) triggers a refund. A purchase still pending after 30 minutes is **escalated**: admins get an SMS and a banner, and resolve it in `/admin/transactions` (mark delivered with the token, or refund), with the reason audit-logged.
- **Failover** to Baxi happens only when VTpass could not be reached at all (DNS, connection refused, TLS failure). A timeout after the request was sent means VTpass may have vended, so that goes to requery instead.
- **Money is integer kobo everywhere** (₦1 = 100), in the database, the API and the apps.

## Design and name

The look follows `BillPay_Demo_Updated.html`: navy sidebar and hero, green actions, Inter, and stat cards. Colours live as CSS variables in `backend/resources/css/app.css` (light and dark) and in `mobile/lib/core/theme.dart`. The product name and tagline are in `backend/resources/js/lib/brand.js`; change them there to rename the web app.

## Web checkout (no app)

`/pay` lets anyone buy (the landing page at `/` links to it): verify the meter, pay with Paystack (card, transfer or USSD), and get the token on screen and by SMS. The payment lands in a wallet under the buyer's phone number and is spent on the token straight away. If the vend fails, the money stays in that wallet; the buyer can log in with the same number to retry, or ask support for a bank refund.

## Admin

Dashboard (sales, margin, failure rate, pending queue, ledger check, provider float) · transactions (search, requery, resolve escalations, see provider payloads) · users and wallets (manual credit/debit with a required reason, role and agent tier, suspend, unlock PIN) · services (switch a DisCo off, routes, commission, fees, limits) · reconciliation (upload the day's VTpass CSV, matched on request_id to the naira) · audit log.

## Going live

1. Fill the keys in `.env` and set `HASKE_PROVIDER_MODE=live`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.
2. Use MySQL and Redis (`DB_CONNECTION=mysql`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`). `docker-compose.yml` runs web, queue worker, scheduler, MySQL, Redis and a daily backup on one VPS; put TLS (Caddy or nginx) in front of port 8080.
3. In Paystack, set the webhook URL to `https://YOUR_DOMAIN/api/webhooks/paystack`.
4. Run `php artisan haske:make-admin 080XXXXXXXX --name="Your Name"`; you set up 2FA on first login.
5. Update commission rates in **Admin → Services** from the current VTpass rate card.
6. Before turning on live keys, test against the VTpass sandbox (`VTPASS_BASE_URL=https://sandbox.vtpass.com/api`) and confirm the Baxi service-type names with Baxi; they are stored per service in `services.meta.baxi_service`.

Launch checklist items from the plan and where they live:

| Checklist item | Where |
| --- | --- |
| Nothing stuck pending past 30 min | `haske:requery` (every 20s) + escalation alerts + admin resolve |
| Ledger sum = wallet balances | `haske:audit-ledger`, nightly 01:00 Lagos time |
| Daily reconciliation to the naira | Admin → Reconciliation, or `php artisan haske:reconcile DATE file.csv` |
| Webhook signature-verified, replay-safe | `PaystackWebhookController` (HMAC-SHA512) + unique `funding_events.reference` |
| Secrets in env, HTTPS, admin 2FA | `.env`, `URL::forceScheme` in production, TOTP on every admin session |
| Backups restored once | `backup` service in docker-compose. **Do a test restore yourself.** |
| Support line, legal view, CAC, employer clearance | Not code. These are yours to do. |

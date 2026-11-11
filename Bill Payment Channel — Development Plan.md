# Bill Payment Channel — Development Plan

Oct 28, 2026 · @Abubakar Yahya Hamza

A 16-week build to a live v1: electricity, airtime and data on web, Android and an agent dashboard, resold through the VTpass API.

## Scope and stack

v1 ships electricity for all DisCos plus airtime and data, a transfer-funded wallet, and agent accounts. The WhatsApp bot and direct DisCo integration wait for v2.

| Layer | Choice | Why |
| --- | --- | --- |
| Backend + admin | Laravel 11, MySQL, Redis queues | Your strongest stack; queues handle requery and refunds |
| Customer app | Flutter (Android first, iOS later) | One codebase; most Northern users are on Android |
| Web | Laravel Blade or Vue checkout | Lets people buy tokens without installing |
| Bill rail | VTpass API, with Baxi as fallback | All DisCos through one integration |
| Wallet funding | Paystack dedicated virtual accounts | Bank-transfer top-up avoids card fees |
| SMS / OTP | Termii | Token delivery and login OTP |
| Hosting | VPS with Docker, daily DB backups | Cheap to start, easy to scale |

Team assumption: you plus one Flutter developer, part-time. The timeline stretches if it's only you.

## Transaction flow

&#91;embedded content: purchase flow · success, failure and pending paths\]

The wallet is debited before the VTpass call, so a pending result is never refunded blindly: the requery job keeps checking until VTpass returns a final success or failure.

## Data model

Eight core tables. Every naira movement is a row in `ledger_entries`, so balances can always be rebuilt and audited.

| Table | Key fields | Notes |
| --- | --- | --- |
| users | id, name, phone, role (customer / agent / admin), pin\_hash, status | Phone + OTP login; transaction PIN |
| wallets | user\_id, balance, currency | Balance is a cache of the ledger |
| ledger\_entries | wallet\_id, type (credit / debit), amount, reference, balance\_after | Append-only, never edited |
| virtual\_accounts | user\_id, bank, account\_number, provider\_ref | One per user from Paystack |
| services | code (e.g. kaduna-electric), category, provider, commission\_rate, fee, active | Toggle a DisCo off when its route fails |
| saved\_meters | user\_id, service\_code, meter\_number, meter\_type, customer\_name | From the validation response |
| transactions | user\_id, service\_code, amount, fee, commission, status (pending / success / failed / refunded), provider\_ref, token, request\_payload, response\_payload | Idempotent by request\_id |
| agent\_profiles | user\_id, commission\_tier, business\_name, location | Agents get a discount instead of paying a fee |

## Sprint plan

Eight two-week sprints. The first paid token goes through in Sprint 3, and a closed beta starts in Sprint 6.

**Sprint 1 (weeks 1–2): Foundations**

- [ ] Employer clearance from Kaduna Electric in writing
- [ ] VTpass sandbox account and current API rate card
- [ ] Laravel project, Docker, CI, staging server
- [ ] Users, OTP login, transaction PIN

**Sprint 2 (weeks 3–4): Wallet**

- [ ] Wallets and append-only ledger
- [ ] Paystack dedicated virtual accounts and webhook (signature-verified, idempotent)
- [ ] Admin: users, wallets, manual credit with audit log

**Sprint 3 (weeks 5–6): Electricity**

- [ ] Services table seeded with all DisCos
- [ ] Meter validation, then purchase (prepaid and postpaid)
- [ ] Token on screen and by SMS; saved meters

**Sprint 4 (weeks 7–8): Reliability**

- [ ] Requery job for pending transactions
- [ ] Auto-refund to wallet on failure
- [ ] Baxi fallback route; per-service on/off switch
- [ ] Aggregator low-balance alerts

**Sprint 5 (weeks 9–10): Airtime, data and Flutter app**

- [ ] Airtime and data purchase
- [ ] Flutter app: login, wallet, buy, history (Hausa + English)

**Sprint 6 (weeks 11–12): Agents and closed beta**

- [ ] Agent accounts, discount tiers, commission report, bulk vending
- [ ] Closed beta: 20 users and 5 POS agents in Kaduna

**Sprint 7 (weeks 13–14): Hardening**

- [ ] Fix beta issues; rate limits, daily limits, device binding
- [ ] Reconciliation report: VTpass statement vs your transactions
- [ ] Switch VTpass and Paystack to live keys

**Sprint 8 (weeks 15–16): Launch**

- [ ] Play Store release and public web checkout
- [ ] Recruit the first 50 agents
- [ ] Daily dashboard: sales, failures, margin

## Launch checklist

Go live only when all of these hold:

- [ ] Every transaction ends as success or refunded within 30 minutes; none stuck as pending
- [ ] Ledger sum equals wallet balances, checked by a nightly job
- [ ] Daily reconciliation matches the VTpass statement to the naira
- [ ] Paystack webhook is signature-verified and safe to replay
- [ ] Secrets in env vars, HTTPS everywhere, admin behind 2FA
- [ ] Backups restored successfully at least once
- [ ] Support line (WhatsApp) staffed with a refund process written down
- [ ] Legal view on holding wallet balances; CAC and employer clearance on file

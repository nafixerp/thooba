# Gold Scheme ERP (Laravel 11)

Scheme app-inte admin panel modern Tailwind UI-il + full double-entry accounting
(General Ledger → Trial Balance → Profit & Loss → Balance Sheet) + mobile app REST API.

---

## 1. Setup

```bash
composer install
cp .env.example .env          # already copied as .env
php artisan key:generate

# MySQL database undakku
mysql -u root -p -e "CREATE DATABASE scheme_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# .env-il DB_DATABASE / DB_USERNAME / DB_PASSWORD set cheyyuka

php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Open <http://localhost:8000>

| Role       | Username   | Password       |
|------------|------------|----------------|
| Super Admin| `admin`    | `admin@123`    |
| Accountant | `accounts` | `accounts@123` |

> SQLite venel: `.env`-il `DB_CONNECTION=sqlite`, `DB_DATABASE=/full/path/database/database.sqlite`,
> ennittu `touch database/database.sqlite && php artisan migrate --seed`.

### CSS

`public/css/app.css` **already built** — npm illathe thanne app run cheyyam.
Tailwind class maattiyal mathram:

```bash
npm install
npm run css        # rebuilds public/css/app.css
```

---

## 2. Accounting model

```
accountgbs   (Level 1 · Group Base)      Assets / Liabilities / Income / Expense
     │                                    gbscode PK, nature, bshead, normal_side
     ▼
accountg     (Level 2 · Account Group)   Current Assets, Sundry Debtors, Indirect Expenses…
     │                                    grcode PK, gbscode FK, parent_grcode (self)
     ▼
accountm     (Level 3 · Ledger Master)   Cash, Bank, Customer A, Rent…
     │                                    accode PK ◄── this is what daybook posts against
     │
     ├──────────────► daybook.accode      the ledger being posted
     └──────────────► daybook.opaccode    the opposite (counterparty) ledger

daybookpart  (Voucher HEADER)  sno PK, voucher_type, voucher_no, voucher_date, total
     │  1 : N
     ▼
daybook      (Voucher LINES / Ledger entries)
                slno PK, sno FK, accode FK, opaccode FK,
                amount (signed), drcr, dr_amount, cr_amount
```

Ningal chodicha pole **rendum** undu — `daybookpart` header aayi, `daybook` lines aayi.
Separate `accountd` table illa, karanam `daybook` thanne aa role cheyyunnu (duplicate ozhivakkan).

### Sign convention

| amount | drcr | meaning |
|--------|------|---------|
| `> 0`  | `D`  | Debit   |
| `< 0`  | `C`  | Credit  |

**Rule:** ഒരു voucher-inte `SUM(amount)` എപ്പോഴും `0.00` ആയിരിക്കണം.
`AccountingService::post()` ithu validate cheyyunnu — balance illenkil exception, DB-yil onnum ezhuthilla.

### Example — sales invoice

| accode   | opaccode | amount  |
|----------|----------|---------|
| Customer | Sales    | +1000   |
| Sales    | Customer | −1000   |

### Example — scheme installment received (auto posted)

```
Dr  Cash / Bank            1030.00
    Cr  Customer ledger            1000.00
    Cr  GST Payable                  30.00
```

---

## 3. Report flow

```
daybook
   │
   ├─► Day Book          accounts/reports/daybook            (every ledger line)
   ├─► Journal Register  accounts/reports/journal-register   (vouchers, both sides together)
   ├─► General Ledger    accounts/reports/general-ledger     (running balance, per accode)
   ├─► Cash Book         accounts/reports/cash-book
   ├─► Bank Book         accounts/reports/bank-book
   ├─► Cash Flow         accounts/reports/cash-flow          (day-wise in / out, with reasons)
   ├─► Receipts & Pmts   accounts/reports/receipts-payments  (cash+bank vouchers, T-format)
   │
   ├─► Trial Balance     accounts/reports/trial-balance      Opening + Dr − Cr = Closing
   │        │            accounts/reports/trial-balance-group  (rolled up per account group)
   │        │            accounts/reports/group-summary        (ledger-wise inside one group)
   │        │
   │        ├─► Trading, P&L A/c   accounts/reports/trading-profit-loss
   │        │        │
   │        │        ├─► Trading A/c   (tplpos 1)  + Opening / Closing Stock
   │        │        │        └─► Gross Profit c/d ──┐
   │        │        ├─► Trading A/c II (tplpos 2)   │
   │        │        │        └─► Gross Profit c/d ──┤
   │        │        └─► Profit & Loss A/c (tplpos 0)◄┘ Gross Profit b/d
   │        │                 └─► Net Profit / Loss
   │        │                          │
   │        ├─► Profit & Loss  accounts/reports/profit-loss   (flat income vs expense)
   │        │                          │
   │        └─► Balance Sheet  accounts/reports/balance-sheet  (bshead = BS + net P&L)
   │                 └─► Schedules     accounts/reports/balance-sheet-schedules
   │
   ├─► Outstanding       accounts/reports/outstanding         (party-wise Dr/Cr)
   └─► Receivable/Payable accounts/reports/receivable-payable (split by side)
```

Ella report-inum date filter, CSV export, print stylesheet undu.

### Trading, Profit & Loss A/c

Ported from the legacy **dua soft** (`D:\dua soft`, PowerBuilder `kuri`) books,
which do not print a flat income-versus-expense statement. They print one
vertical document made of up to three T accounts, each closing into the next.

`accountm.tplpos` says which section a P&L ledger belongs to:

| tplpos | Section | Closes into |
|--------|---------|-------------|
| `1` | Trading A/c | Gross Profit c/d |
| `2` | Trading A/c II | Gross Profit c/d |
| `0` | Profit & Loss A/c | Net Profit / Loss |

Two trading sections exist because the old KGST/VAT books kept taxable and
non-taxable goods apart; section II stays hidden until a ledger is put in it.
Out of the box Direct Income and Direct Expenses carry `tplpos = 1`, everything
else `0`. Change it per ledger from **Accounts → Ledgers**.

dua soft keeps its running figures in `accountm.amount` and in the reserved
ledgers `OPSTOCK` / `CLSTOCK` / `GPROFITN` / `GPROFITT` / `GPROFIT` / `PROFIT`,
rewriting them on every run. Nothing ever posts to those, so
`App\Services\TradingAccountService` **computes** the same lines instead of
storing them — the Trial Balance then stays purely daybook-driven and cannot
drift out of step with the statement.

> The legacy sign convention is the opposite of this project's: there
> `amount < 0` is a debit. The structure is ported, the sign convention is not.

### Closing stock

dua soft works the stock figure out from its inventory tables
(`items` / `salesd` / `purchased`). This project has no inventory module, so the
figure is **entered per date** under **Accounts → Closing Stock** — the legacy
`Manualstkvalue` mode. Reports take the most recent valuation on or before their
end date:

- **Trading A/c** — Opening Stock on the debit side (the stock ledger's book
  value), Closing Stock on the credit side.
- **Balance Sheet** — the same figure appears under Stock in Hand as
  *Closing Stock (as valued)*, so assets still equal liabilities plus the net
  profit the statement produced.

With nothing entered the two are equal, so the statements read exactly as they
did before anyone started valuing stock.

### Sundry Dr / Cr auto-correct

`accountg.flip_grcode` ports the legacy `SunDbCrAutoCorrect` switch: a Sundry
Debtor who has overpaid is money the shop holds, so the Balance Sheet reports
them under Sundry Creditors instead of as a negative asset — and the reverse for
a creditor in debit. Turn it off with `SUNDRY_AUTO_CORRECT=false`.

### Verified

The engine was verified against a real posting run (63 vouchers, 180 lines):

- every voucher sums to zero
- global Debit = Credit
- Trial Balance tallies (opening, movement and closing columns)
- P&L net = income − expense
- **Balance Sheet difference = 0.00**
- GST Payable balance = GST collected
- customer deposits sit as a credit (liability)
- unbalanced / unknown-ledger vouchers are rejected
- cancelled vouchers drop out of every report and the books still tally

`tests/Feature/TradingAccountTest.php` covers the Trading / P&L structure:

- direct items land in the Trading A/c, indirect items in the P&L A/c
- gross profit is carried down, and a gross loss carried down on the other side
- every section squares off (total debit = total credit)
- valuing closing stock lifts the gross profit **and** the asset side by the
  same figure, so the Balance Sheet still tallies
- "Upto date" mode picks up ledger opening balances, period mode does not
- an overpaid Sundry Debtor is reported as a creditor
- Cash Flow and Receipts & Payments agree with the cash book
- the group trial balance matches the ledger trial balance
- every accounts screen renders and every CSV export downloads

---

## 4. Chart of accounts (seeded)

| accode | Ledger | Group | Head |
|--------|--------|-------|------|
| 1001 | Cash in Hand | Cash in Hand | BS |
| 1002 | Bank Account | Bank Accounts | BS |
| 1010 | Gold Stock | Stock in Hand | BS |
| 2001 | Scheme Deposit Control | Scheme Customers | BS |
| 2002 | GST Payable | Duties & Taxes | BS |
| 2010 | Capital Account | Capital Account | BS |
| 4001 | Gold Sales | Direct Income | PL |
| 5001 | Agent Commission | Indirect Expenses | PL |
| 5002–5099 | Salary, Rent, Electricity, Ads, Bank charges, Round off | Indirect Expenses | PL |
| `C000001…` | one ledger per customer | Scheme Customers | BS |
| `A000001…` | one ledger per agent | Agents / Sundry Creditors | BS |

Customer/agent ledgers `AccountingService::ledgerForCustomer()` /
`ledgerForAgent()` automatic aayi undakkunnu.

Control accode `.env`-il maattam:

```
ACC_CASH=1001
ACC_BANK=1002
ACC_GST_PAYABLE=2002
ACC_AGENT_COMMISSION=5001
ACC_SALES=4001
```

---

## 5. Auto posting (scheme → accounts)

| Event | Voucher | Entry |
|-------|---------|-------|
| Payment approved | RECEIPT | Dr Cash/Bank · Cr Customer · Cr GST Payable |
| Agent commission paid | PAYMENT | Dr Agent Commission · Cr Cash/Bank |
| Scheme maturity redeemed | SALES | Dr Customer · Cr Gold Sales |
| Manual entry | any | multi-line, from the voucher screen |

Payment `PENDING` aayirikkumpol accounts-il onnum varilla.
Admin **approve** cheyyumpol mathrame daybook-il post aakunnullu, and the
`payments.daybook_sno` column links the receipt to its voucher.

### GST on a collection

**Settings → GST mode** decides what a customer actually hands over for an
installment, and how that splits. `App\Services\GstService` is the only place
that works it out, so the app, the gateway and the books always agree.

| Mode | On a ₹5,000 installment at 3% | Scheme credit | GST Payable |
|------|-------------------------------|---------------|-------------|
| `EXCLUSIVE` | customer pays **₹5,150** | 5,000.00 | 150.00 |
| `INCLUSIVE` | customer pays **₹5,000** | 4,854.37 | 145.63 |
| `NONE` | customer pays **₹5,000** | 5,000.00 | 0.00 |

The rule every mode holds, because it is exactly what the receipt voucher
posts:

```
money actually received  ==  scheme credit + GST

Dr Cash/Bank   total
    Cr Customer        base
    Cr GST Payable     tax
```

Under `INCLUSIVE` the installment is settled by the money received rather than
by the scheme credit — the customer paid the amount they agreed to, the GST
simply came out of it, so the schedule closes instead of sitting at `PARTIAL`
for ever.

A scheme with its own `gst_percent` overrides the shop-wide rate; `NONE`
ignores both.

> **This was a bug.** The online gateway used to be handed the installment
> while the receipt was written for installment + GST, so every card/UPI
> collection debited Cash/Bank with ₹150 that never arrived. `createOrder` now
> charges `GstService::quote()`'s total and `bookPayment` splits the captured
> amount instead of adding tax on top of it. `tests/Feature/GstModeTest.php`
> holds the invariant for all three modes.

---

## 6. Modules

**Scheme** — Customers, Agents, Scheme master, Enrollments (auto installment
schedule), Payments + approval queue, Matured schemes, Expiring installments,
Points & referral, Commission payouts.

**Accounts** — Group base, Account groups, Ledgers, Voucher entry (live
balanced/unbalanced indicator), Day book, General ledger, Cash & bank book,
Trial balance, P&L, Balance sheet, Outstanding.

**Masters** — Country/State/District, Metals & gram rate, Categories, Products,
Offers, Banners, Terms, Team, Custom messages, Banks, Users, Company settings.

**Reports** — Customer report, Scheme report, Collection (day/mode/agent),
Payment pending, Agent pending, Gram summary.

---

## 7. Mobile App API

Base URL `{APP_URL}/api/v1` · Header `Authorization: Bearer {token}`

### Public

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/auth/register` | name, phone, password, referred_by? |
| POST | `/auth/login` | phone, password, fcm_token? |
| GET  | `/home` | banners + live rates + schemes + offers |
| GET  | `/rates` | rate history |
| GET  | `/schemes` | scheme list with maturity value |
| GET  | `/products` · `/categories` | catalogue |
| GET  | `/banks` | payment bank / UPI details |
| GET  | `/content/{TERMS\|PRIVACY\|FAQ\|ABOUT}` | static content |

### Authenticated

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET  | `/auth/me` | profile + wallet + points |
| PUT/POST | `/auth/profile` | update profile (multipart for photo) |
| POST | `/auth/change-password` | |
| POST | `/auth/logout` | revoke token |
| GET  | `/my/schemes` | enrollments with progress |
| GET  | `/my/schemes/{id}` | installment schedule + payments |
| POST | `/my/schemes/join` | scheme_id, installment_amount? |
| GET  | `/my/payments` | payment history |
| POST | `/my/payments` | submit a payment (goes to PENDING) |
| GET  | `/my/wallet` | wallet, gram, gold value at today's rate |
| GET  | `/my/points` | point transactions |
| POST | `/my/points/redeem` | points, note? |
| GET  | `/my/passbook` | **ledger statement straight from daybook** |

Response shape:

```json
{ "success": true, "message": "OK", "data": { } }
```

### Quick test

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -d "phone=9000010000&password=customer@123"

curl http://localhost:8000/api/v1/my/wallet \
  -H "Accept: application/json" -H "Authorization: Bearer <token>"
```

Demo customers: phone `9000010000` … `9000010011`, password `customer@123`.

---

## 8. Key files

```
app/Services/AccountingService.php        double entry posting engine
app/Services/FinancialReportService.php   GL / TB / P&L / BS
app/Services/SchemeService.php            enroll, payment, redeem, points
app/Models/{Accountgbs,Accountg,Accountm,Daybookpart,Daybook}.php
database/seeders/ChartOfAccountsSeeder.php
database/migrations/…_create_account_master_tables.php
database/migrations/…_create_daybook_tables.php
resources/views/layouts/app.blade.php     sidebar + topbar + dark mode
resources/views/accounts/reports/         the four financial reports
config/scheme.php                         control ledgers, voucher types
```

## 9. Scheduler

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Runs `scheme:mark-overdue` daily at 01:00.

---

## 10. Mobile App API (NAFO Flutter app)

The Flutter client lives at `D:\NAFO`. It talks to `/api/v1` with
Sanctum bearer tokens. Two token audiences share one table:

| Guard | Middleware | Model |
|---|---|---|
| customer | `auth:sanctum` | `App\Models\Customer` |
| agent | `auth:agent` | `App\Models\Agent` |

A customer token is rejected on agent routes and vice versa.

### Endpoints added for the app

**Public**

```
GET  /api/v1/app-config          branding, feature flags, gateway key, OTP timings
POST /api/v1/auth/otp/request    issue an OTP  (purpose LOGIN | REGISTER | RESET)
POST /api/v1/auth/otp/verify     OTP login, returns a token
POST /api/v1/auth/otp/register   OTP verified self registration
POST /api/v1/auth/forgot-password OTP verified password reset
POST /api/v1/agent/login         agent sign in (agent_code + password)
POST /api/v1/payment/webhook     Razorpay server callback (HMAC verified)
```

**Customer** (`auth:sanctum`)

```
GET  /api/v1/payment/methods     what to offer: gateway on/off, UPI id, banks
POST /api/v1/payment/order       create a Razorpay order
POST /api/v1/payment/verify      verify the checkout signature, book the payment
POST /api/v1/payment/failed      record an abandoned checkout
```

**Agent** (`auth:agent`)

```
GET  /api/v1/agent/me            POST /api/v1/agent/logout
POST /api/v1/agent/change-password
GET  /api/v1/agent/dashboard     collection + due counters
GET  /api/v1/agent/customers     POST /api/v1/agent/customers
GET  /api/v1/agent/customers/{id}
POST /api/v1/agent/schemes/join  enrol one of their customers
GET  /api/v1/agent/collections   POST /api/v1/agent/collections
GET  /api/v1/agent/due-list      GET  /api/v1/agent/commission
```

### Default passwords

Customers and agents created without a password can sign in once using their
phone number. The first successful login seeds that as the password and sets
`must_change_password`, which the app honours by blocking everything until a
new password is chosen. `POST /auth/change-password` clears the flag.

### OTP

Codes are stored hashed in `otp_codes`, are single use, expire after
`OTP_TTL` seconds, and are throttled per number (`OTP_RESEND_AFTER`,
`OTP_MAX_PER_HOUR`, `OTP_MAX_ATTEMPTS`).

Sending is driver based — `SMS_DRIVER=log|msg91|twilio` (`App\Services\SmsService`).
`log` needs no credentials and writes the message to `storage/logs/laravel.log`.

While `OTP_EXPOSE=true` the code is also returned in the API response so the
app can be tested without an SMS provider. **Set it to false in production.**

> **A wrong digit used to kill the code.** `$table->timestamp('expires_at')`
> looks harmless, but MySQL and MariaDB running with the default
> `explicit_defaults_for_timestamp = OFF` silently give the first non-nullable
> `TIMESTAMP` column in a table
>
> ```sql
> DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
> ```
>
> so **any** update to the row rewrote it. `OtpService::verify()` calls
> `$otp->increment('attempts')` on a wrong code — that UPDATE reset the expiry
> to `now()`, and the customer was then told the OTP sitting in their inbox had
> expired, even though `OTP_MAX_ATTEMPTS` is 5.
>
> `expires_at` and `terms_acceptances.accepted_at` are now `DATETIME`, which
> never gets that rule. `tests/Feature/OtpAndGatewayTest.php` covers the
> behaviour, and one test scans `information_schema` for any other column that
> rewrites itself on update — it only runs when the suite is pointed at MySQL.

### Payment gateway

`App\Services\RazorpayService` talks to the Razorpay REST API directly — no
composer SDK. Orders are tracked in `payment_orders`; a payment is only booked
after the HMAC-SHA256 signature over `{order_id}|{payment_id}` verifies.

#### Why it might be off

`RazorpayService::status()` is the single answer, and `/payment/methods` passes
its `disabled_reason` to the app so the screen can say something the shop can
act on rather than "temporarily unavailable". The string never contains a key.

| `RAZORPAY_KEY_ID` | `RAZORPAY_LIVE_MODE` | Result |
|---|---|---|
| blank | any | off — *"Razorpay keys are not set."* |
| `rzp_test_…` | `true` | off — *"LIVE_MODE is on but the key is a test key."* |
| `rzp_test_…` | `false` | **on**, test mode |
| `rzp_live_…` | `true` | **on**, live |
| `rzp_live_…` | `false` | on, with a note that live keys are running in test mode |
| anything else | any | off — *"does not look like a Razorpay key."* |

> `RAZORPAY_LIVE_MODE` defaults to `true`, so a shop that dropped in test keys
> to try the flow got a silently disabled gateway and an app that blamed a
> temporary outage. Set `RAZORPAY_LIVE_MODE=false` while testing.

`RAZORPAY_UPI_ONLY` (default **false**) pins the checkout sheet to UPI. Leave
it off unless the shop genuinely accepts nothing else — hiding every other
block saves one tap but strands anyone who cannot pay by UPI on that device,
and iOS does not surface UPI apps the way Android does.

#### Setting the keys

Keys live in `.env` on the server, never in the app. The Flutter app receives
`razorpay_key` (the public key id) in the `/payment/order` response and hands it
straight to the checkout sheet; the **secret is never sent to a client** — it
only signs the order-create call and the signature check on the way back. A
secret shipped in an APK can be extracted from it, and anyone holding it can
refund, capture and read every payment on the account.

```bash
RAZORPAY_KEY_ID=rzp_test_xxxxxxxxxxxx
RAZORPAY_KEY_SECRET=xxxxxxxxxxxxxxxxxxxxxxxx
RAZORPAY_LIVE_MODE=false          # true only with rzp_live_ keys
php artisan config:clear
```

Going live is two edits: swap in the `rzp_live_` pair from Dashboard →
Settings → API Keys, and set `RAZORPAY_LIVE_MODE=true`. Nothing in the app
changes or needs rebuilding — it reads the key from the server each time.

#### The webhook is the safety net

`RAZORPAY_WEBHOOK_SECRET` is blank out of the box, and `POST /payment/webhook`
**rejects every unsigned call with 400** — it fails closed, so an empty secret
means no webhook, not an open door.

Turn it on. The checkout callback only fires if the app is still alive; when
the customer's phone dies, loses signal or is swiped away between paying and
returning, the money reaches the account and nothing in the ERP records it.
The webhook is what closes that gap.

In Dashboard → Settings → Webhooks add `https://<your-host>/schemeerp/api/v1/payment/webhook`,
subscribe to `payment.captured` and `payment.failed`, and copy the secret
Razorpay shows into `RAZORPAY_WEBHOOK_SECRET`. Every call is logged to
`payment_webhook_logs` either way, so a rejected signature is visible rather
than silent.

### Direct UPI — no aggregator

`App\Services\UpiService` builds a `upi://pay` link straight to the shop's own
VPA. The app opens Google Pay / PhonePe / Paytm with it; no Razorpay, no per
transaction fee, and the money lands in the shop account immediately. Offered
alongside the gateway, and the only option when the gateway is off.

```
upi://pay?pa=<vpa>&pn=<payee>&am=<total>&cu=INR&tn=<note>&tr=<order_no>
```

`tr` carries our own order number — that is what the office matches against the
bank statement.

| | |
|---|---|
| `POST /api/v1/payment/upi/intent` | creates the order, returns the links |
| `POST /api/v1/payment/upi/confirm` | what the customer / UPI app reported |

The VPA comes from **Settings → UPI ID for collections**, falling back to
`UPI_VPA` and then to the first active bank's `upi_id`. The payee name comes
from **Settings → UPI payee name**, falling back to the company name.

> **It always lands PENDING, and that is the point.** Nothing in this flow can
> prove the money arrived: the result a UPI app hands back is client side and
> trivially forged, and with no payment service provider there is no signed
> callback to check it against. So a confirmed intent writes a `PENDING`
> payment and a `SUBMITTED` order and stops there — **no voucher, no scheme
> credit, no wallet movement**. It reaches the books only when the office
> approves it under *Payments → Pending*, having seen it on the bank
> statement. That is the same gate an agent's cash collection goes through.
>
> Razorpay does not have this problem — its signature plus the capture check
> take a payment straight to `APPROVED`.

Turn it off entirely with `UPI_DIRECT_ENABLED=false`.

### Bank reconciliation — how a direct UPI payment gets proved

**Accounts → Bank Reconciliation.** This is the answer to "how do we know the
money actually arrived". The statement is the evidence; everything else is a
claim.

```
customer pays by UPI intent      →  payment PENDING, order SUBMITTED
shop exports the statement CSV   →  upload it here
                                 →  every credit matched against the claims
                                 →  approve  →  voucher posts, scheme credited
```

`App\Services\BankReconciliationService` matches three ways:

| Match | What it means | Bulk approve? |
|---|---|---|
| `ORDER_NO` | the narration carries our own order number — the app puts it in the UPI `tr` field | **yes** |
| `UTR` | the narration or reference carries the UTR the customer typed | **yes** |
| `AMOUNT_DATE` | no reference, but exactly one claim has that amount within 3 days | no — a person agrees to it |
| `MANUAL` | linked by hand from the screen | no |

Reference matches are resolved first, so an amount-only guess can never steal a
payment a stronger line would have taken. Two identical claims and one credit
leaves both alone rather than guessing.

The CSV reader skips the account blurb above the header and accepts the column
names the usual HDFC / SBI / ICICI / Axis exports use (`Narration` /
`Description` / `Particulars`, `Deposit Amt.` / `Credit`, and so on), plus the
Indian date formats. Re-uploading an overlapping range is safe — lines are
fingerprinted on date + amounts + narration.

What the screen is really for is the two lists nobody looks at otherwise:

- **Unmatched credits** — money in the bank no customer claimed.
- **Claimed, not in the statement** — a customer says they paid and the bank
  never saw it. Either the statement for those days is not uploaded, or the
  payment did not happen.

> **Worth knowing before choosing between the two.** UPI has zero MDR for
> merchants in India, so an aggregator normally charges **nothing** on a UPI
> transaction. If the reason for going direct was the fee, it may not be a real
> saving — a PSP gives a signed, real-time confirmation that no amount of
> reconciliation can match for speed. Going direct is worth it when the shop
> wants the money in its own account instantly with no onboarding, and is happy
> to reconcile daily. The app reads the flag
from `/payment/methods` rather than hard coding it.

Gateway payments are booked `APPROVED` (the money is already collected);
set `PAYMENT_AUTO_APPROVE_GATEWAY=false` to leave them pending instead.

Before anything is written to the books, `verify` also **asks Razorpay what
actually happened**. A valid signature only proves the order/payment pair came
from Razorpay — it says nothing about whether the money was captured, or how
much of it. So the payment is fetched and rejected unless it is `captured` or
`authorized` and the amount matches the order. A transient failure to reach
Razorpay is *not* treated as a rejection (the signature is still good and the
webhook reconciles), but a definite "not captured" or a different amount is.

The webhook books `payload.payment.entity.amount`, not the order amount, for
the same reason.

The checkout sheet is titled with `Settings → company_name`, so renaming the
shop rebrands the payment screen without an app release.

Configuration lives in `config/mobile.php`; see `.env.example` for every key.

### One-time setup

```bash
php artisan migrate
php artisan storage:link     # required, or uploaded screenshots and banners 404
```

`.htaccess` in the project root forwards the `Authorization` header to PHP.
Without that rule every bearer token is dropped and the API answers
`Unauthenticated` — do not remove it.

### Demo catalogue for the app

The app's first screen shows banners, jewellery and About text from this
database. To populate it:

```bash
php artisan db:seed --class=CatalogueDemoSeeder
```

That adds 5 categories, 4 products, 3 banners and one offer, generating
placeholder artwork with GD so no asset files are needed. Replace each image
from the admin panel with a real photo — the app picks it up immediately.

### Image URLs (important)

API responses build image URLs with `storage_url()` (`app/Support/helpers.php`),
**not** `asset()`. `asset()` pins everything to `APP_URL`
(`http://localhost:8081/schemeerp`), and a phone or Android emulator cannot
resolve `localhost` to the server — every banner, product photo and payment
screenshot silently fell back to a placeholder.

`storage_url()` keeps the path prefix from `APP_URL` but takes the host from the
incoming request, so a client that reached the API on `10.0.2.2:8081` also gets
its images from `10.0.2.2:8081`. Use it for any new image field you expose.

## 11. Clear all data

`Settings → Danger zone → Clear all data` (`POST /admin/settings/reset-data`,
`MasterController::resetData`). Meant for handing a configured install to a real
shop: throw away the trial entries, keep the setup.

Three guards, in order: `admin:superadmin` on the route, the signed-in user's own
password, and the phrase `RESET DATA` typed exactly.

**Cleared** (`MasterController::CLEARED_TABLES`) — customers, agents, schemes
taken by customers, installments, payments, gateway orders and webhook logs,
bank statement lines, the whole daybook, closing-stock entries, login OTPs and
API tokens, and every user except the one running it. The chart of accounts is
truncated and re-seeded, so the control ledgers auto posting needs come back
empty.

**Kept** (`MasterController::KEPT_TABLES`) — the scheme master, banks, metals and
rates, the catalogue, banners, offers, terms, settings, locations, roles and
permissions, branches, commission rules.

### Three things that were wrong here

*Tables nobody added to the list.* `payment_orders`, `bank_statement_lines`,
`otp_codes` and `stock_values` were never cleared. A reset on live data left 3
gateway orders and a statement line pointing at customers and payments that had
just been deleted, plus 9 live OTPs for numbers no longer in the database.

*It only ran on MySQL.* The foreign-key toggle was a raw
`SET FOREIGN_KEY_CHECKS=0`, which is MySQL-only syntax — on SQLite the whole
reset threw a 500. It now goes through `Schema::disableForeignKeyConstraints()`,
which compiles to that identical statement on MySQL and to
`PRAGMA foreign_keys = 0` on SQLite.

*It renamed the shop.* `MasterDataSeeder` ran at the end, and its `Setting::put`
calls overwrite. Every clear handed the shop back the demo identity — company
name `Ishq Jewellery`, receipt prefix `RCT`, GST 3%, three sample schemes, an SBI
account — and re-created `admin@scheme.test` with the demo password. Only
`ChartOfAccountsSeeder` runs now; no master table is truncated, so there was
nothing for the master seeder to restore.

The reset is deliberately **not** wrapped in `DB::transaction()`: in MySQL every
`TRUNCATE` performs an implicit commit, so the framework's commit at the end of
the closure failed with *There is no active transaction* and the reset died half
way through. Take a `mysqldump` first — this is not undoable.

### The guard that keeps it honest

`tests/Feature/ResetDataTest.php` fails when a table is on neither list, naming
it. Add a table to the schema, and the suite tells you to decide whether a data
reset clears it or keeps it — that is what stops the next table quietly
surviving a reset with rows pointing at records that are gone.

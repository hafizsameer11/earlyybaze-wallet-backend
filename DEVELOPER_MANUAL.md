## EarlyBaze Wallet Backend – Developer Manual

### 1. Project Overview

**Purpose**: Backend for a multi-asset wallet (crypto + NGN) that supports:

- **User features**: registration, login, OTP, KYC, support, notifications.
- **Wallet features**:
  - Crypto balances in `virtual_accounts` per currency/network.
  - Fiat (NGN) balance in `user_accounts`.
- **Transaction flows**:
  - On‑chain deposits (incoming blockchain webhooks).
  - Internal user‑to‑user transfers.
  - On‑chain withdrawals (to external addresses).
  - Buying crypto via NGN bank transfer.
  - NGN withdrawals to bank accounts.
- **Admin features**: user management, transaction reports, AML rules, trade limits, database backups, newsletters, roles/modules.

**Tech stack**

- **Framework**: Laravel (PHP)
- **Auth**: Laravel Sanctum (`auth:sanctum` middleware)
- **Queue**: Laravel jobs (e.g. `ProcessBlockchainWebhook`)
- **DB**: MySQL/PostgreSQL via Eloquent
- **Config**: `.env` + `config/*` (e.g. `config/database.php`, `config/tatum.php`)

---

### 2. Running the Project

#### 2.1. Environment setup

Requirements:

- PHP (version compatible with `composer.json`)
- Composer
- MySQL/PostgreSQL

From the repo root:

```bash
composer install
cp .env.example .env  # if present
php artisan key:generate
```

Update `.env`:

- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Any provider keys (e.g. Tatum, push notifications) as needed.

#### 2.2. Database migrations

Standard:

```bash
php artisan migrate
```

Development helper route (do **not** expose in production):

- `GET /api/migrate` → runs `php artisan migrate` (see `routes/api.php`).

#### 2.3. Run the app and queues

HTTP server:

```bash
php artisan serve
```

Queue worker (for jobs like `ProcessBlockchainWebhook`):

```bash
php artisan queue:work
```

API base URL is usually `http://127.0.0.1:8000/api`.

---

### 3. High‑Level Architecture

#### 3.1. Routing

- Main API routes: `routes/api.php`
  - Customer/auth routes (`/auth`, `/user/*`, `/wallet/*`, `/withdraw/*`, `/support/*`, `/exchange-rate/*`, etc.).
  - Webhook route: `POST /webhook` → `WebhookController@webhook`.
  - Admin routes under:
    - `Route::middleware('auth:sanctum')` + `Route::prefix('admin')->middleware(['admin'])`:
      - User management, KYC, AML rules, trade limits, reports, newsletters, assets, transaction management, database backup, etc.

#### 3.2. Controllers (selected)

- `app/Http/Controllers/Wallet/UserController.php`
  - User profile, balance, assets, wallet currencies, deposit addresses, FCM tokens, user activity.
- `app/Http/Controllers/Wallet/TransactionController.php`
  - Internal transfers, on‑chain transfers, swaps, buys, transaction listings.
- `app/Http/Controllers/WithdrawController.php`
  - Create and fetch withdraw requests for authenticated users.
- `app/Http/Controllers/WebhookController.php`
  - Entry point for blockchain webhooks.
- `app/Http/Controllers/Admin/*`
  - `UserManagementController`, `TransactionManagementController`, `WalletManagementController`, `AmlRuleController`, `TradeLimitController`, `DatabaseBackupController`, `NewsletterController`, `AssetController`, etc.

#### 3.3. Services and Repositories (wallet domain)

- `app/Repositories/transactionRepository.php`
  - Admin/global view of `transactions` (filtering, statistics, aggregation).
  - Per‑user transaction summaries.
- `app/Repositories/TransactionSendRepository.php`
  - Internal transfers and on‑chain send logic.
- `app/Repositories/BuyTransactionRepository.php`
  - “Buy crypto with NGN” creation and admin approval logic (crediting crypto balance).
- `app/Repositories/WithdrawRequestRepository.php`
  - NGN withdraw request creation and status updates (approve/reject/refunds).
- `app/Jobs/ProcessBlockchainWebhook.php`
  - Processes on‑chain deposit webhooks and credits user balances.

Other important services:

- `app/Services/*` – blockchain services (Ethereum/BSC/Bitcoin/Litecoin/Solana/Tron), notification service, user services, exchange rate service, etc.

---

### 4. Core Data Model

#### 4.1. Users and accounts

- **User**
  - File: `app/Models/User.php`
  - Authentication, profile, notification tokens, etc.

- **UserAccount**
  - Table: `user_accounts`
  - Fields of interest:
    - `user_id`
    - `naira_balance` – NGN fiat wallet.

- **VirtualAccount**
  - Table: `virtual_accounts`
  - Represents a crypto wallet *per user, currency, and blockchain*.
  - Key fields:
    - `user_id`
    - `currency` (e.g. `BTC`, `USDT`)
    - `blockchain` (e.g. `ethereum`, `bsc`, `tron`)
    - `account_id` (provider’s virtual account id)
    - `available_balance`
    - `account_balance`

#### 4.2. Transactions

- **Transaction**
  - Table: `transactions`
  - Generic ledger row used by all flows.
  - Key fields:
    - `id`
    - `user_id`
    - `type` (`send`, `receive`, `buy`, `swap`, `withdraw`, etc.)
    - `amount`, `amount_usd`
    - `currency`
    - `network`
    - `status`
    - `reference`
    - `transfer_type` (`internal`, `external`, etc. when used).

- **TransactionSend**
  - Table: `transaction_sends`
  - Details about sends:
    - `transaction_type` (`internal`, `on_chain`, `external`)
    - `sender_virtual_account_id`
    - `receiver_virtual_account_id`
    - `sender_address`
    - `receiver_address`
    - `amount`, `currency`, `tx_id`, `gas_fee`, `blockchain`, `status`, `amount_usd`, etc.

- **ReceiveTransaction**
  - Table: `receive_transactions`
  - Central table for all *incoming* funds (internal + on‑chain).
  - Fields:
    - `user_id`
    - `virtual_account_id`
    - `transaction_id` (foreign to `transactions`)
    - `transaction_type` (`internal`, `on_chain`)
    - `sender_address`
    - `reference`
    - `tx_id`
    - `amount`, `currency`, `blockchain`, `amount_usd`, `status`.

- **ReceivedAsset**
  - Table: `received_assets`
  - Raw view of on‑chain incoming events per webhook (account id, tx id, from/to, amount, timestamp, status).

- **BuyTransaction**
  - Table: `buy_transactions`
  - Data for “buy crypto” flows: amount coin, amount NGN, user, bank account, receipt path, status.

- **WithdrawRequest**
  - Table: `withdraw_requests`
  - User NGN withdrawal requests:
    - `user_id`
    - `amount`
    - `total` (amount + fee)
    - bank details or `bank_account_id`
    - `status` (`pending`, `approved`, `rejected`)
    - `balance_before`, `send_account`, etc.

There are additional models for KYC, referrals, banners, AML rules, etc., but the above are the critical wallet models.

---

### 5. Main Business Flows (Technical)

#### 5.1. On‑chain deposit (incoming funds)

**HTTP entry**

- `POST /api/webhook` → `WebhookController@webhook` (queues `ProcessBlockchainWebhook` job).

**Processing job**

- File: `app/Jobs/ProcessBlockchainWebhook.php`
- Steps:
  - Check if sender address is a master wallet; if yes, treat as top‑up and skip user credit.
  - Guard against duplicates using:
    - `WebhookResponse` (unique reference).
    - `Cache::lock('webhook_lock_'.$reference, 120)` for concurrency.
  - Find `VirtualAccount` by `account_id = $data['accountId']` and load `user`.
  - Inside `DB::transaction()`:
    - Lock the `VirtualAccount` row with `lockForUpdate()`.
    - Update `available_balance += amount` with BCMath.
    - Compute USD equivalent using `ExchangeFeeHelper`.
    - Insert into `webhook_responses`.
    - Insert into `received_assets`.
    - Insert generic `Transaction` row with `type='receive'`.
    - Insert `ReceiveTransaction` row linking the above.
  - Notify the user via `NotificationService`.

**DB points for investigation**

- `receive_transactions` – authoritative view of received funds.
- `transactions` – rows with `type='receive'`.
- `virtual_accounts` – final user balances.
- `received_assets` / `webhook_responses` – raw event data.

---

#### 5.2. Internal transfer (user → user inside platform)

**HTTP entry**

- `POST /api/wallet/internal-transfer`
- Controller: `Wallet\TransactionController@sendInternalTransaction`

**Controller behavior**

- Validates via `InternalTransferRequest`.
- Detects whether `email` is internal:
  - Internal → `TransactionSendService::sendInternalTransaction`.
  - External (address) → uses blockchain services and creates external send.

**Core logic**

- File: `app/Repositories/TransactionSendRepository.php::sendInternalTransaction`
- Steps (inside `DB::transaction()`):
  - Determine `currency` and `network` from `WalletCurrency`.
  - Resolve `sender` = `Auth::user()` and `receiver` by email; reject if same.
  - Lock both `VirtualAccount` rows for sender and receiver (`lockForUpdate()`).
  - Check sender `available_balance` with `bccomp`.
  - Debit sender balances using `bcsub`.
  - Credit receiver balances using `bcadd`.
  - Compute `amount_usd` via `ExchangeRateService`.
  - Generate random `reference`.
  - Create generic `Transaction`:
    - Sender: `type='send'`.
    - Receiver: `type='receive'`.
  - Create `TransactionSend` with `transaction_type='internal'`.
  - Create `ReceiveTransaction` with `transaction_type='internal'`.
  - Send notifications via `NotificationService` and `UserNotification`.

**DB inspection**

- Sender:
  - `transactions` where `user_id = sender_id` and `type='send'`.
  - `transaction_sends` where `transaction_type='internal'` and `user_id = sender_id`.
- Receiver:
  - `transactions` where `user_id = receiver_id` and `type='receive'`.
  - `receive_transactions` where `transaction_type='internal'` and `user_id = receiver_id`.

---

#### 5.3. On‑chain send (user → external address)

**HTTP entry**

- `POST /api/wallet/on-chain-transfer`
- Controller: `Wallet\TransactionController@sendOnChain`

**Core logic**

- File: `app/Repositories/TransactionSendRepository.php::sendOnChainTransaction`
- Steps (inside `DB::transaction()`):
  - Resolve `sender` = `Auth::user()`.
  - Lock `VirtualAccount` row for `(user, currency, blockchain)` with `lockForUpdate()`.
  - Locate `DepositAddress` for this virtual account (sender on‑chain address).
  - Use `TatumService` to estimate gas (gasLimit, gasPrice) and compute `gasFee`.
  - Ensure `available_balance >= amount + gasFee` (BCMath).
  - Call `TatumService::sendBlockchainTransaction` to broadcast the chain transaction.
  - Create `TransactionSend` row with `transaction_type='on_chain'`, `tx_id`, `gas_fee`, etc.
  - Deduct `amount + gasFee` from `available_balance` and `account_balance`.

**DB inspection**

- `transaction_sends` with `transaction_type='on_chain'`.
- `virtual_accounts` for updated balances.
- External transaction hash in `tx_id`.

---

#### 5.4. Buy crypto with NGN

**HTTP entry**

- `POST /api/wallet/buy`
- Controller: `Wallet\TransactionController@buy`

**Core logic**

- File: `app/Repositories/BuyTransactionRepository.php`
  - `create($data)`:
    - Generate reference `EarlyBaze<timestamp>`.
    - Create generic `Transaction` of `type='buy'`, `status='pending'`.
    - Create `BuyTransaction` row with link to `Transaction` and bank account.
  - Admin approval (`update($id, $data)`):
    - Lock `BuyTransaction` with `lockForUpdate()`.
    - If already approved, skip to avoid double credit.
    - When `status='approved'`:
      - Lock user `VirtualAccount` (`user_id` + `currency`) with `lockForUpdate()`.
      - Credit `amount_coin` to `available_balance` and `account_balance` (BCMath).

**DB inspection**

- `buy_transactions` – buy requests and statuses.
- `transactions` where `type='buy'`.
- `virtual_accounts` – crypto balances after approval.

---

#### 5.5. NGN withdrawal

**HTTP entry**

- User routes:
  - `POST /api/withdraw/create`
  - `GET /api/withdraw-request-status/{id}`
  - `GET /api/withdraw-requests`
- Admin route:
  - `POST /api/admin/withdrawRequest/withdraw/update-status/{id}`

**Core logic**

- File: `app/Repositories/WithdrawRequestRepository.php`
  - `create($data)`:
    - Lock `UserAccount` row with `lockForUpdate()`.
    - Validate `naira_balance >= total`.
    - Save `balance_before`.
    - Create `WithdrawRequest`.
    - Deduct `total` from `naira_balance` via BCMath.
  - `updateStatus($id, $data)`:
    - Lock `WithdrawRequest` row.
    - For `status='approved'`:
      - Mark approved.
      - Call `WithdrawTransactionRepository::create(...)` to record the payout.
      - Create `UserNotification` of type `withdraw_approved`.
    - For `status='rejected'`:
      - Mark rejected.
      - Lock `UserAccount` with `lockForUpdate()`.
      - Refund `total` to `naira_balance` via BCMath.
      - Create `WithdrawTransaction` record.
      - Create `UserNotification` of type `withdraw_rejected`.

**DB inspection**

- `withdraw_requests` – status, amount, and bank info.
- `user_accounts` – `naira_balance` debits and credits.
- Withdraw transaction table/model used by `WithdrawTransactionRepository`.

---

#### 5.6. User summaries via API

- `GET /api/user/balance`
  - Returns NGN balance from `UserAccount`.
- `GET /api/user/assets`
  - High‑level asset summary via `UserService`.
- `GET /api/user-asset-transaction`
  - Implemented in `BuyTransactionRepository::getUserAssetTransactions($userId)`:
  - Returns:
    - `assets`: virtual accounts with balance and metadata.
    - `transactions`: merged list of recent `transactions` + `withdraw_requests`.

---

### 6. Concurrency and Precision

- All balance‑affecting flows use:
  - `DB::transaction()` for atomic multi‑row changes.
  - `->lockForUpdate()` on `VirtualAccount` and `UserAccount` to avoid race conditions.
  - BCMath (`bcadd`, `bcsub`, `bccomp`) with scale 8 for safe decimal arithmetic.

When adding new flows that touch money:

- Always wrap logic in a database transaction.
- Always lock the relevant rows.
- Always use BCMath, not regular floats.

---

### 7. Practical Debug Playbook

#### 7.1. Find a specific incoming deposit

- Given `tx_id` or `reference`:
  - `SELECT * FROM receive_transactions WHERE tx_id = ? OR reference = ?;`
  - Join to `transactions`:
    - `SELECT t.* FROM transactions t JOIN receive_transactions rt ON t.id = rt.transaction_id WHERE rt.tx_id = ?;`
  - Check user wallet:
    - `SELECT * FROM virtual_accounts WHERE id = <virtual_account_id>;`
  - Verify raw webhook:
    - `SELECT * FROM received_assets WHERE tx_id = ?;`

#### 7.2. Check a user’s full history

- Via API:
  - `GET /api/transaction/get-all` (for authenticated user).
  - Admin: `GET /api/admin/transactions/get-for-user/{id}`.
- Via DB:
  - `SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at;`
  - Inspect linked tables (`transaction_sends`, `receive_transactions`, `buy_transactions`, `withdraw_requests`).

---

### 8. Key Files Index

- **Routing**
  - `routes/api.php`
- **Wallet / user controllers**
  - `app/Http/Controllers/Wallet/UserController.php`
  - `app/Http/Controllers/Wallet/TransactionController.php`
  - `app/Http/Controllers/WithdrawController.php`
- **Webhooks**
  - `app/Http/Controllers/WebhookController.php`
  - `app/Jobs/ProcessBlockchainWebhook.php`
- **Repositories**
  - `app/Repositories/transactionRepository.php`
  - `app/Repositories/TransactionSendRepository.php`
  - `app/Repositories/BuyTransactionRepository.php`
  - `app/Repositories/WithdrawRequestRepository.php`
- **Core models**
  - `app/Models/Transaction.php`
  - `app/Models/TransactionSend.php`
  - `app/Models/ReceiveTransaction.php`
  - `app/Models/ReceivedAsset.php`
  - `app/Models/VirtualAccount.php`
  - `app/Models/UserAccount.php`
  - `app/Models/WithdrawRequest.php`
  - `app/Models/BuyTransaction.php`


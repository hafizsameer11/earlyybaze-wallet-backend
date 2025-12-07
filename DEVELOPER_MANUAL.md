# EarlyBaze Wallet Backend - Developer Manual

## Table of Contents
1. [System Overview](#system-overview)
2. [Technology Stack](#technology-stack)
3. [Architecture](#architecture)
4. [Setup & Installation](#setup--installation)
5. [Project Structure](#project-structure)
6. [Database Schema](#database-schema)
7. [Authentication & Authorization](#authentication--authorization)
8. [Core Features](#core-features)
9. [API Documentation](#api-documentation)
10. [Tatum Integration](#tatum-integration)
11. [Transaction Flows](#transaction-flows)
12. [Jobs & Queues](#jobs--queues)
13. [Error Handling](#error-handling)
14. [Testing](#testing)
15. [Deployment](#deployment)
16. [Troubleshooting](#troubleshooting)

---

## 1. System Overview

### Purpose
EarlyBaze Wallet is a cryptocurrency wallet backend system that enables users to:
- Create and manage cryptocurrency wallets
- Send and receive cryptocurrencies
- Swap between different cryptocurrencies
- Buy cryptocurrencies with fiat (NGN)
- Withdraw to bank accounts
- Manage KYC verification
- Track transactions and balances

### Key Components
- **User Management**: Registration, authentication, profile management
- **Wallet System**: Virtual accounts, deposit addresses, master wallets
- **Transaction System**: Internal transfers, on-chain transfers, swaps, buys
- **Tatum Integration**: Blockchain operations via Tatum API
- **Admin Panel**: User management, transaction monitoring, system configuration
- **KYC System**: Know Your Customer verification
- **Support System**: Ticket management
- **Referral System**: User referrals and earnings

---

## 2. Technology Stack

### Backend Framework
- **Laravel 10.x** - PHP Framework
- **PHP 8.1+** - Programming Language

### Key Dependencies
- **Laravel Sanctum** - API Authentication
- **Guzzle HTTP** - HTTP Client for API calls
- **Twilio SDK** - SMS/WhatsApp notifications
- **Google API Client** - Google services integration
- **Bacon QR Code** - QR code generation
- **PragmaRX Google2FA** - Two-factor authentication

### Database
- **MySQL/MariaDB** - Primary database

### External Services
- **Tatum API** - Blockchain infrastructure
- **Twilio** - SMS/WhatsApp messaging
- **Email Service** - SMTP for email notifications

---

## 3. Architecture

### Architecture Pattern
The system follows **Repository-Service-Controller** pattern:

```
Controller → Service → Repository → Model → Database
```

### Flow Example
```
HTTP Request
  ↓
Route (routes/api.php)
  ↓
Controller (app/Http/Controllers/)
  ↓
Service (app/Services/)
  ↓
Repository (app/Repositories/)
  ↓
Model (app/Models/)
  ↓
Database
```

### Key Design Patterns
- **Repository Pattern**: Data access abstraction
- **Service Layer**: Business logic encapsulation
- **Job Queue**: Asynchronous processing
- **Middleware**: Request filtering and authentication

---

## 4. Setup & Installation

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL/MariaDB
- Node.js & NPM (for frontend assets if needed)

### Installation Steps

1. **Clone Repository**
```bash
git clone <repository-url>
cd earlyybaze-wallet-backend
```

2. **Install Dependencies**
```bash
composer install
```

3. **Environment Configuration**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure .env File**
```env
APP_NAME="EarlyBaze Wallet"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=earlybaze_wallet
DB_USERNAME=your_username
DB_PASSWORD=your_password

TATUM_API_KEY=your_tatum_api_key
TATUM_BASE_URL=https://api.tatum.io/v3
TATUM_WEBHOOK_URL=https://yourdomain.com/api/webhook

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@earlybaze.com
MAIL_FROM_NAME="${APP_NAME}"

QUEUE_CONNECTION=database
```

5. **Run Migrations**
```bash
php artisan migrate
```

6. **Seed Database (Optional)**
```bash
php artisan db:seed
```

7. **Create Storage Link**
```bash
php artisan storage:link
```

8. **Start Queue Worker**
```bash
php artisan queue:work
```

9. **Start Development Server**
```bash
php artisan serve
```

---

## 5. Project Structure

```
earlyybaze-wallet-backend/
├── app/
│   ├── Console/
│   │   └── Commands/          # Artisan commands
│   ├── Exceptions/
│   │   └── Handler.php        # Exception handling
│   ├── Helpers/               # Helper functions
│   ├── Http/
│   │   ├── Controllers/       # Request handlers
│   │   ├── Middleware/        # Request middleware
│   │   └── Requests/         # Form validation
│   ├── Jobs/                  # Queue jobs
│   ├── Mail/                  # Email templates
│   ├── Models/                # Eloquent models
│   ├── Providers/             # Service providers
│   ├── Repositories/          # Data access layer
│   └── Services/              # Business logic
├── config/                    # Configuration files
├── database/
│   ├── migrations/            # Database migrations
│   └── seeders/               # Database seeders
├── public/                    # Public assets
├── resources/
│   └── views/                 # Blade templates
├── routes/
│   └── api.php                # API routes
├── storage/                   # Storage files
├── tests/                     # Test files
├── .env                       # Environment variables
└── composer.json              # PHP dependencies
```

### Key Directories Explained

**app/Http/Controllers/**
- `Wallet/` - User-facing controllers
- `Admin/` - Admin panel controllers
- `Api/` - General API controllers

**app/Services/**
- Business logic layer
- Handles complex operations
- Communicates with repositories

**app/Repositories/**
- Data access abstraction
- Database queries
- Returns models/collections

**app/Jobs/**
- Asynchronous tasks
- Background processing
- Queue-based execution

---

## 6. Database Schema

### Core Tables

#### users
- User accounts and authentication
- Fields: id, name, email, password, phone, role, otp_verified, etc.

#### virtual_accounts
- Tatum virtual accounts per user/currency
- Fields: user_id, account_id, currency, blockchain, balance, etc.

#### master_wallets
- Master wallets per blockchain
- Fields: blockchain, xpub, address, private_key (encrypted), mnemonic (encrypted)

#### deposit_addresses
- Deposit addresses for virtual accounts
- Fields: virtual_account_id, address, private_key (encrypted), index

#### transactions
- All transaction records
- Fields: user_id, type, amount, currency, status, network, etc.

#### wallet_currencies
- Supported cryptocurrencies
- Fields: currency, blockchain, symbol, price, etc.

#### webhook_responses
- Tatum webhook logs
- Fields: account_id, amount, tx_id, reference, etc.

### Relationships

```
users (1) ──< (many) virtual_accounts
virtual_accounts (1) ──< (many) deposit_addresses
users (1) ──< (many) transactions
master_wallets (1) ──< (many) deposit_addresses [via blockchain]
```

---

### Complete Database Schema Reference

#### 6.1. master_wallets

**Migration:** `database/migrations/2025_01_28_131225_create_master_wallets_table.php`

```php
Schema::create('master_wallets', function (Blueprint $table) {
    $table->id();
    $table->string('blockchain');        // e.g., bitcoin, ethereum, xrp
    $table->string('xpub')->nullable();  // For EVM/UTXO blockchains
    $table->string('address')->nullable(); // For XRP/XLM or EVM
    $table->string('private_key')->nullable(); // Store securely if required
    $table->string('mnemonic')->nullable(); // Store securely if required
    $table->text('response')->nullable(); // Full Tatum API response
    $table->timestamps();
});
```

**Fields:**
- `id` - Primary key
- `blockchain` - Blockchain name (e.g., ethereum, bitcoin, tron, bsc, litecoin)
- `xpub` - Extended public key (for address derivation on UTXO/EVM chains)
- `address` - Master wallet address (derived at index 0)
- `private_key` - Encrypted private key (stored as string, should be encrypted)
- `mnemonic` - Seed phrase (stored as string, should be encrypted)
- `response` - Full JSON response from Tatum API (for reference)
- `created_at`, `updated_at` - Timestamps

**Purpose:** Stores master wallet for each blockchain used to generate deposit addresses.

**Model:** `app/Models/MasterWallet.php`

---

#### 6.2. virtual_accounts

**Initial Migration:** `database/migrations/2025_01_29_102556_create_virtual_accounts_table.php`  
**Update Migration:** `database/migrations/2025_02_24_163655_update_virtual_account_table.php`

```php
// Initial schema
Schema::create('virtual_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('blockchain');
    $table->string('currency');
    $table->string('customer_id')->nullable();
    $table->string('account_id')->unique();
    $table->string('account_code')->nullable();
    $table->boolean('active')->default(true);
    $table->boolean('frozen')->default(false);
    $table->string('account_balance')->default('0');
    $table->string('available_balance')->default('0');
    $table->string('xpub')->nullable();
    $table->string('accounting_currency')->nullable();
    $table->timestamps();
});

// Update added:
Schema::table('virtual_accounts', function (Blueprint $table) {
    $table->unsignedBigInteger('currency_id')->nullable();
    $table->foreign('currency_id')->references('id')->on('wallet_currencies');
});
```

**Complete Schema:**
- `id` - Primary key
- `user_id` - Foreign key to `users` table (cascade delete)
- `blockchain` - Blockchain name (e.g., Ethereum, Bitcoin, Tron)
- `currency` - Currency code (e.g., BTC, ETH, USDT)
- `customer_id` - Tatum customer ID (nullable)
- `account_id` - Tatum virtual account ID (unique)
- `account_code` - User's account code (nullable)
- `active` - Account active status (default: true)
- `frozen` - Account frozen status (default: false)
- `account_balance` - Total account balance from Tatum (string, default: '0')
- `available_balance` - Available balance from Tatum (string, default: '0')
- `xpub` - Extended public key (nullable, currently not used)
- `accounting_currency` - Accounting currency (e.g., USD, EUR)
- `currency_id` - Foreign key to `wallet_currencies` table
- `created_at`, `updated_at` - Timestamps

**Relationships:**
- `belongsTo(User::class)` - One user has many virtual accounts
- `belongsTo(WalletCurrency::class, 'currency_id')` - Links to wallet currency
- `hasMany(DepositAddress::class)` - One virtual account has many deposit addresses

**Purpose:** Stores Tatum virtual accounts for each user and currency combination.

**Model:** `app/Models/VirtualAccount.php`

---

#### 6.3. deposit_addresses

**Initial Migration:** `database/migrations/2025_01_29_103141_create_deposit_addresses_table.php`  
**Updates:** 
- `database/migrations/2025_03_28_174839_update_depositaddress_tablke.php` - Added index and private_key
- `database/migrations/2025_03_28_191627_update_depositaddress_tablke.php` - Changed types

```php
// Initial schema
Schema::create('deposit_addresses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('virtual_account_id')->constrained()->onDelete('cascade');
    $table->string('blockchain')->nullable();
    $table->string('currency')->nullable();
    $table->string('address')->unique();
    $table->timestamps();
});

// Updates added:
$table->integer('index')->nullable();        // Derivation index from master wallet
$table->text('private_key')->nullable();    // Encrypted private key for this address
```

**Final Schema:**
- `id` - Primary key
- `virtual_account_id` - Foreign key to `virtual_accounts` (cascade delete)
- `blockchain` - Blockchain name (nullable)
- `currency` - Currency code (nullable)
- `address` - Deposit address (unique)
- `index` - Derivation index from master wallet (integer, nullable)
- `private_key` - Encrypted private key (text, nullable)
- `created_at`, `updated_at` - Timestamps

**Purpose:** Stores deposit addresses generated from master wallet, linked to virtual accounts.

**Model:** `app/Models/DepositAddress.php`

**Security Note:** Private keys are encrypted using Laravel's `Crypt::encryptString()` before storage.

---

#### 6.4. webhook_responses

**Migration:** `database/migrations/2025_02_27_205918_create_webhook_responses_table.php`

```php
Schema::create('webhook_responses', function (Blueprint $table) {
    $table->id();
    $table->string('account_id')->nullable();
    $table->string('subscription_type')->nullable();
    $table->decimal('amount', 20, 8)->nullable();
    $table->string('reference')->nullable();
    $table->string('currency')->nullable();
    $table->string('tx_id')->nullable();
    $table->bigInteger('block_height')->nullable();
    $table->string('block_hash')->nullable();
    $table->string('from_address')->nullable();
    $table->string('to_address')->nullable();
    $table->timestamp('transaction_date')->nullable();
    $table->integer('index')->nullable();
    $table->timestamps();
});
```

**Fields:**
- `id` - Primary key
- `account_id` - Tatum virtual account ID
- `subscription_type` - Webhook subscription type
- `amount` - Transaction amount (decimal 20,8)
- `reference` - Unique transaction reference
- `currency` - Currency code
- `tx_id` - Blockchain transaction hash
- `block_height` - Block number
- `block_hash` - Block hash
- `from_address` - Sender address
- `to_address` - Receiver address
- `transaction_date` - Transaction timestamp
- `index` - Transaction index in block
- `created_at`, `updated_at` - Timestamps

**Purpose:** Logs all webhook events received from Tatum for audit and debugging.

**Model:** `app/Models/WebhookResponse.php`

---

#### 6.5. received_assets

**Migration:** `database/migrations/2025_04_28_161237_create_received_assets_table.php`

```php
Schema::create('received_assets', function (Blueprint $table) {
    $table->id();
    $table->string('account_id')->nullable();
    $table->string('subscription_type')->nullable();
    $table->decimal('amount', 20, 8)->nullable();
    $table->string('reference')->nullable();
    $table->string('currency')->nullable();
    $table->string('tx_id')->nullable();
    $table->string('from_address')->nullable();
    $table->string('to_address')->nullable();
    $table->timestamp('transaction_date')->nullable();
    $table->string('status')->default('inWallet');
    $table->integer('index')->nullable();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->foreign('user_id')->references('id')->on('users');
    $table->timestamps();
});
```

**Fields:**
- `id` - Primary key
- `account_id` - Tatum account ID
- `subscription_type` - Webhook subscription type
- `amount` - Asset amount (decimal 20,8)
- `reference` - Transaction reference
- `currency` - Currency code
- `tx_id` - Transaction hash
- `from_address` - Sender address
- `to_address` - Receiver address
- `transaction_date` - Transaction timestamp
- `status` - Asset status (default: 'inWallet')
- `index` - Transaction index
- `user_id` - Foreign key to users table
- `created_at`, `updated_at` - Timestamps

**Purpose:** Tracks received assets from webhooks, with status field to track asset state.

**Model:** `app/Models/ReceivedAsset.php`

---

#### 6.6. receive_transactions

**Migration:** `database/migrations/2025_03_29_180239_create_receive_transactions_table.php`

```php
Schema::create('receive_transactions', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->unsignedBigInteger('virtual_account_id')->nullable();
    $table->unsignedBigInteger('transaction_id')->nullable();
    $table->foreign('user_id')->references('id')->on('users');
    $table->foreign('virtual_account_id')->references('id')->on('virtual_accounts');
    $table->foreign('transaction_id')->references('id')->on('transactions');
    $table->string('transaction_type'); // 'internal' or 'on_chain'
    $table->string('sender_address')->nullable();
    $table->string('reference')->nullable()->unique();
    $table->text('tx_id')->nullable()->unique();
    $table->double('amount')->nullable();
    $table->string('currency')->nullable();
    $table->string('blockchain')->nullable();
    $table->double('amount_usd')->nullable();
    $table->string('status')->default('pending');
    $table->timestamps();
});
```

**Fields:**
- `id` - Primary key
- `user_id` - Foreign key to users table
- `virtual_account_id` - Foreign key to virtual_accounts table
- `transaction_id` - Foreign key to transactions table
- `transaction_type` - Type: 'internal' or 'on_chain'
- `sender_address` - Sender wallet address
- `reference` - Unique transaction reference
- `tx_id` - Blockchain transaction hash (unique)
- `amount` - Transaction amount
- `currency` - Currency code
- `blockchain` - Blockchain name
- `amount_usd` - Amount in USD
- `status` - Transaction status (default: 'pending')
- `created_at`, `updated_at` - Timestamps

**Purpose:** Links received transactions to users, virtual accounts, and general transactions table.

**Model:** `app/Models/ReceiveTransaction.php`

---

#### 6.7. Related Core Tables

##### users
**Purpose:** User accounts and authentication

**Key Fields:**
- `id` - Primary key (referenced by virtual_accounts.user_id)
- `name` - User's full name
- `email` - User's email (unique)
- `password` - Hashed password
- `phone` - User's phone number
- `role` - User role ('user' or 'admin')
- `otp` - OTP code for verification
- `otp_verified` - Email verification status
- `user_code` - Unique user code
- `kyc_status` - KYC verification status
- `is_freezon` - Account freeze status
- `bvn` - Bank Verification Number
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/User.php`

##### wallet_currencies
**Purpose:** Supported cryptocurrencies

**Key Fields:**
- `id` - Primary key (referenced by virtual_accounts.currency_id)
- `currency` - Currency code (e.g., BTC, ETH, USDT)
- `blockchain` - Blockchain name (e.g., bitcoin, ethereum, tron)
- `symbol` - Currency symbol image path
- `price` - Current price in USD
- `naira_price` - Current price in NGN
- `status` - Currency status (active/inactive)
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/WalletCurrency.php`

##### transactions
**Purpose:** General transaction table

**Key Fields:**
- `id` - Primary key (referenced by receive_transactions.transaction_id)
- `user_id` - Foreign key to users
- `type` - Transaction type (send, receive, buy, swap, withdraw)
- `amount` - Transaction amount
- `currency` - Currency code
- `status` - Transaction status (pending, completed, failed)
- `network` - Blockchain network
- `reference` - Unique transaction reference
- `amount_usd` - Amount in USD
- `transfer_type` - Transfer type (internal, external)
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/Transaction.php`

##### failed_master_transfers
**Purpose:** Failed transfer attempts to master wallet

**Key Fields:**
- `id` - Primary key
- `virtual_account_id` - Foreign key to virtual_accounts
- `webhook_response_id` - Foreign key to webhook_responses
- `reason` - Failure reason
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/FailedMasterTransfer.php`

---

#### 6.8. Additional Important Tables

##### user_accounts
**Purpose:** User account information (Naira wallet)

**Key Fields:**
- `id` - Primary key
- `user_id` - Foreign key to users
- `account_number` - User's account number
- `naira_balance` - NGN balance
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/UserAccount.php`

##### transactions (Related Tables)
- `transaction_sends` - Internal send transactions
- `buy_transactions` - Buy cryptocurrency transactions
- `swap_transactions` - Swap transactions
- `withdraw_transactions` - Withdrawal transactions

##### kyc
**Purpose:** KYC verification documents

**Key Fields:**
- `id` - Primary key
- `user_id` - Foreign key to users
- `first_name`, `last_name` - User names
- `dob` - Date of birth
- `address`, `state` - Address information
- `bvn` - Bank Verification Number
- `document_type` - ID document type
- `document_number` - ID document number
- `picture` - User photo
- `document_front`, `document_back` - ID document images
- `status` - KYC status (pending, approved, rejected)
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/Kyc.php`

##### bank_accounts
**Purpose:** User bank accounts for withdrawals

**Key Fields:**
- `id` - Primary key
- `user_id` - Foreign key to users
- `account_name` - Account holder name
- `account_number` - Bank account number
- `bank_name` - Bank name
- `bank_code` - Bank code
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/BankAccount.php`

##### withdraw_requests
**Purpose:** Withdrawal requests

**Key Fields:**
- `id` - Primary key
- `user_id` - Foreign key to users
- `bank_account_id` - Foreign key to bank_accounts
- `amount` - Withdrawal amount
- `currency` - Currency code
- `status` - Request status (pending, approved, rejected, completed)
- `created_at`, `updated_at` - Timestamps

**Model:** `app/Models/WithdrawRequest.php`

---

#### 6.9. Complete Schema Relationships Diagram

```
users (1)
  ├──< (many) virtual_accounts
  │     └──< (many) deposit_addresses
  │
  ├──< (many) transactions
  │     ├──< (1) transaction_sends
  │     ├──< (1) buy_transactions
  │     ├──< (1) swap_transactions
  │     └──< (1) withdraw_transactions
  │
  ├──< (1) user_accounts
  ├──< (many) receive_transactions
  ├──< (many) received_assets
  ├──< (many) bank_accounts
  ├──< (many) withdraw_requests
  └──< (1) kyc

master_wallets (1)
  └──< (many) deposit_addresses [via blockchain + index]

wallet_currencies (1)
  └──< (many) virtual_accounts

webhook_responses (1)
  └──< (many) failed_master_transfers
```

---

#### 6.10. Database Indexes

**Important Indexes:**
- `virtual_accounts.account_id` - UNIQUE (for fast Tatum account lookup)
- `deposit_addresses.address` - UNIQUE (for address lookup)
- `receive_transactions.reference` - UNIQUE (prevent duplicates)
- `receive_transactions.tx_id` - UNIQUE (prevent duplicate transactions)
- `users.email` - UNIQUE (for authentication)
- `webhook_responses.reference` - For duplicate detection

**Foreign Key Constraints:**
- All foreign keys have `onDelete('cascade')` where appropriate
- Ensures data integrity when parent records are deleted

---

#### 6.11. Data Types & Constraints

**String Fields:**
- Most text fields use `string()` type
- Addresses and hashes stored as `string()`
- Private keys stored as `text()` for longer values

**Numeric Fields:**
- Balances stored as `string()` to preserve precision (avoid floating point errors)
- Amounts in transactions use `double()` or `decimal(20,8)`
- IDs use `unsignedBigInteger()`

**Boolean Fields:**
- `active`, `frozen`, `otp_verified` use `boolean()` with defaults

**Timestamps:**
- All tables have `created_at` and `updated_at` timestamps
- Some tables have additional timestamp fields (e.g., `transaction_date`)

---

#### 6.12. Migration Files Reference

All migration files are located in `database/migrations/`:

**Core Wallet Migrations:**
- `2025_01_28_131225_create_master_wallets_table.php`
- `2025_01_29_102556_create_virtual_accounts_table.php`
- `2025_01_29_103141_create_deposit_addresses_table.php`
- `2025_02_24_163655_update_virtual_account_table.php`
- `2025_02_27_205918_create_webhook_responses_table.php`
- `2025_03_28_174839_update_depositaddress_tablke.php`
- `2025_03_28_191627_update_depositaddress_tablke.php`
- `2025_03_29_180239_create_receive_transactions_table.php`
- `2025_04_28_161237_create_received_assets_table.php`

**User & Transaction Migrations:**
- User table migrations
- Transaction table migrations
- KYC table migrations
- Bank account migrations
- Withdrawal request migrations

To view all migrations:
```bash
php artisan migrate:status
```

---

## 7. Authentication & Authorization

### Authentication Methods

#### 1. User Authentication (Sanctum)
- Token-based authentication
- Bearer token in Authorization header
- Token obtained via `/api/auth/login` or `/api/auth/otp-verification`

**Flow:**
```
1. User registers → receives OTP via email
2. User verifies OTP → receives token
3. Token used for subsequent requests
```

#### 2. Admin Authentication (2FA)
- Two-factor authentication required
- Step 1: Login with email/password → receive temp token
- Step 2: Verify OTP → receive full admin token

**Flow:**
```
1. POST /api/admin/login
   → Returns temp_token with '2fa:pending' ability
2. POST /api/admin/2fa/verify
   → Returns admin_token with 'admin' and '2fa:passed' abilities
```

### Authorization

#### Middleware
- `auth:sanctum` - Requires authentication
- `admin` - Requires admin role
- `EnsureTwoFactorVerified` - Requires 2FA verification for admins

#### Role-Based Access
- **user** - Regular users
- **admin** - Administrators (requires 2FA)

### Token Abilities
- `2fa:pending` - Temporary token for OTP verification
- `admin` - Admin access
- `2fa:passed` - 2FA verified

---

## 8. Core Features

### 8.1. User Registration & Verification

**Endpoint:** `POST /api/auth/register`

**Flow:**
1. User provides: name, email, password, phone
2. System generates OTP and sends via email
3. User verifies OTP → Virtual accounts created automatically

**Code Location:**
- Controller: `app/Http/Controllers/Wallet/AuthController.php`
- Service: `app/Services/UserService.php`
- Job: `app/Jobs/CreateVirtualAccount.php`

### 8.2. Virtual Account Management

**Creation:**
- Triggered automatically on email verification
- One virtual account per currency per user
- Created via Tatum API

**Code Location:**
- Job: `app/Jobs/CreateVirtualAccount.php`
- Service: `app/Services/TatumService.php`

### 8.3. Deposit Address Generation

**Process:**
1. Virtual account created
2. Deposit address generated from master wallet
3. Address assigned to virtual account via Tatum

**Code Location:**
- Service: `app/Services/WalletAddressService.php`
- Job: `app/Jobs/AssignDepositAddress.php`

### 8.4. Transaction Types

#### Internal Transfer
- Transfer between users within platform
- No blockchain transaction
- Instant processing

#### On-Chain Transfer
- Blockchain transaction
- Requires gas fees
- Asynchronous processing

#### Swap
- Exchange one cryptocurrency for another
- Multi-step process
- Fee calculation

#### Buy
- Purchase cryptocurrency with NGN
- Bank transfer required
- Admin approval needed

#### Withdraw
- Withdraw to bank account
- Admin approval required
- Fee calculation

### 8.5. Webhook Processing

**Endpoint:** `POST /api/webhook`

**Flow:**
1. Tatum sends webhook on deposit
2. System queues webhook for processing
3. Job processes webhook:
   - Validates transaction
   - Updates virtual account balance
   - Creates transaction records
   - Sends notification

**Code Location:**
- Controller: `app/Http/Controllers/WebhookController.php`
- Job: `app/Jobs/ProcessBlockchainWebhook.php`

---

## 9. API Documentation

### Base URL
```
Production: https://yourdomain.com/api
Development: http://localhost:8000/api
```

### Authentication
Most endpoints require Bearer token:
```
Authorization: Bearer {token}
```

### Key Endpoints

#### Authentication
- `POST /api/auth/register` - Register user
- `POST /api/auth/otp-verification` - Verify OTP
- `POST /api/auth/login` - User login
- `POST /api/admin/login` - Admin login
- `POST /api/admin/2fa/verify` - Verify admin OTP

#### User Management
- `GET /api/user/details` - Get user details
- `GET /api/user/assets` - Get virtual accounts
- `GET /api/user/deposit-address/{currency}/{network}` - Get deposit address
- `POST /api/user/update-profile` - Update profile

#### Transactions
- `POST /api/wallet/internal-transfer` - Internal transfer
- `POST /api/wallet/on-chain-transfer` - On-chain transfer
- `POST /api/wallet/swap` - Swap currencies
- `POST /api/wallet/buy` - Buy cryptocurrency
- `GET /api/transaction/get-all` - Get user transactions

#### Admin
- `GET /api/admin/transactions/get-all` - Get all transactions
- `GET /api/admin/user-management` - Get all users
- `POST /api/admin/user-management/block-user/{id}` - Block user

For complete API documentation, see `TATUM_VIRTUAL_ACCOUNT_ANALYSIS.md` section 10.

---

## 10. Tatum Integration

### Overview
Tatum provides blockchain infrastructure for:
- Wallet generation
- Virtual account management
- Transaction processing
- Webhook notifications

### Configuration
```env
TATUM_API_KEY=your_api_key
TATUM_BASE_URL=https://api.tatum.io/v3
TATUM_WEBHOOK_URL=https://yourdomain.com/api/webhook
```

### Key Operations

#### Master Wallet Creation
```php
GET /v3/{blockchain}/wallet
```

#### Virtual Account Creation
```php
POST /v3/ledger/account
{
  "currency": "BTC",
  "customer": { "externalId": "user_id" },
  "accountCode": "user_code",
  "accountingCurrency": "USD"
}
```

#### Deposit Address Generation
```php
GET /v3/{blockchain}/address/{xpub}/{index}
```

#### Webhook Registration
```php
POST /v3/subscription
{
  "type": "ACCOUNT_INCOMING_BLOCKCHAIN_TRANSACTION",
  "attr": {
    "id": "account_id",
    "url": "webhook_url"
  }
}
```

For complete Tatum integration details, see `TATUM_VIRTUAL_ACCOUNT_ANALYSIS.md`.

---

## 11. Transaction Flows

### 11.1. Deposit Flow

```
1. User sends crypto to deposit address
   ↓
2. Tatum detects transaction
   ↓
3. Tatum sends webhook to /api/webhook
   ↓
4. ProcessBlockchainWebhook job queued
   ↓
5. Job processes:
   - Validates transaction
   - Updates virtual account balance
   - Creates transaction record
   - Creates receive_transaction record
   - Sends notification to user
```

### 11.2. Internal Transfer Flow

```
1. User initiates transfer
   POST /api/wallet/internal-transfer
   ↓
2. System validates:
   - Sufficient balance
   - Valid recipient
   - Fee calculation
   ↓
3. System processes:
   - Deduct from sender
   - Add to recipient
   - Create transaction records
   - Send notifications
```

### 11.3. On-Chain Transfer Flow

```
1. User initiates transfer
   POST /api/wallet/on-chain-transfer
   ↓
2. System:
   - Validates balance
   - Calculates gas fees
   - Decrypts private key
   ↓
3. System calls Tatum API:
   - Creates blockchain transaction
   ↓
4. System:
   - Updates balances
   - Creates transaction records
   - Monitors transaction status
```

---

## 12. Jobs & Queues

### Queue Configuration
```env
QUEUE_CONNECTION=database
```

### Key Jobs

#### CreateVirtualAccount
- Creates virtual accounts for new users
- Triggered on email verification
- Location: `app/Jobs/CreateVirtualAccount.php`

#### AssignDepositAddress
- Generates deposit address from master wallet
- Assigns to virtual account
- Location: `app/Jobs/AssignDepositAddress.php`

#### RegisterTatumWebhook
- Registers webhook subscription with Tatum
- Location: `app/Jobs/RegisterTatumWebhook.php`

#### ProcessBlockchainWebhook
- Processes incoming webhooks
- Updates balances and creates transactions
- Location: `app/Jobs/ProcessBlockchainWebhook.php`

### Running Queue Worker
```bash
php artisan queue:work
```

For production, use supervisor or similar process manager.

---

## 13. Error Handling

### Exception Handling
- Global exception handler: `app/Exceptions/Handler.php`
- Custom exceptions can be added

### Response Format
```json
{
  "status": "error|success",
  "message": "Error or success message",
  "data": {}
}
```

### Logging
- Logs stored in `storage/logs/laravel.log`
- Use `Log::info()`, `Log::error()`, etc.

---

## 14. Testing

### Running Tests
```bash
php artisan test
```

### Test Structure
- Unit tests: `tests/Unit/`
- Feature tests: `tests/Feature/`

---

## 15. Deployment

### Production Checklist

1. **Environment**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Generate new `APP_KEY`

2. **Database**
   - Run migrations: `php artisan migrate --force`
   - Backup database

3. **Queue**
   - Set up queue worker (supervisor)
   - Configure `QUEUE_CONNECTION`

4. **Storage**
   - Create storage link: `php artisan storage:link`
   - Set proper permissions

5. **Optimization**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. **Security**
   - Use HTTPS
   - Set secure cookies
   - Configure CORS properly
   - Review security audit (see SECURITY_AUDIT.md)

---

## 16. Troubleshooting

### Common Issues

#### 1. Queue Not Processing
**Solution:**
- Check queue worker is running: `php artisan queue:work`
- Check queue connection in `.env`
- Check failed jobs: `php artisan queue:failed`

#### 2. Webhook Not Received
**Solution:**
- Verify webhook URL is accessible
- Check Tatum webhook registration
- Check server logs
- Verify firewall allows Tatum IPs

#### 3. Virtual Account Not Created
**Solution:**
- Check user email is verified
- Check queue is processing
- Check Tatum API key is valid
- Check logs for errors

#### 4. Deposit Address Not Generated
**Solution:**
- Verify master wallet exists for blockchain
- Check AssignDepositAddress job is running
- Check Tatum API responses
- Verify xpub and mnemonic are set

#### 5. Transaction Failed
**Solution:**
- Check user balance
- Verify gas fees are sufficient
- Check blockchain network status
- Review transaction logs

### Debugging

**Enable Debug Mode:**
```env
APP_DEBUG=true
```

**View Logs:**
```bash
tail -f storage/logs/laravel.log
```

**Check Queue Status:**
```bash
php artisan queue:work --verbose
```

---

## Additional Resources

- **Tatum Integration**: See `TATUM_VIRTUAL_ACCOUNT_ANALYSIS.md`
- **Security**: See `SECURITY_AUDIT.md`
- **Laravel Documentation**: https://laravel.com/docs
- **Tatum API Docs**: https://docs.tatum.io

---

## Support

For technical support or questions:
- Review logs: `storage/logs/laravel.log`
- Check Tatum dashboard for API status
- Review error messages in responses
- Consult Laravel documentation

---

**Last Updated:** 2025-01-XX
**Version:** 1.0.0


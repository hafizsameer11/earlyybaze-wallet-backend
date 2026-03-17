## EarlyBaze Wallet Backend – Non‑Technical Overview

### 1. What This System Is

This project is the **backend system for the EarlyBaze wallet**.

It powers:

- The **mobile / web app** that your users see.
- The **admin panel** used by your internal team (operations, support, compliance).

The system allows users to:

- Hold balances in **multiple cryptocurrencies**.
- Hold a **local NGN balance**.
- Move money:
  - Receive crypto from outside (blockchain deposits).
  - Send crypto to other EarlyBaze users.
  - Send crypto to external blockchain addresses.
  - Buy crypto using NGN (bank transfer).
  - Withdraw NGN to a bank account.

It also provides admin tools to **monitor, control, and report** on all of this.

---

### 2. Who Uses It

- **End‑users (customers)**
  - Interact from the app (mobile or web).
  - Can see balances, send/receive funds, buy crypto with NGN, and withdraw NGN.

- **Operations / Customer support**
  - Use the admin panel to:
    - Look up users and their wallets.
    - Review and approve buy/withdraw requests.
    - Resolve support tickets.

- **Compliance / Risk / Management**
  - Use reporting and rule modules to:
    - Set anti‑money‑laundering (AML) rules and trade limits.
    - Monitor transaction volumes and suspicious activity.

- **Engineering / DevOps**
  - Deploys, monitors, and maintains the backend and its integrations.

---

### 3. How Money Flows (Plain Language)

#### 3.1. Deposits – Receiving Crypto

- The app gives the user a **deposit address** for a specific coin and network.
- The user sends crypto from another wallet to this address.
- The blockchain provider notifies our backend via a **webhook**.
- The backend:
  - Verifies the deposit.
  - Increases the user’s internal crypto balance.
  - Logs a “received” transaction in the database.
  - Sends a notification to the user.

**Result**: the user’s crypto balance inside EarlyBaze goes up.

---

#### 3.2. Internal Transfers – Send to Another EarlyBaze User

- The sender selects a friend’s email and an amount in the app.
- The backend checks:
  - That the email belongs to a valid user.
  - That the sender has enough balance.
- If everything is okay, the backend:
  - Decreases the sender’s internal balance.
  - Increases the receiver’s internal balance.
  - Records a “sent” transaction for the sender and a “received” transaction for the receiver.
  - Sends notifications to both.

**Result**: money moves instantly inside the system, with **no blockchain fees**.

---

#### 3.3. On‑Chain Sends – Withdraw Crypto to External Wallets

- The user chooses:
  - Which coin and network.
  - The external wallet address.
  - The amount to send.
- The backend:
  - Calculates blockchain network fees (gas).
  - Confirms the user has enough to cover both **amount + fees**.
  - Instructs the blockchain provider to send the transaction.
  - Decreases the user’s internal balance by amount + fees.
  - Stores the blockchain transaction id for tracking.

**Result**: crypto leaves EarlyBaze and arrives in the external wallet.

---

#### 3.4. Buying Crypto With NGN

- The user chooses a coin and how much to buy.
- The app shows instructions (for example, a bank account to pay into and a reference).
- The user makes an NGN bank transfer.
- An admin reviews the proof (like a bank receipt) and **approves or rejects** the buy request.
- When approved, the backend:
  - Credits the user’s crypto wallet.
  - Updates the transaction from “pending” to “approved”.

**Result**: user’s NGN (outside) is converted to crypto **inside** their EarlyBaze wallet.

---

#### 3.5. NGN Withdrawals to Bank

- The user requests a withdrawal:
  - Chooses an amount and a bank account.
- The backend:
  - Checks they have enough NGN balance inside the system.
  - Immediately “reserves” that money by deducting it from their in‑app NGN balance.
  - Creates a withdrawal request with status “pending”.
- An admin sends the actual payout through your banking channel and then:
  - Marks the request “approved” when completed, or
  - Marks it “rejected” if there is a problem.
- If rejected, the backend **automatically refunds** the NGN back to the user’s in‑app balance.

**Result**: NGN leaves the platform safely and the system keeps a complete history.

---

### 4. Safety, Controls, and Audit Trail

- **No double‑spending**
  - When the system changes a balance, it locks that wallet record so two operations can’t touch the same money at the same time.

- **Accurate amounts**
  - The system uses high‑precision calculations designed for money, not normal decimal math, to avoid rounding problems.

- **Duplicate protection**
  - For deposits, webhooks are identified by a unique reference and transaction id.
  - If the same webhook comes twice, it is detected and ignored.

- **Full audit trail**
  - Every movement of funds (deposit, internal transfer, buy, withdraw, etc.) is recorded in a central `transactions` table.
  - Additional tables store detailed information for each type (for example, whether it was an internal transfer or an on‑chain send).
  - This makes it possible to reconstruct exactly what happened for any user or transaction.

---

### 5. What the Admin Panel Can Do

- **User management**
  - Search and view users.
  - See their KYC, wallets, bank accounts, and activity.
  - Block, deactivate, or delete users (depending on policy).

- **Wallets and transactions**
  - View all transactions with filters (by period, type, status, user, etc.).
  - Dive into details for internal sends, receives, buys, withdraws.
  - View user virtual accounts (crypto wallets) and NGN balances.

- **Compliance and risk**
  - Define AML rules and trade limits (for example, daily or monthly caps).
  - Access reports on user volumes, transactions, and balances.

- **System operations**
  - Create and manage database backups.
  - Manage in‑app banners and notifications.
  - Configure roles and permissions for admin users.

---

### 6. Handover Notes for Stakeholders

- The backend is **modular and extensible**:
  - Money logic is centralized in specific services/repositories, which simplifies audits and future changes.
  - Admin and user APIs share the same underlying data, so views are consistent.

- To add **new features** (for example, a new coin or network):
  - Engineers typically add a new currency configuration, connect it to the existing wallet and transaction logic, and, if needed, extend the transaction models.

- For **compliance, audits, and reporting**:
  - The existing database tables already contain:
    - Who moved how much,
    - In which asset and network,
    - When it happened,
    - Through which flow (deposit, internal, on‑chain, buy, withdraw).
  - This data can be used to build any dashboards or regulatory reports you need.

This document is meant for non‑technical readers to understand **what** the system does and **how value flows**, without needing to read the source code. For implementation details, refer to `DEVELOPER_MANUAL.md`.


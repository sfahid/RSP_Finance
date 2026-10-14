# Ready Personal Finance — Simple Edition 0.4.0

Everyday personal finance on PHP/MySQL: income, expenses, bank/cash accounts, parties, bills/invoices and cash flow. Mobile-first, installable PWA. Solid double-entry accounting stays behind clear transaction forms.

## Daily use

- Overview: cash/bank balances, receivables, payables, monthly income/expenses and recent entries.
- Accounts: open any account's full ledger or record a transaction directly against that account.
- Transactions: search all dates, filter income/expenses/Uncategorized, edit, or delete by reversal. Paginated; no 500-record search cutoff here.
- Parties: people and businesses share one model, with both money owed to you and money you owe/hold. Net is shown for information; separate balances are never silently offset.
- Bills & invoices: household bills and partial payments, party income/supplier invoices, private receipt/invoice attachments.
- More: statement imports, documents, reports, income/expense heads, settings and encrypted backup. Accounting entries/reports are available under safeguards for advanced adjustments.

Budgets, savings goals, sinking funds, recurring plans and their daily navigation are retired. The scheduler generates no more bills. Their historical rows remain in the database and encrypted backups. Old reservations no longer reduce the actual cash/bank balance. Future modules are not part of this edition.

## Bank/cash party transactions

From an account or its ledger, choose Record transaction, then money out/in for a party. Select the balance party and optionally a different recipient/sender. Example: Bank ABC pays Mr. B on behalf of Mr. A. Using your own funds debits A's receivable and credits Bank ABC. Using money held for A debits A's payable and credits the bank. B is retained as recipient without inventing another balance against B.

Borrowing, advancing, repaying, collecting and holding/spending funds for a party are principal movements, excluded from personal income/expenses. Party-paid personal expenses debit your selected expense head and credit the party payable without moving your cash. Income invoices debit receivable/credit income; supplier invoices debit expense/credit payable. Settle these through party receipt/payment forms, not a duplicate income/expense entry. Offset requires an explicit agreed adjustment and cannot exceed either side.

## Accounting and data preservation

UUIDs plus local numeric IDs, household scope, exact decimal amounts, immutable balanced journals, audited reversal/replacement corrections, period locks, optimistic versions and idempotent commands remain. Edits retain originals; statement-linked bank amounts/dates cannot change. Statement fingerprints, links and attachments follow replacements. Delete on a posted entry means financial reversal; delete on a party/account means restorable archive after settlement.

Party creation can reuse existing receivable/payable ledgers. Additional old accounts can be assigned to the same party. Linking never reposts, merges or changes a balance. Accounts belonging to different parties cannot be used interchangeably. No automatic name-based merging or netting is performed.

Migration 008_parties adds parties, party_accounts and party_entries only. Existing 0.3.4 owners enable it from Parties → Enable parties with temporary CREATE privileges. Earlier chart/editing/bill migrations remain compatible. Restore handles both pre-party backups and new backups in a fresh database; it never overwrites live books.

## Installation and operations

Use PHP 8.3+ with PDO MySQL, BCMath, OpenSSL, Fileinfo and Mbstring; MySQL 8 or compatible MariaDB. Windows/WAMP and Bluehost shared hosting use the same private core and public assets. Public document root must expose only public/. Keep config/local.php and storage/ outside the public web directory. Use trusted HTTPS for hosted/mobile access.

For a NEW installation, bin/install.php creates a new database/runtime user and refuses an existing database. For an EXISTING installation use the 0.4.0 update guide; never rerun installation/schema imports or owner creation. Configuration, installation UUID, database name, owners and financial records must remain unchanged.

Bank imports support CSV/XLSX/XLS/readable text PDF, explicit review/approval, matching, skipping, duplicate detection and archive/restore. Scanned PDFs require a readable bank export. No OCR/AI service or external data transmission is used. Uploaded data is preserved as private staging rows; financial entries change only after approval.

Backups include all data tables, party metadata, statement staging and private documents. Restore: bin/restore.php archive.rpf NEW_DATABASE NEW_PRIVATE_DIRECTORY, with the backup password supplied privately. Retained scheduled tasks may continue calling the scheduler but generate zero plans.

PWA caches public static assets only. Sessions, API records and documents are not cached. Financial use requires a connection. Permissions allow owners/managers to write and viewers to read; only owners enable schema upgrades and create full-installation backups. Sensitive documents use authenticated private storage, not per-file encryption or malware scanning.

## Validation

Synthetic-only checks: tests/integration.php (32), tests/parties.php (60), tests/editing.php (35), tests/api-parties.mjs (29), tests/api-editing.mjs (15), tests/api-imports.mjs (15). tests/simple-ui.mjs (19 DOM logic checks) is not a rendered-browser layout test. Encrypted restore checks cover old tables, journal totals, UUIDs, files and all three party tables. PHP/JS syntax and package checksums are verified. Retired planning-only tests are removed; previous versions remain in Git history.

Browser validation confirmed direct bank-to-party payment, correction and reversal, with no horizontal overflow at 360/412/1280 pixels. Save/Cancel controls were 50 pixels high. Single linked accounts are selected automatically without an extra selector. Physical-phone and hosted verification remain post-upload checks. Packages are not hosted deployments. See docs/RELEASE_040.md and the update guide.

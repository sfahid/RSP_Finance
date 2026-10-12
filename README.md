# Ready Personal Finance

Working local WAMP release 0.3.1. PHP 8.3+ with PDO MySQL, BCMath, OpenSSL, Fileinfo and Mbstring. MySQL 8.3 was tested on this machine. Update the installed runtime to a maintained patched release before wider household use.

## Run

Point Apache only at `public/`. Run `php bin/install.php readyfinance` from the application folder with local database administrative credentials supplied through private environment variables when necessary. The installer creates a new database and dedicated runtime user and refuses to overwrite existing databases. Open the app on localhost and create your owner account. No default login or sample financial records are shipped.

In this machine's deployment, the private app lives at `C:\wamp64\apps\readyfinance`, and the public entry/assets live at `C:\wamp64\www\readyfinance`. Open `http://localhost/readyfinance/`. Keep database credentials in private `config/local.php` and exclude it from packages/source control.

## Included

Mobile navigation, dashboard, accounts and opening journals, PKR income/expenses/refunds/transfers, category splits, optional member/project/tag attribution, balanced immutable journals and reversals, bills and partial payments, monthly category budgets, principal borrowing/lending and repayments, goals/sinking funds with non-ledger reservations, recurring bill generation, members, categories, private receipt downloads, CSV transaction export, book totals, audit, period locks, and password-encrypted full-installation backups. Identity and household memberships are separate tables; this UI currently provisions one household with an owner.

## Accounting and statement imports

More → Chart of accounts exposes Assets, Liabilities, Income, Expenses and Equity, with Receivables and Payables child groups. Create same-class parent groups and posting subaccounts; Income/Expense subaccounts are linked to the everyday category selectors and budgets. Parent groups cannot receive journal postings. Balanced journals support accruals and adjustments. Dated reports include hierarchy rollups, account ledgers, income/expense summary, balance sheet and trial balance.

More → Bank statement imports stages CSV, XLSX, XLS and readable text PDF files. Review dates, mapping, row counts, totals, balances and classifications before explicit approval. Classify income, expenses, refunds, transfers, borrowing/lending and principal repayments, match existing entries, or skip. Approvals are atomic and duplicate links are scoped to each bank account. Scanned PDFs and arbitrary PDF layouts need a bank export; no OCR or external service is used. Vendor libraries are bundled in the hosting update. Owners can enable migrations 004/005 inside the app when temporary schema privileges are available.

## Operations

Run `php bin/scheduler.php` from Windows Task Scheduler to generate due recurring bills. It creates obligations, never assumes payment. The local deployment registers an hourly task. PC shutdown delays work until the next run. Restore using `php bin/restore.php archive.rpf readyfinance_restored NEW_PRIVATE_DIRECTORY`, supplying `RPF_BACKUP_PASSWORD` privately. It refuses existing databases/directories and restores into a fresh target. The live application is never overwritten automatically. Scheduled backups and a guided restore UI remain planned.

## Known scope limits

This is the first working vertical release, not the entire 45-section master specification. Foreign currencies, typed permissions and restricted child access, invitations, draft editing, correction replacement, contacts, dedicated project records, tag reporting, advanced budget rollover, fund auto-consumption, interest amortization, committees, assets, investments, market net worth, universal notes/entity links, worker queue/listeners, additional report exports, notifications, encrypted document storage at rest, commercial licensing, cloud sync, AI/OCR and phone offline writes remain to be implemented. Installable PWA with Android and iPhone guidance, PNG icons, and a public-asset-only service worker is included. Offline navigation displays a connection screen; financial records and API responses are never cached. Use trusted HTTPS for phone installation. See docs/BLUEHOST_DEPLOYMENT.md for the hosting package. Sensitive documents are protected by authenticated downloads and host permissions, not per-document encryption or malware scanning in this release.

Financial changes use exact decimal arithmetic; UI formatting alone uses JavaScript numeric values. Journal lines are immutable in the application. Host/database administrators can still alter underlying storage. Cash accounts may go below zero; reservation shortfalls must be reviewed. Reversal of a disbursement with dependent principal repayments is blocked until those repayments are reversed. Future commitments are entered as bills rather than posted future transactions. The database tables are an initial migration rather than the final domain map. Do not treat all foundation acceptance criteria as passed until the remaining scope is implemented and verified.

## Validation

See `tests/integration.php` for isolated accounting fixtures. Tests must use a separate database and never add synthetic data to the production household. Source control and database backups are independent recovery mechanisms.

## Account management and transaction corrections

Release 0.3.1 separates Record expense / Record income from account creation, adds Edit details and Archive / Restore for money accounts, Edit name and Archive / Restore for chart leaves/groups, and Move entries for income/expense categories. Money account types may change within the asset class; credit-card classification stays fixed. Financial history and report balances survive archives. Main headings and required system accounts remain protected.

Owners enable migration 006 through Enable editing & review tools after enabling the chart. Edit an existing transaction from Money: income, expenses, refunds, transfers, bills, principal loans, opening balances and balanced journals use atomic reversal-and-replacement corrections. The original remains linked to its replacement; closed periods require reopening and concurrent edits/retries are guarded. Statement-matched cash amount and date stay unchanged while classification and notes can be corrected. Attachments follow the replacement.

Uncategorized income / expense are selectable posting subaccounts. Money → Needs review includes entries and journals using them, within the newest 500 transaction records. Current views hide superseded originals and reversal entries; Show correction history exposes them. Category moves handle at most 100 current entries as one atomic correction, preserve split details and cash balances, move plans, and optionally archive the source. Unlock affected periods before moving and explicitly choose Combine budgets if needed.

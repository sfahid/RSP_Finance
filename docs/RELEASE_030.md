# Ready Personal Finance 0.3.0

Prepared 11 October 2026. This release adds accounting hierarchy and reviewed statement imports. It remains a usable subset of the master specification.

## Accounting

ChartService exposes Assets, Liabilities, Income, Expenses and Equity. Receivables are Assets children; Payables are Liabilities children. Money accounts remain bank/cash/wallet/savings assets or credit-card liabilities. Income/Expense subaccounts link to existing category selectors and budgets, using the same ledgers rather than a parallel engine.

Migration 005 preserves original journal lines/balances, adds hierarchy columns and assigns existing ledgers to their main groups. Parent groups cannot receive postings. Create groups and same-class posting children; existing chart reparenting/renaming/archival UI remains future work.

Accounting::postJournal supports 2–50 leaf-account lines, exactly one positive debit or credit per line, equal totals, exact money, household locks, period locks, idempotency, audit and immutable reversal correction. Income/expense lines include category IDs so reports/budgets remain journal-derived. Tracked loan positions cannot become invalid.

Dated reports include hierarchy rollups, income/expense summary, balance sheet, trial balance and individual ledgers. Trial totals exclude groups; balance-sheet equity includes accumulated income less expenses. The ledger view is capped at 1,000 entries. Other search remains capped at the latest 500 transactions.

## Statement imports

StatementReader reads UTF-8/UTF-16 CSV, XLSX/XLS through PhpSpreadsheet and readable text PDF through Smalot PdfParser. Libraries are locked and bundled. Limits: 5 MB, 2,000 rows, 40 Excel columns, 40 PDF pages, guarded XLSX expansion; formula cells are rejected without evaluation.

Migration 004 stores private extracted source values, mapped rows and provenance links. Upload/remapping has no financial effect. Dates, monetary scale, debit/credit direction and optional running-balance continuity are validated. Oldest/newest order is detected. Ambiguous collapsed PDF columns are invalid. Wrapped/unsupported PDF text can be omitted or marked invalid, requiring comparison with original row counts/totals or CSV/Excel export. Scanned PDFs need future OCR; no external service is used.

Every row starts unselected. An explicit atomic approval of 1–100 rows can post income/expense/refund/transfer/loan movement, match an existing bank entry, or skip. Accounting::postInTransaction avoids nested commits. Same-date/amount matches are suggestions, not automatic classifications. Invalid approvals roll back all selected changes.

Fingerprint suppression is household/bank scoped and includes normalized date/text/reference/direction/amount/balance plus occurrence number. Without balance, file hash is included to avoid falsely suppressing distinct identical transactions across files. Changed descriptions/overlaps need manual review. A unique link prevents one bank entry being matched to multiple rows for the same bank account. Transfers across two statements are posted once and matched on the other side.

Bank/Savings/Wallet statement accounts are supported. Credit-card adapters, OCR, automatic bill allocation, imported category/member/project/tag splits and an import reversal wizard remain future work. Existing bill/split transactions can be recorded normally and matched. Creating a tracked loan already posts its original disbursement; its bank row should be matched rather than posted again.

## API and upgrade

GET: chart, chart-ledger, imports, import-view. POST: chart-upgrade, chart-create, journal, import-upgrade, import-upload, import-map, import-commit. Existing query routes and /api/v1 routing remain supported. All mutations require session auth/CSRF. Owners/managers can post; only owners enable migrations. Viewers can read charts but cannot access statement staging.

Upgrade buttons require temporary CREATE/ALTER/INDEX/REFERENCES on the dedicated database. DDL is retry-safe but not assumed transactional; schema version 005 is marked after successful backfill. Runtime configuration is unchanged.

Restore accepts pre-import/current backups, adds empty staging tables to older data, restores ledger parents before children and runs hierarchy backfill. It targets a new database/directory and requires administrator access. A browser-based shared-hosting restore tool is not included.

The Readysols ZIP separates private/public contents and bundles vendor. It excludes config/local.php, storage, credentials, real statements, customized index.php/.htaccess and setup tools. The package has not been deployed to Bluehost by this session.

## Validation

- 33 existing isolated financial checks passed.
- 18 chart/journal checks passed, including parent rollups, cross-household rejection, cash separation and reconciled trial/balance-sheet totals.
- 33 import/reader checks passed, including CSV/XLSX/XLS/PDF, rollback, duplication, matching, transfers, invalid values, formula rejection, ambiguous/scanned PDF refusal.
- All 61 supplied Alfalah rows normalized and their running balances reconciled in a separate test database; no supplied rows were posted to live books.
- 14 new API authentication/CSRF/permission checks passed.
- 16 restore checks passed each for older/current synthetic backups: core counts, ledger totals, transaction UUIDs, import counts and completed schema upgrades.
- Browser exercised synthetic PDF upload/classification/approval, and Income subaccount creation/selection. Phone widths 360/412 and desktop adaptation were reviewed. Physical-phone and Bluehost behavior still require destination checks.
- PHP/JS syntax and public-asset-only PWA policy checks pass; Composer audit reported no known dependency advisories at installation.

Tests use separate local databases and synthetic records. Never run them with production configuration.

Remaining master-spec work includes multi-currency/FX, invitations/child permissions, chart editing/reparenting, contacts, advanced budget rollover, committees, dedicated asset valuation/investment workflows, universal notes, document encryption/malware scanning, additional report exports, notifications, licensing, sync, AI/OCR and offline writes. Generic asset ledgers are not a full valuation register.

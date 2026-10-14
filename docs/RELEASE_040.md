# Simple Edition 0.4.0

Ready Personal Finance now centers on income, expenses, accounts, parties, bills/invoices and cash flow. Parties include both people and businesses. Bank/cash transaction recording can select a balance party plus a different recipient/sender, including paying B on behalf of A from account ABC.

Receivables and payables remain separate under one party. Supported flows: advances/on-behalf payments, money received to return/use, reimbursements, repayments, party-paid expenses, income/supplier invoices and explicit offsets. The journal engine validates balanced postings, compatible active accounts, household scope and settlement limits. No recipient debt is invented. Posted corrections and reversals preserve history and matched statement provenance.

Daily navigation and settings no longer advertise budgets/goals/recurring plans. New budget/fund/recurrence/reservation commands are blocked; scheduled generation returns zero. Historical planning/reservation rows remain in database/backups, while cash availability reflects actual accounts. Retired plans do not block account/category archival. Outstanding bills still protect their expense heads.

Additive migration 008 creates three party tables. Existing receivable/payable ledgers are explicitly linked without merging/reposting or automatic name matching. Party archival requires zero balances, can be restored and excludes archived targets from statement assignment. Transaction and party history searches are paginated and cover all dates. Invoices appear in Bills & invoices; private attachments follow corrections. Cash flow excludes openings and own-account transfers and treats reversals in their corresponding direction.

Backup/restore recognizes new party tables and accepts older backups with absent party tables. Restore stays confined to a fresh database. Package excludes private configuration, storage, real statements and setup/owner commands; public entry paths are retained on hosting.

Validation: 60 party/accounting-preservation checks, 35 retained editing checks, 29 party API checks, 15 editing API and 15 statement API checks and 32 core/older-schema checks (186 total); 19 DOM UI logic checks; 20 encrypted restore checks. Syntax and package checksum verification complete. These are synthetic tests. Browser checks at 360/412/1280 pixels found no horizontal overflow. The synthetic A/B bank payment, amount correction and reversal were verified; B retained zero balances. Single linked account selectors are hidden, while multiple linked accounts remain selectable. Physical-phone verification remains pending. Hosted and existing private WAMP installations have not been updated automatically.

Use the supplied 0.4.0 update guide for backup, upload, one-time owner Enable parties, explicit account linking and phone verification. No database reset, reinstallation, owner recreation or bank statement re-upload is required.

# Ready Finance 0.4.4 — Simple bank ledger assignment

This release builds on v0.4.3 and requires no database migration, new tables, or database privilege changes. The cancelled metadata-editor redesign is not included.

Editing a bank/cash transaction opens a simple posting editor. Statement-linked bank lines and the statement date remain fixed. The opposite posting account is searchable and grouped across all five chart classes: assets (including property, stocks, ETF and mutual fund ledgers), liabilities, income, expenses and equity. Receivable/payable accounts are selected directly; a party selection is not required and balances follow the selected mapped ledger.

Existing split journals retain all their lines. Every statement-matched bank side is fixed, including transfers matched in two own banks. Their descriptions can be corrected without changing the matched financial effects. Opening balances and allocated bill payments retain their existing specialized editors and safeguards.

Statement-upload review offers Any ledger account, using the row's bank direction to generate the matching debit/credit pair. Existing expense, income, own transfer and party shortcuts remain. Active leaf accounts only can be posted; parent groups, invalid principal positions and unbalanced journals are rejected. Journal imbalance now returns a clear validation error before persistence.

Saved corrections use the existing audited reversal/replacement service. Import fingerprints and bank links follow the replacement; bank amount/date and balances remain protected. No new party-role fields or account creation behavior is introduced.

Verification: 27 ledger-assignment API checks, 15 existing statement API checks, 51 UI logic checks, 60 syntax checks. Browser testing confirmed locked bank/date fields, searchable counter-account selection, a saved/reopened PKR205000 receipt, and no horizontal overflow at 360 and 412 phone widths. Real hosted/physical-device deployment still requires uploading the package.

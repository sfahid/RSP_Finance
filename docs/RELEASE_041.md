# Ready Finance 0.4.1 — Chart of accounts and journal access

Chart of accounts is directly available in Accounts, mobile More, and the desktop sidebar. All five accounting classes remain supported: Assets, Liabilities, Income, Expenses and Equity, including Receivable and Payable groups. Add subaccounts or groups; open Ledger beside any posting account for complete paged history and transaction editing.

Journal entry is available from Accounts, More, Chart of accounts and the Record transaction dialog. Select between 2 and 50 posting account lines, with a debit or credit per line. Live totals use integer minor units and saving rejects missing accounts, invalid amounts, both-sided lines and imbalances before sending. Server-side balancing, scope, period, permission and idempotency checks remain authoritative. Parent groups cannot receive postings.

Journal corrections use an audited reversal and replacement. Delete / reverse retains the original and cancels its financial effect. Simple income, expense, transfer and party recording remain available. Journal entries affecting party-linked accounts contribute to the party balances and ledger; use the party form when recipient/on-behalf metadata is needed.

This release has no new schema migration and preserves financial records, uploaded statements, import links and private configuration. Older installations may still need the existing Chart/editing upgrade buttons.

Validation: 21 accounting-engine checks, 29 UI logic checks, 57 syntax checks. Browser verification on synthetic data covered a three-account split, imbalance rejection, correction, reversal, adding/removing lines, and layout at 360, 412 and 1280 widths. These checks do not establish a deployment to Bluehost or testing on a physical Samsung phone.

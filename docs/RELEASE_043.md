# Ready Finance 0.4.3 — Incoming bank transfer correction

Assign to a party instead previously classified every transfer as money out. Editing the receiving bank side therefore opened a payment form, and the statement protection correctly rejected its reversed bank effect.

Transaction details now include per-bank debit/credit/net movements and statement-matched account identifiers. Party conversion uses the current bank ledger's posting, or the single matched statement account when opened outside a ledger, before falling back to the original bank selection. A debit/inflow opens receipt purposes and retains the receiving bank and amount; a credit/outflow opens payment purposes. Transfers missing posting details require reopening rather than guessing direction.

The statement date/amount and bank effect protections are unchanged. Corrections retain audited reversal/replacement and transfer the original import link. Existing records and private configuration remain intact; no new migration is needed.

Validation: 46 UI checks, 9 isolated incoming-transfer accounting checks, and syntax verification. Browser testing reproduced a statement-linked Rs.205,000 incoming transfer, opened its Edit → Assign to a party receipt form and saved the correction with Rs.205,000 still on the bank debit side and its total balance unchanged. Production/Bluehost were not modified.

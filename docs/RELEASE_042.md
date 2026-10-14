# Ready Finance 0.4.2 — Optional party account validation fix

The Add party form incorrectly marked optional receivable/payable selectors as required. Choosing Create an empty account therefore triggered native browser validation and blocked submission. Both selectors now allow the blank choice; the existing service creates and links empty accounts. The reusable selector renders one labelled blank choice instead of also injecting None. Archived accounts are excluded from new-party linking choices.

No database schema or financial-posting behavior changed. Existing account assignment remains scoped and audited. Changing the party on a transaction uses Edit and the existing reversal/replacement workflow; assigning an account already owned by another party remains blocked to prevent accidental reassignment of its entire history.

Validation: 38 UI regression checks and 57 syntax checks passed. Real-browser verification on an isolated synthetic installation confirmed blank-account party creation, one existing/one empty account creation with its Rs.70 balance retained, linking an existing receivable with Rs.0.20 retained, and correction of a Rs.1 payment from one party to another while retaining the bank effect. No production database or Bluehost files were modified.

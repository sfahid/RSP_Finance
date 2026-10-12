# Ready Personal Finance — release 0.3.1

Ready for manual upload to the existing Readysols installation. This package has not been deployed to Bluehost by Codex. Existing WAMP private files and household books were not changed.

## Behavior

Separate expense/income entry with existing account selectors; explicit bank/cash account creation; zero opening-balance validation; optional split/member/project/tag controls; mobile dialog titles remain visible while scrolling.

Account name editing, money asset-type editing, archive/restore for chart leaves and groups and Money accounts; protected system headings; zero-balance and planning/reference guards. Category moves reassign at most 100 current entries and plans to a compatible subaccount or Uncategorized, then optionally archive the source.

Transactions are editable through atomic reversal and replacement, linked by replaces_id and a mandatory reason. Income, expense, refund, transfer, opening, bill, loan and journal flows are covered. Period locks, optimistic versions, retries, loan/bill balances, split metadata, attachments and statement provenance are enforced. Statement-matched bank effects and dates cannot silently change.

Uncategorized income/expense and Needs review include their split and journal postings. Current lists omit superseded originals and reversals; correction history remains available. Transaction/review lists cover the newest 500 records. No permanent account deletion or historical journal rewriting is included.

## Database and deployment

Migration 006_editing adds ledger active status and transaction correction fields, preserves posting lines, and seeds Uncategorized leaves. Owner-only browser enablement follows chart migration 005 and needs temporary ALTER/INDEX/REFERENCES privileges (alongside required existing privileges); installations missing import migration 004 still enable imports separately. Migrations can be retried. Backup restore orders original transactions before replacements and reapplies missing upgrades.

Copy private application files outside the public website, public assets into pf, and retain config/local.php, storage, index.php and .htaccess. Libraries, chart and statement import features from 0.3.0 are included. No credentials, private books or real statements are shipped.

## Validation

- 39 isolated account/edit/move financial checks, including atomic failure rollback, split metadata, statement links, budget collisions, loan/bill positions and private attachments.
- 15 API authentication/role/CSRF/scoping checks.
- 33 existing financial checks, 18 chart checks and 30 statement-import checks rerun successfully.
- 16 restore checks on a synthetic backup with corrected entries and documents.
- Browser: empty-account entry flow, zero opening, income/expense postings, partial payment, balanced journal, expense editing/Needs review, account rename/archive and category move/archive.

Phone widths 360/412 and desktop 1280 are checked in the local preview. These are local browser checks, not a Samsung-device or hosted-installation test. Hosted upload and confirmation remain the user's next step.

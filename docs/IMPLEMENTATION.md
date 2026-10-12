# Ready Personal Finance release 0.1.0

Historical release report. Deployment and empty-installation statements below describe the original verification, not current live state. See RELEASE_030.md and the v0.3.0 update guide for the current implementation and validation.

Prepared and verified on 11 October 2026. This release implements the first usable local financial slice of the master specification. It does not claim completion of every foundation criterion or future phase.

## Deployment

- Live local app: http://localhost/readyfinance/
- Public entry and assets: C:\wamp64\www\readyfinance
- Private application, configuration, sessions and documents: C:\wamp64\apps\readyfinance
- Dedicated database: readyfinance, with a separately generated runtime database user
- Owner, household, accounts and business transactions in the real installation: zero until the user completes setup
- Hourly Windows task: Ready Personal Finance recurring bills
- No external publication, cloud service or port forwarding

The application has its own domain services under app/Modules. The application facade owns command coordination and idempotency; Accounting owns the journal; Shared/Core owns exact-money primitives, storage transaction management and scoped persistence. Frontend components share the same data services across desktop and mobile. Refactor repositories and validators further as individual modules grow; do not create parallel engines.

## Verified evidence

33 isolated financial integration checks passed, including UUIDv7, exact money, same-command retries, split reconciliation, rollback after actual writes, InnoDB enforcement, database-level household foreign keys, card purchase/settlement, bill overpayment and reversal, principal repayments, backed reservations, recurrence, month locks, and rejection of dependent disbursement reversals.

14 HTTP checks passed for authentication, scope, posting, dashboard reconciliation, command deduplication, CSRF protection, private attachment upload/download, encrypted backup, and the empty production setup state.

12 restore checks passed after decrypting a real downloaded backup into a separate database. Checked table counts, ledger totals, transaction UUIDs and document checksums. The live database was not overwritten.

Dashboard layout checks at 320, 360, 390, 412, 768 and 1280 CSS pixels found no page overflow. Phone search/form inputs use 16-pixel text. Quick expense entry and partial bill payment were exercised in the browser on a synthetic household. Browser error logs were empty at the final dashboard check. These are browser viewport tests, not physical-phone install or HTTPS tests.

## Scope remaining

The README lists remaining functionality. Major next stages are richer contacts/projects and tag reporting, restricted family logins and permissions, expanded reports and notification delivery, guided restore and automatic backup, foreign currencies, committees, assets and valuations, investments, AI/OCR and offline/cloud synchronization. The manifest is preparatory; service-worker/offline behavior and a trusted HTTPS phone installation are not claimed.

The master specification remains the canonical design. This implementation record identifies the current release boundary. All new schema changes must be migrated. In particular, the local WAMP default table engine was found to be non-transactional; migration 003 enforces InnoDB and restores missing foreign-key constraints for this app. New installs explicitly specify InnoDB in every table definition. Startup refuses non-InnoDB application tables.

## Artifact status

The editable master DOCX was generated with 45 sections. Its contents were reviewed, but automatic Word page rendering could not complete on this host; pagination has not been visually verified. No available tool confirmed saving to the user's ChatGPT Library. The source package includes the same master text in Markdown for development and version control.


## Release 0.2.0 — mobile installation and Bluehost package
Installable PWA assets, browser installation prompt and iPhone/Android guidance are included. The service worker stores only public assets and shows an offline connection page; no financial API responses, authenticated HTML, documents or backups are cached. Local deployment updated. Bluehost shared-hosting preflight, empty database installer and private CLI owner setup are included. The 33 accounting checks passed again; cache policy checks and hosted setup refusal/owner checks passed. Physical phone installation and live Readysols hosting are not verified. See BLUEHOST_DEPLOYMENT.md.


## Release 0.2.0 — mobile installation and Bluehost package
Installable PWA assets, browser installation prompt and iPhone/Android guidance are included. The service worker stores only public assets and shows an offline connection page; no financial API responses, authenticated HTML, documents or backups are cached. Local deployment updated. Bluehost shared-hosting preflight, empty database installer and private CLI owner setup are included. The 33 accounting checks passed again; cache policy checks and hosted setup refusal/owner checks passed. Physical phone installation and live Readysols hosting are not verified. See BLUEHOST_DEPLOYMENT.md.

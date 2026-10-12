# Ready Personal Finance Master Development Specification

Canonical product and architecture specification

Version 1.0 • 11 October 2026 • Recommended baseline for development

This specification defines a mobile-first personal and household finance web application running primarily on a Windows 11 WAMP computer and accessed by phones, tablets, and PCs on the household network. It consolidates the earlier Personal Finance App Features discussion into one consistent implementation model. Its purpose is to guide developers, reviewers, and testers from the foundation through later expansion.

The recommended solution is a modular PHP monolith with one authoritative double-entry accounting engine, a household isolation boundary, responsive HTML and JavaScript, and a versioned JSON API. The application must remain useful without cloud services. PWA installation, offline writing, cloud synchronization, AI, and bank integrations are separate capabilities with explicit release gates.

Normative language: MUST identifies a required invariant or acceptance condition; SHOULD identifies a recommended implementation; MAY identifies optional behavior. Features marked future require an extension boundary now, not an unfinished production implementation. Architecture can reduce future redesign; it cannot guarantee that no migration or contract evolution will ever be necessary.

## 1 Product vision and scope

Ready Personal Finance helps an individual or family understand money available today, spending, upcoming commitments, debt, savings goals, and net worth. Household users see familiar concepts such as Expense, Income, Transfer, Bill, Borrow, Lend, and Goal. Accounting terminology remains behind the interface except in owner-controlled advanced views.

The first complete household release includes users and members, cash and bank accounts, credit cards, income, expenses, transfers, splits, categories, contacts, attachments, budgets, bills and partial payments, recurring schedules, basic borrowing and lending, goals and sinking funds, dashboard, reports, in-app notifications, backup and restore. Foundation delivery precedes this release and has a smaller functional scope.

Later releases add committees, asset registers, investment holdings, richer forecasts, import review, OCR, AI assistance, offline entry, synchronization, and optional hosted operation. Household finances remain a separate product from business invoicing, inventory, estate agency operations, and company books. A business owner may record household drawings or distributions without importing an entire business ledger.

Product success means fast routine entry, trustworthy totals, visible future commitments, private family access, recoverable data, and expansion through modules rather than parallel financial engines.

## 2 Target users and household model

A household is the financial ownership and isolation boundary. An individual creates a one-person household. A family can add adults, children, parents, and dependents. A login user is an identity; a household member is a person or reporting attribution; a membership grants access to a household. These concepts MUST remain separate.

Users may belong to multiple households through household_memberships. household_members optionally link to a user, but children and dependents need no login. Invitations link the intended user to a membership only after acceptance; creating a member does not create credentials. Membership removal revokes access without erasing historical attribution.

Every household-owned account, transaction, obligation, document, plan, and report is scoped to household_id. The server selects the active household from an authenticated membership and verifies every referenced entity. Client-supplied household identifiers are never sufficient authorization. Cross-household transfers are two separate books and require explicit linked workflows later.

An account can have member attribution or joint ownership metadata, but financial ownership remains the household. Initial access defaults are shared household finances and account-scoped restricted access for limited members. Restricted users cannot infer hidden balances from dashboards, totals, exports, search, document links, or report drilldowns. The UI identifies partial views rather than presenting them as complete household totals.

## 3 Rationalized architectural decisions

| Earlier ambiguity | Canonical decision |
|---|---|
| Page folders versus domain folders | Domain modules own business behavior; HTTP, UI, and infrastructure remain adapters. |
| Accounts versus chart of accounts | ledger_accounts is the sole chart of accounts; accounts provides user-facing financial account metadata with one linked ledger account. |
| Categories versus expense ledger heads | Categories are user-facing classifications with controlled income or expense ledger mappings; splits carry categories. |
| Separate income expense and debt movements | One transactions aggregate and posting engine; domain modules own schedules and contracts. |
| Posted records regenerated after editing | Posted journals are immutable; corrections use balanced reversal and replacement. |
| Journals generated by background listeners | Posting, allocations, audit, and outbox commit atomically; listeners handle secondary work after commit. |
| Bills counted as both obligations and spending | Default cash recognition; bills describe obligations until payment. An explicit accrual mode is a later extension. |
| Goals and sinking funds have separate machinery | One funds model with purpose goal or sinking_fund; earmarking does not move cash. |
| Assets and investments counted multiple times | Each holding has one valuation owner; market net worth replaces book value once. |
| One universal status list | Each aggregate has a typed state machine; derived due and payment states stay separate. |
| Every table gets soft deletion and sync status | Mutable business entities get versions and tombstones; journals, audits, and events are append-only; sync delivery state is per peer. |
| Local-first means offline on every phone | WAMP is initially authoritative; disconnected phone writes are a later release. |
| LAN HTTP gives full PWA capability | Trusted HTTPS is required for the phone LAN deployment; localhost development is a different secure-context exception. |

## 4 Recommended technology baseline

| Layer | Recommended baseline | Implementation decision |
|---|---|---|
| Host | Windows 11 with maintained WAMP distribution | Pin the actual Apache PHP and database versions in installation metadata. |
| Web server | Apache 2.4 maintained build | Virtual host DocumentRoot points only at public. Enable routing and trusted TLS. |
| Backend | PHP 8.4 or 8.5 supported patched branch | Prefer 8.5 after dependency checks; keep 8.4 compatible during initial development. |
| Dependencies | Composer with lockfile and PSR 4 loading | Small maintained routing validation UUID decimal and migration packages. |
| Database | MySQL 8.4 LTS with InnoDB | MariaDB 10.11 or 11.4 LTS may be an alternative after compatibility tests; certify specific versions. |
| Data access | PDO prepared statements | Explicit repositories and transaction management; no SQL in views. |
| Frontend | Semantic HTML5 CSS and JavaScript modules | Responsive shell with progressive enhancement; no heavy framework required. |
| API | Same-origin JSON REST-style API under /api/v1 | Cookie sessions initially; future clients use a separate authentication adapter. |
| Charts | Chart.js or equivalent pinned library | Accessible textual summaries and data tables accompany charts. |
| Jobs | PHP CLI worker and Windows Task Scheduler | Database-backed durable queue; Redis is optional later. |
| Quality | Automated domain integration API and UI checks | Composer scripts and reproducible fixtures; schema tested on each supported database. |

PHP extensions include pdo_mysql, mbstring, intl, openssl, sodium, fileinfo, bcmath or an exact decimal library, and zip. Image processing is enabled only when needed. Installation preflight verifies available extensions, writable private directories, database permissions, scheduler readiness, and maximum upload size. Exact package versions are pinned during implementation rather than guessed in this specification.

Use a stable local hostname or reserved LAN address. Configure trusted HTTPS for each accessing phone. A private certificate authority is acceptable if its trust is installed correctly on those devices. No port forwarding or public exposure is part of the default local deployment. The WAMP PC must be awake and reachable for initial operation; local-first does not mean every device stores a complete independent copy.

## 5 Architecture and dependency rules

Use a modular monolith: one deployable application and one transactional database, with clear ownership of modules. Request flow is HTTP controller → validator and policy → application service → domain rules → repository and accounting service → database. Read flows go through scoped query and reporting services. The UI and AI adapters call the same application commands.

Accounting, Money, Identity, and Household define shared contracts. Domain modules may depend on these contracts but MUST NOT write another module's tables directly. Infrastructure implements repositories, storage, messaging, and provider clients. Accounting must not depend on bill pages, OCR providers, or dashboard widgets.

Controllers handle transport and response codes. Services orchestrate use cases and transaction boundaries. Domain objects enforce money, posting, schedules, and state transitions. Repositories persist aggregates and enforce scope. Query services provide efficient read models. Avoid an abstract universal repository, unbounded generic entity framework, or excessive interfaces with no credible alternative implementation.

All core write commands have explicit authorization, validation, expected version, idempotency where retry is plausible, audit intent, and failure handling. No widget, scheduled job, attachment upload, or integration can bypass these commands.

## 6 Project structure and naming

```text
ready-personal-finance/
  app/
    Modules/
      Identity/ Household/ Accounting/ Money/
      Classification/ Budgets/ Bills/ Recurrence/ Obligations/
      Funds/ Committees/ Assets/ Investments/ Projects/
      Documents/ Notifications/ Reporting/ Search/
    Shared/
      Money/ Clock/ Ids/ Validation/ Authorization/ Events/
    Infrastructure/
      Database/ Storage/ Queue/ Backup/ Sync/ Integrations/
    Http/
      Controllers/ Middleware/ Requests/ Responses/
  bootstrap/              application and container setup
  config/                 checked-in non-secret defaults
  database/
    migrations/ seeds/ fixtures/
  public/
    index.php assets/ manifest.webmanifest sw.js
  resources/
    views/ components/ js/ css/ lang/en/ lang/ur/
  routes/                 web.php api.php
  storage/                private runtime files outside web root
    documents/ staging/ backups/ logs/ cache/
  bin/                    scheduler worker backup healthcheck
  tests/                  unit integration api ui security
  docs/                   specification ADRs API runbooks
  vendor/                 Composer dependencies
  .env.example composer.json composer.lock
```

Each module contains Domain, Application, and Persistence or adapter folders only where needed. Use one responsive view system, not unrelated mobile and desktop implementations. In production, private storage may be relocated outside the application release directory through configuration. public/uploads MUST NOT contain receipts or sensitive files. Database backups belong in private storage, not database/backups in source control.

PHP namespaces use ReadyFinance\Modules\ModuleName; classes use PascalCase; methods and JavaScript identifiers use camelCase. Tables and columns use lowercase snake_case; tables are plural; foreign keys use entity_id; UUID columns use uuid. API fields use snake_case consistently; URLs use lowercase plural resource names. Permission keys use domain.action; events use past-tense PascalCase; translation keys are stable semantic keys. Avoid Helpers as a dumping ground and labels tied to one bank or local currency.

## 7 Domain ownership and boundaries

| Domain | Owns | Must delegate |
|---|---|---|
| Identity and Household | Users memberships roles members scope | Money changes to transaction commands |
| Money | Account metadata transaction drafts splits posting requests | Journal invariants to Accounting |
| Accounting | Chart journals reversals periods balance rules | Display grouping to Reporting |
| Classification | Categories tags contacts and mappings | Financial movement to Money |
| Bills | Payable reminders bill lines payment allocations | Payment posting to Money |
| Obligations | Borrowed or lent contracts schedules principal allocation | Cash movement and journals |
| Recurrence | Rules occurrences generation leases | Target creation to each owning domain |
| Budgets and Funds | Spending plans earmarking targets | Actual figures to central calculations |
| Committees | Rotation membership contribution and payout schedule | Settlements to Money and Accounting |
| Assets and Investments | Holding metadata quantities valuation history | Cash posting and net worth aggregation |
| Documents | Private blobs notes and entity links | Entity access to owner policies |
| Reporting and Search | Scoped queries calculations indexes | Source mutation to domain services |
| Infrastructure | Jobs backups logs provider and sync adapters | Business rules to domain commands |

## 8 Database conventions and record identity

Use InnoDB, utf8mb4, strict SQL mode, explicit foreign keys, and deterministic ordering. Select a collation supported by both certified engines, such as utf8mb4_unicode_ci; use binary comparison for UUIDs and stable machine codes. Do not depend on MySQL-only JSON indexes or UUID functions without a tested adapter. Relational columns hold invariants; JSON holds bounded preferences or versioned snapshots, not searchable financial relationships.

Major records have BIGINT UNSIGNED auto-increment id for local joins and application-generated UUIDv7 uuid for APIs, exports, and future sync. Use a maintained UUID implementation. Store UUIDs consistently as CHAR(36) with ASCII binary collation initially; a later BINARY(16) optimization requires a migration and adapter. UUID time ordering is not a trusted audit clock. UUIDs are generated before insertion and never reused or regenerated on restore.

Local numeric IDs are installation-specific and MUST NOT be transferred as cross-install references. Sync and portable exports refer to UUIDs and resolve them to local IDs. A UNIQUE(uuid) constraint applies to major entities. Public routes use UUIDs, but unpredictable identifiers do not replace authorization. Small static lookup tables and join tables need no unnecessary UUID unless independently addressable or replicated.

Add UNIQUE(household_id, id) to scoped parents where composite foreign keys are used, and reference (household_id, parent_id) from scoped children. This enforces same-household relationships at database level in addition to service validation. Operational tables have suitable indexes beginning with household_id for date, status, account, due date, and common search filters. Prevent hierarchy cycles and avoid cascading deletion of financial history.

## 9 Standard columns and table classes

| Table class | Required conventions |
|---|---|
| Mutable household entity | id uuid household_id created_at updated_at created_by updated_by row_version origin_installation_uuid origin_device_uuid; deleted_at only where archival or tombstones apply |
| Financial aggregate | Standard mutable fields plus status effective_date posted_at and correction links; posted content becomes immutable |
| Journal lines and allocations | Parent scope foreign keys exact money attribution and origin; immutable after posting; no soft-delete flag that hides posted lines |
| Audit outbox and change feed | Append-only event UUID scope actor UTC timestamp payload schema version sequence and correlation ID |
| Global identity or lookup | Explicit global scope; no meaningless household_id; identity changes audited |
| Join or delivery state | Composite unique keys and required timestamps; per-peer sync cursor and delivery state rather than generic sync_status |

UTC timestamps use DATETIME(6) written by the application with the connection timezone set to UTC. row_version is a monotonic integer incremented on successful mutation, used for optimistic concurrency. origin_device_uuid can be null for server jobs; it identifies the first creator, while audit records identify later actors and devices. deleted_at is a tombstone for eligible mutable records, not permission to erase posted accounting.

created_by and updated_by identify authenticated users or a documented system principal. Deactivated users remain referencable. Important records include source_type, source_uuid, and source_version when generated or imported. Track schema version independently from entity version, API version, and app version.

## 10 Money currency and rounding

Use exact decimal arithmetic end to end. Store monetary amounts as DECIMAL(20,6), exchange rates as DECIMAL(24,12), and investment quantities as DECIMAL(28,12). The additional scale accommodates currencies with different minor units and precise conversion; it does not authorize fractional PKR paisa on posted cash amounts. currencies contains ISO code, display symbol, minor_unit_scale, and active status. Non-fiat instruments belong in securities with their own quantity precision.

API money values are decimal strings such as "4500.00", never binary floating-point JSON numbers. PHP uses a Money value object and exact arithmetic; JavaScript formatting does not perform authoritative calculations. Reject excess entered scale rather than silently truncate. Validate magnitude before persistence. Round posted cash and base-currency lines to the corresponding currency scale using a documented half-up policy; preserve unrounded calculation inputs where needed.

Households start with a chosen base currency, default PKR, and each monetary ledger account has one currency. Freeze the base currency after first posting; a later conversion is a controlled migration, not a settings edit. Rates mean base currency units per one foreign currency unit. Store rate, source, effective date, and base amounts on posted lines; historical reports never recompute them with today's rate.

Validate balance in base currency, not by adding unrelated nominal currencies. Same-currency entries must also balance in that currency. A currency conversion records both exchanged quantities, explicit rate, fees, and any rounding or realized FX line. Reject unexplained residuals; bounded roundoff goes to a configured FX rounding ledger account. Foreign monetary balances retain historical carrying value; optional revaluation posts a separate dated adjustment. Display-only current-rate conversions are labeled estimates.

## 11 Accounts and the chart of accounts

ledger_accounts is the accounting chart: household_id, code, name, class, normal_side, parent_id, currency_id, is_postable, system_key, and active state. Classes are asset, liability, equity, income, and expense. Group nodes are not postable. Classification is authoritative here; a bank account subtype cannot redefine a liability as an asset.

accounts is the user-facing metadata for cash, bank, wallet, credit card, loan, receivable, investment custody, or tracked asset accounts. It contains ledger_account_id with a unique relationship, subtype, institution/contact, masked account number, credit limit, display order, owner member, and cash/net-worth inclusion flags. Account group display preferences are not a second chart. Inactive accounts remain in historical reports; hiding an account does not remove its balance.

Opening balances are dated transactions posted against Opening Equity, not an editable accounts.opening_balance added again by reports. Credit limits are not assets. Loan principal, card balances, and receivables derive from journals and reconcile to linked contracts. Cache balances only as rebuildable projections with a last processed sequence; never make a stored current_balance authoritative.

Seed a minimal chart for cash and bank assets, receivables, investments, tracked assets, card and loan liabilities, household equity, opening equity, income, expense categories, FX gain/loss, valuation reserve, and roundoff. System keys are stable identifiers independent of translated labels. Chart creation is idempotent per household. Account closure requires zero balance or an explicit transfer/write-off and no active schedules depending on it.

## 12 Categories tags contacts members and projects

Categories describe the nature of income or expense and have household scope, parent, name, type, active state, icon, display order, and mapped ledger_account_id. Only compatible income or expense ledger accounts may be mapped. Many categories may share an expense ledger if reporting uses immutable split attribution. Default categories include housing, food, transport, education, health, gifts, recreation, household supplies, salary, distributions, rent, and other income.

Prevent category cycles; render shallow navigation and provide search even if storage supports deeper nesting. Never change historical classification silently when a mapping changes. Posting records category UUID and ledger mapping on the line; an explicit reclassification command is audited and respects locked periods. Archived categories cannot be used for new entries but remain reportable.

Tags are many-to-many descriptive labels; they are not ledger accounts and do not create money. contacts holds people and organizations with optional roles such as merchant, utility, lender, borrower, school, or bank. A household member may link to a contact when useful, but contact creation does not grant access. Store optional personal data sparingly.

projects provides a bounded reporting dimension for a wedding, holiday, home renovation, school year, or household event, with dates, budget target, status, and member responsibility. An event is a project subtype, not a second accounting system. Transaction splits and bill lines carry member_id and project_id; tags can link at transaction and split level. Report rules must avoid counting a tagged transaction and all its tagged splits twice. Calendar views are projections over transactions, bills, and scheduled occurrences.

## 13 Generic transaction aggregate and splits

transactions owns user intent and lifecycle: UUID, household, type, effective_date, optional occurred_at, description, reference, primary contact, transaction_currency_id, display_total, status, source link, expected version, posted journal link, and correction references. Types include expense, income, transfer, borrowing, lending, repayment, refund, purchase, disposal, investment_trade, opening, adjustment, and reversal. Types select posting strategies; they do not define independent storage engines.

transaction_splits owns attributed economic components: transaction_id, line_no, component_type, amount, currency_id, financial account or mapped ledger target, category_id where applicable, member_id, project_id, contact_id, and memo. Components include expense, income, principal, fee, tax, asset, and settlement. Category/member/project on the header are UI defaults only; reports use resolved split attribution. Never make a header category compete with split categories.

For a simple expense, display_total equals split expense amounts plus explicit fees and tax components, with a separate funding leg. A transfer has source and destination amounts and currencies and optional expense fee splits; its transfer principal is not spending. display_total is a presentation value, not a sum of both sides of the journal. At initial release, use one funding account per simple entry; later mixed funding uses explicit funding legs without changing the journal model.

Draft splits may be edited. Posting validates that attributed components and settlement amounts reconcile. If one category is selected, generate one split using the same path as a multi-category transaction. Split-level amounts and references must be retained in journal linkage so budgets, members, and projects all reconcile. Refunds reference original splits where possible and reduce the original expense classification rather than being arbitrary income.

## 14 Double entry posting and correction engine

journal_entries contains transaction_id, effective_date, posting sequence, status posted, currency context, source version, reversal_of_id, and actor. journal_lines contains entry_id, line_no, ledger_account_id, original currency debit or credit, rate, base_debit or base_credit, split reference, and reporting dimensions. One side is positive and the other zero; negative debit or credit amounts are prohibited. A posted entry has at least two nonzero lines and balanced exact base totals.

Posting is one database transaction: authorize and validate; lock the source aggregate, affected obligation allocation rows and relevant period guard; verify expected version and idempotency; build journal; verify currency and base balance; persist lines and allocations; change transaction state; append audit and outbox events; commit. Any failure rolls everything back. Use fixed lock ordering and bounded deadlock retries tied to the same idempotency key. Exactly-once economic effect is enforced by unique source/posting keys, not hoped for from queue delivery.

Journal generation MUST NOT be deferred to an asynchronous TransactionPosted listener. No transaction is reported as posted before its complete journal exists. Balance and reporting projections may update after commit because the journals remain the source of truth. The restricted database user and application policies prevent direct UI changes to journals; advanced manual adjustments still use the same validated engine.

Posted records cannot be edited in place. Correcting an unlocked entry creates an exact reversal using original rates and dimensions and a replacement transaction in one controlled command. The old intent is marked superseded with links; reports include original, reversal, and replacement journal effects rather than filtering the old journal away. Voiding creates a reversal and marks the original intent reversed. Linked bill, debt, and fund allocations reverse consistently. Lock rules may require the reversal to be dated in the current open period.

Duplicate command retries return the prior committed result. A reused key with different payload returns a conflict. A post timeout is resolved by looking up the key or transaction status, not by assuming failure and creating another entry. Manual adjustments require a reason, privileged permission, and clear reporting classification.

## 15 Posting examples and accounting invariants

| Household action | Debit | Credit | User report effect |
|---|---|---|---|
| Grocery paid 4500 | Grocery expense 4500 | Bank 4500 | Expense 4500 |
| Salary received 150000 | Bank 150000 | Salary income 150000 | Income 150000 |
| Bank to cash 20000 | Cash 20000 | Bank 20000 | Internal transfer excluded from income and expense |
| Borrow 50000 | Bank 50000 | Loan payable 50000 | Cash rises debt rises no income |
| Lend 15000 | Loan receivable 15000 | Bank 15000 | Cash falls receivable rises no expense |
| Repay 11000 including fee 1000 | Loan payable 10000 and finance expense 1000 | Bank 11000 | Expense only 1000 |
| Card grocery 5000 | Grocery expense 5000 | Credit card payable 5000 | Expense now no cash outflow yet |
| Pay card 5000 | Credit card payable 5000 | Bank 5000 | Cash outflow no second grocery expense |
| Reserve goal money 10000 | No journal | No journal | Available unreserved cash falls total cash unchanged |
| Buy tracked vehicle 1000000 | Vehicle asset 1000000 | Bank 1000000 | Asset purchase cash flow net worth initially unchanged |
| Expense refund 2000 | Bank 2000 | Original expense ledger 2000 | Net spending decreases 2000 |

Acceptance fixtures include grocery splits 8000, 2000, 2000 against one 12000 payment; a foreign-currency exchange with fee and rate; and correction of a partially allocated bill payment. The engine must reject unbalanced lines, wrong-household references, locked dates, invalid currencies, duplicate economic postings, and allocation overpayment.

## 16 Bills obligations and partial payments

Bills are expected or confirmed payment obligations before settlement. bills contains contact, issue_date, due_date, currency, confirmation state, expected or confirmed amount, recurrence occurrence, and cancellation metadata. bill_lines holds category/member/project attribution. bill_payment_allocations links a posted transaction settlement to one or more bills with exact allocated amount; it does not store another cash payment.

Default household recognition is cash basis for ordinary bills: creation and confirmation do not post expense or payable; each payment posts its expense shares and updates the allocation. An estimate becoming confirmed is audited. Category attribution for a partial payment is explicitly chosen or proportionally allocated with deterministic residual rounding. A 10000 bill paid 4000 and 6000 produces 10000 expense overall, not 20000.

Bill lifecycle is draft, open, cancelled, archived. Payment state unpaid, partial, paid and timing upcoming, due, overdue are derived from confirmed amount, active allocations, due date, and household date. Do not persist conflicting combinations such as paid and overdue. Excess payments are rejected initially; later supplier credits require explicit credit balances. Refunds and reversals reopen the unsettled amount correctly. Cancelled partially paid bills retain history and require a settlement or credit decision.

Debt installments and committee dues can appear in the same Bills and calendar UI through an obligation projection, but remain owned by their contracts. If an explicit reminder bill links to an installment, its settlement routes to the obligation posting strategy and is not a second expense bill. Unique obligation occurrence references prevent duplicate future cash flow. Later accrual mode would post bill expense against payables on confirmation and settle payables on payment; it requires a controlled mode migration and cannot be mixed casually with cash recognition.

## 17 Recurring engine and occurrence control

recurring_rules owns timezone, start date, frequency, interval, local time if relevant, month-day policy, end date or count, next due date, generation horizon, reminder offset, template type, template version, and auto_create mode. recurring_occurrences has a unique rule UUID plus scheduled local date/time or stable sequence, generation state, target reference, and failure record.

Support daily, weekly, monthly, quarterly, annual, and explicit custom dates. A monthly rule starting on the 31st defaults to the last valid day in shorter months; this policy is stored and visible. A missed scheduler run processes bounded overdue occurrences according to a chosen catch-up policy. Pausing preserves history. Editing future terms creates a new effective rule revision; existing confirmed targets do not silently change.

The engine calls target factories for draft expense/income/transfer, open bill, obligation installment, fund reminder, or committee contribution. Initial automatic generation creates drafts or obligations, not assumed payments. Auto-posting known fixed transactions is an optional owner-enabled mode with explicit consent, posting validation, an audit trail, and a notification. A rule cannot directly insert a journal. Generate one economic target per occurrence and use leases, uniqueness, and idempotency to survive restarts.

## 18 Budgets and actual spending

budgets identifies a plan, currency, scope, recognition basis, and active revision. budget_periods holds explicit start and exclusive end dates; budget_lines defines a category/member/project filter, planned amount, rollover rule, and precedence. Monthly, quarterly, annual, and custom periods are supported. Prevent overlapping active periods for the same plan and overlapping counting rules within its line set.

Initial budgets use posted net expense splits by effective_date, including card purchases and finance charges and subtracting refunds. Transfers, borrowed funds, loan principal, earmarking, and asset purchases are excluded unless an explicit cash allocation budget view is chosen. Pending bills appear as commitments, not actual expense. A later accrual view must be labeled and cannot alter historical cash reports.

Parent category budgets include descendants exactly once. A child override belongs within its parent envelope; do not sum parent and child actuals into an inflated total. For independent scenario budgets, compare each separately. Rollover is computed once at closed period boundaries; policy can carry positive remainder, overspend, both, or neither. Historical plan revisions preserve what was planned at the time. Percent-based plans and shared member allowances can be later calculation strategies.

## 19 Debts loans and lending

Use obligations with direction borrowed or lent, counterparty, principal currency, linked liability or receivable ledger account, start date, maturity, rate/markup method, payment schedule, status, and agreement metadata. obligation_terms stores effective revisions; obligation_installments stores scheduled principal, interest/markup, fees, and dates; obligation_allocations links actual posted components to the contract and installment. Receivables is a view of lent obligations, not a parallel movement engine.

Disbursements post principal to liability or receivable. Repayments split principal, finance cost or income, and fees explicitly. Outstanding principal derives from the linked ledger and allocations; scheduled interest is a forecast until recognized by a payment or approved accrual strategy. For loans, prepayment changes remaining schedules through a versioned recalculation. Restructure, write-off, waiver, and settlement each have explicit command and accounting mapping.

Support zero-interest family loans and manual fixed markup first. Rate-based schedules must state flat or reducing balance, day-count convention, rounding, compounding, payment timing, and final residual handling; these are not interchangeable formulas. Credit card accounts may have statement cycles and due reminders, but the card liability ledger is the sole balance source. Avoid creating a second debt principal for a card already tracked as an account.

## 20 Committees and rotating savings

Committees represent a household participant's rotating savings arrangement, not full custody accounting for every participant. committees contains organizer contact, currency, contribution amount, number of rounds, dates, expected payout round, and terms. committee_participants records relevant members and turns; committee_rounds records scheduled contribution and payout; committee_allocations links actual transactions. Irregular contributions and fees are explicit.

Use a dedicated committee position ledger account with a signed debit/credit position. Before payout, contributions debit committee position and credit cash. Payout debits cash and credits committee position; after an early payout, the credit position represents remaining funded obligation. Later contributions reduce that position. For a ten-round committee at 20000 each, total contributions 200000 and payout 200000 settle to zero; fees or losses are separately classified expenses.

Reports present a debit position as a committee asset and a credit position as a committee liability, once each, with remaining scheduled dues shown as commitments. The position account is excluded from liquidity. A simple reminder for a contribution does not post a duplicate loan or expense. Multiple household participants have distinct positions. Managing other households' collected funds, auction committees, bidding discounts, defaults, and organizer custody need separate approved extensions and are outside the initial committee release.

## 21 Goals sinking funds and reserved money

Use funds with purpose goal or sinking_fund, target amount, currency, target date, priority, status, optional linked accounts, and recurring contribution recommendation. fund_allocations is a versioned non-ledger reservation history, including reserve, release, consume, and adjustment. Goals are saved views and UX patterns over funds rather than separate financial balances.

Earmarking 10000 in an existing bank account reserves cash without generating income, expense, or a transfer. Moving 10000 to a savings account is a normal transaction; optionally link a reservation command in the same workflow without counting the same contribution twice. Spending reserved money posts the ordinary expense and consumes its reservation. Fund progress is funded reservation, not arbitrary manually increased saved_amount.

A reservation must identify backing account balances or an explicit unbacked planned state. Prevent active funded reservations exceeding eligible positive balances; if later spending depletes backing, expose the shortfall and reconcile reservations through policy. Available unreserved cash subtracts backed reservations once. Debt-funded and non-liquid assets do not qualify as cash backing. Currency conversion and aggregate reservations use the shared currency service.

Required periodic saving is remaining target divided by remaining scheduled contribution opportunities, rounded appropriately. When the target date has passed, show a shortfall rather than dividing by zero. Completing a target does not automatically spend or move money. Sinking funds can recur after consumption; preserve each funding cycle's history.

## 22 Assets and valuation history

assets stores asset type, description, ownership share, purchase details, serial/reference, warranty dates, linked ledger account if capitalized, lifecycle, and net-worth inclusion policy. asset_valuations is append-only by effective date with amount, currency, valuation source, method, confidence, and actor. asset_events captures purchase, maintenance, disposal, and warranty events; documents use universal links.

Small household equipment may be expensed and excluded from net worth by default. A tracked property or vehicle purchase posts an asset acquisition rather than ordinary consumption. Existing assets introduced at setup use Opening Equity if included in books. Ownership share applies once; a jointly owned external asset must not be recorded at full value if the household owns only a fraction.

Valuations are estimates by default and do not post income or cash. Market net worth replaces the asset's linked book balance with its latest eligible valuation; it does not add both values. Book net worth remains available from ledger balances. If formal revaluation is enabled later, it posts through Accounting against a configured reserve or gain account and remains separately labeled. Disposal posts proceeds, derecognition of book cost, and realized gain/loss. Maintenance usually posts expense and does not automatically increase valuation.

## 23 Investments and holding ownership

securities identifies instrument type, symbol, market, denomination currency, and quantity precision. investment_accounts is portfolio/custody metadata linked to existing accounts rather than a second cash ledger. holdings and investment_lots maintain quantity and cost allocation; investment_trades links buy, sell, dividend, fee, tax, and corporate action to transactions. investment_prices preserves timestamped manual or provider valuations.

Buy posts investment asset and any expense fees against cash; sell reduces cost basis and records realized gain/loss; dividends post income. Adopt one declared cost method, initially weighted average, and keep calculation version and trade ordering. Tax-specific lot methods are a future extension. Security splits change quantities and unit cost with no fictitious cash income. Opening holdings include exact quantity and cost with an opening journal.

Prices and unrealized gains are valuation data, not cash flow. Portfolio market value replaces its corresponding ledger carrying amount in market net worth. A property, gold holding, or business stake belongs to either the Assets valuation registry or the Investments holding registry through a unique valuation-owner link, never both. Price feeds are optional, fail gracefully, and display freshness; the product remains useful with manual prices.

## 24 Documents attachments and notes

documents owns metadata and a private blob: UUID, household, original filename, generated storage key, verified MIME type, bytes, checksum, sensitivity, upload state, creator, retention policy, and encryption metadata where applicable. document_links links one document to registered entity types and UUIDs. notes and note_links provide the same reusable entity attachment model with version history and visibility rules.

Use a controlled entity registry that maps entity_type to a resolver and policy. Generic links cannot have normal foreign keys to arbitrary tables, so the service MUST validate existence, household ownership, allowed entity type, and access on every link and read. This deliberately limited pattern is for documents, notes, and optional tag links; it must not become a generic substitute for financial foreign keys.

Uploads enter staging, undergo size/type checks, malware scanning where available, and optional preview conversion, then become ready. Documents in pending or quarantined state are not downloadable. Blob finalization and database records use compensating cleanup because filesystem writes are not database transactions. Reconciliation finds missing or orphaned blobs. Checksums verify integrity; cross-household deduplication is prohibited.

Store blobs outside public and serve them through authenticated policy-controlled downloads. Use safe download headers, attachment disposition for risky formats, random keys, and path traversal protection. Notes use plain text or tightly sanitized rich text; never render arbitrary user HTML. Permission on one link must not expose hidden linked entities or titles. Sensitive legal, identity, and property documents may require explicit documents.sensitive.read permission and reauthentication. Backups include files and decryption requirements.

## 25 Audit logging and workflows

audit_events is append-only: event UUID, household, actor/user or system principal, device, action, entity UUID, before/after permitted fields, reason, UTC timestamp, correlation ID, and optional IP with retention policy. Audit covers posting, reversals, changed mappings, permissions, exports, document reads of sensitive files, backups, restore, period locks, settings, and integration consent. Exclude passwords, session tokens, provider keys, and raw document content. Audit records commit with core financial changes.

Audit is a forensic business record; application logs are diagnostic. Owner UI offers filters and readable changes. Application-level append-only controls do not make records tamper-proof against a database administrator; a future signed hash chain or external log sink can increase assurance. Purging diagnostic logs cannot purge required accounting history.

| Aggregate | Stored lifecycle | Derived or separate state |
|---|---|---|
| Transaction | draft posted reversed superseded | Failed validation returns errors; retry state belongs to the command |
| Bill | draft open cancelled archived | unpaid partial paid; upcoming due overdue |
| Recurring rule | active paused completed cancelled | occurrence pending generated failed skipped |
| Obligation | draft active settled written_off cancelled | overdue and outstanding from allocations |
| Fund | active paused completed archived | backed amount shortfall percentage |
| Asset | active disposed archived | warranty timing and valuation freshness |
| Document | staged scanning ready quarantined removed | authorization and sensitivity |
| Job | queued running succeeded failed dead_letter | retry count and lease expiry |

Transitions are typed enums or constrained codes with centralized guards. Not every aggregate supports cancelled, deleted, or pending. Soft deletion is a retention mechanism for eligible records, not an alternate financial workflow. Remove unused drafts only under audit and retention policy.

## 26 RBAC and data visibility

Use roles, permissions, role_permissions, membership_roles, and optional membership_account_scopes. Roles belong to household memberships; a global technical administrator is a separate installation role. A spouse relationship does not itself grant a role. Default roles are Owner, Manager, Contributor, Limited Member, and Viewer; households may rename labels while permission keys remain stable.

| Capability | Owner | Manager | Contributor | Limited member | Viewer |
|---|---|---|---|---|---|
| Read authorized transactions | Yes | Yes | Yes | Own or scoped | Yes |
| Create drafts | Yes | Yes | Yes | Scoped | No |
| Post transactions | Yes | Yes | Granted | Scoped if granted | No |
| Reverse posted entries | Yes | Granted | No | No | No |
| Read household balances | Yes | Yes | Granted | No by default | Granted |
| Manage users permissions and locks | Yes | No by default | No | No | No |
| Backup export and restore | Yes | Separate grant | No | No | Separate export grant |
| Read sensitive documents | Separate grant | Separate grant | No by default | No | No by default |

Permission checks combine action, household membership, account scope, record ownership, sensitivity, and field visibility. Use keys such as transactions.create, transactions.post, transactions.reverse, accounts.balance.read, income.read, documents.read, documents.sensitive.read, reports.export, members.manage, periods.lock, backups.create, backups.restore. At least one active owner must remain; ownership transfer is explicit.

Limited entry should not reveal account balance through validation errors. A mixed-visibility split transaction is either hidden as a whole or served through a tested redacted view; initial release uses the simpler whole-record rule. Household-wide reports require full report permission; scoped reports are labeled and never reconstructed into hidden totals. Technical maintenance does not automatically grant household document browsing in the UI, although host administrators can access underlying data.

## 27 Settings localization dates and periods

Settings have four scopes: installation, household, user preference, and device preference. Typed definitions declare allowed scope, validation, default, visibility, and whether changes are audited. Household business rules cannot be overridden by personal preferences. Resolution for visual preferences is device → user → household → installation; business policy resolves household → installation only.

Installation settings cover private storage, logging, scheduler, integrations, enabled capabilities, and backup destinations. Household settings cover base currency, timezone, fiscal year, budget calendar, recognition policy, category defaults, and lock rules. User settings cover language, theme, date/number display, notifications, and widget layout. Device settings cover remembered input defaults and cache policy. Secrets belong in private configuration or protected credential storage, never an ordinary readable settings JSON table.

English is the first UI language; translation catalogs support Urdu and right-to-left layout. Use locale-aware number and currency formatting, pluralization, translated validation, and CSS logical properties. Database codes remain language neutral. Store personal names as entered, with Unicode search behavior tested. Core financial dates use Gregorian ISO dates; an optional alternate calendar is a display/input adapter, not a second accounting calendar.

Households default to Asia/Karachi but store an IANA timezone. Timestamps are UTC; effective_date, due_date, and period boundaries are SQL DATE and never shifted by viewer timezone. A user's timezone may change display of activity timestamps; financial deadlines follow household or explicitly stored schedule timezone. Recurrence stores local date/time and timezone with rules for ambiguous or nonexistent DST times. Reports use start inclusive and end exclusive boundaries.

financial_periods stores dated non-overlapping periods and lock metadata. Default is unlocked; optional soft close warns and hard lock blocks posting, reversing into, or modifying financial attribution within the period. Lock and posting commands share a period guard to prevent races. Reopening requires explicit permission, reason, and audit. A current-period correction may reverse a historic mistake without rewriting the locked period. Changing fiscal year affects future period definitions; historical periods remain stable.

## 28 Central calculations reports and dashboard KPIs

BalanceService, SpendingService, CashFlowService, BudgetAnalysisService, ObligationService, FundService, NetWorthService, InvestmentCalculationService, CurrencyService, and ForecastService are authoritative calculation boundaries. They accept household, authorization scope, dates, currency basis, and recognition policy; return typed results with freshness and provenance; and are consumed by UI, exports, widgets, search summaries, and AI tools.

All booked balances come from posted journal lines including reversing effects. No report independently sums transaction headers to calculate account balances. Spending uses attributed expense/income journal effects with split links. Cash flow classifies movements across the selected cash boundary; internal transfers are eliminated only when both ends lie inside that boundary. Cash flow is not income minus expense when loans, card settlements, investments, or asset purchases are present.

| KPI | Definition and exclusions |
|---|---|
| Total liquid funds | Eligible cash bank and wallet balances converted on the stated basis; excludes card limits non-liquid holdings and expected income |
| Available unreserved cash | Liquid funds minus backed reservations and explicitly defined due commitment coverage; show each deduction and never subtract the same obligation twice |
| Income and spending | Posted classified income and net expense for the selected dates; excludes transfers loan principal earmarking and valuation changes |
| Budget remaining | Planned amount plus eligible rollover minus actual net spending; commitments shown separately |
| Bills due | Unsettled confirmed bill amounts due in the selected horizon plus unique loan or committee due projections |
| Debt and receivables | Liability or receivable principal positions reconciled to active allocations; scheduled future finance cost shown separately |
| Net worth | Included assets less liabilities; book and market views explicitly separate and each holding counted once |
| Goal progress | Backed reservations divided by target; unbacked plans shown separately |
| Savings rate | Income minus consumption expenses divided by income when income is positive; define one-off gain inclusion and do not label this cash savings |
| Forecast lowest cash | Current eligible cash plus unique projected inflows minus outflows over the horizon with assumptions and stale-source flags |

Initial reports include income/expense, category spending, cash flow, balances, budget/actual, family/project spending, debts/receivables, and funds. Net worth and investment reports activate with those modules. Every report has scope, date range, currency, calculation definition, posted-versus-planned distinction, and drilldown to source. CSV exports escape spreadsheet formula prefixes; export permission is separate and export events are audited.

Dashboard widget definitions are registered in code with permission and feature requirements. user_dashboard_widgets stores order, visibility, and bounded configuration. Widgets include cash, monthly spending, due bills, budget progress, goals, debt, recent transactions, net worth, forecast, and backup health. Inactive modules do not produce empty misleading totals. A financial health score is future and must expose its formula, input completeness, and assumptions rather than claim an objective credit rating.

## 29 Search notifications and financial timeline

SearchService provides a scoped API for transactions, bills, contacts, assets, funds, projects, and document metadata. Start with indexed exact fields and bounded text matching; evolve to full-text indexes per certified engine. Authorization is applied before pagination, snippets, counts, and suggestions. Search hidden documents neither by title nor OCR content. Search indexes are rebuildable, with tombstone processing and no financial authority.

Notification rules generate durable in-app notifications from committed events and scheduled checks. notification_deliveries stores per-user/channel delivery, deduplication, retry, and read state. Support bills, budget thresholds, low cash, installment reminders, goals, warranties, unusual expenses, failed jobs, and backup failure. Threshold crossing should notify once per policy interval; edits and retries must not flood users.

In-app notification center is available locally. Browser notifications depend on capability and consent. Background web push, email, and messaging need configured services and connectivity; they are not guaranteed while the PC is off. External messages are optional and enabled explicitly, with quiet hours and sensitivity controls. A timeline merges actual transactions with labeled planned occurrences using stable source keys. Forecast, calendar, and notifications consume the same occurrence projection so one committee installment is not counted three times.

## 30 API contracts validation and concurrency

Use /api/v1 with JSON request and response schemas and OpenAPI documentation. Resources expose UUIDs and ISO dates; decimal strings and enum codes remain machine-stable. Typical endpoints are GET /accounts, POST /transactions, PATCH /transactions/{uuid} for drafts, POST /transactions/{uuid}/post, POST /transactions/{uuid}/reverse, GET /bills, POST /bills/{uuid}/settlements, GET /reports/cash-flow, and POST /backups. Command responses return source and journal UUIDs, entity version, and correlation ID.

Errors use a stable envelope with code, readable message, field_errors, and correlation_id. Use 401 for unauthenticated, 403 for denied action, 404 for inaccessible entity without disclosing existence, 409 for version or idempotency conflicts, 422 for invalid business input, and 429 for rate limiting. Do not expose stack traces. Cursor pagination is preferred for changing transaction feeds; deterministic sort and maximum page sizes are required.

Mutation requests carry expected row_version or If-Match. Retryable creation, posting, settlements, uploads, and import acceptance use Idempotency-Key scoped to actor/household/command and a canonical payload hash. Retain financial command results long enough to cover replay windows; economic source uniqueness remains permanent even after an API retry record expires. A stale draft edit returns conflict with current version for review, not silent last-write-wins.

Transport validation checks formats and payload bounds. Domain validation checks balances, scope, compatible categories, dates, locks, allocations, and state transitions. Repository queries are scoped and parameterized. PATCH allowlists fields; mass assignment cannot alter household, actor, posting state, or source ownership. Keep API version changes additive where possible; breaking changes require a new version and migration/deprecation plan.

## 31 Events listeners scheduler and background work

Persist domain events in outbox_events in the same transaction as the business change. Events include event_uuid, household_uuid, aggregate_uuid, aggregate_version, occurred_at, schema_version, correlation_id, and a minimal payload. TransactionPosted, TransactionReversed, BillSettled, FundReservationChanged, DocumentReady, and PeriodLocked are representative names. Version payloads and support old event readers during rollout.

An outbox dispatcher delivers at least once. Listeners deduplicate using event UUID and listener identity. Secondary listeners rebuild projections, check thresholds, deliver notifications, index search, and create sync change records. Accounting and critical allocation/audit invariants remain synchronous. Replaying events must not send every old notification again or create another journal.

Windows Task Scheduler launches a short PHP scheduler tick, initially every minute, and scheduled backup jobs. A separate bounded PHP CLI worker leases queued jobs. Queue tables store kind, safe payload reference, attempts, available_at, lease expiry, error code, and deduplication key. Use backoff, maximum attempts, dead-letter visibility, graceful shutdown, and idempotent handlers. Do not keep a browser tab open as the scheduler.

Jobs handle recurrence, due reminders, backup, OCR, preview conversion, optional price updates, and sync. Missed ticks catch up within limits after restart. Health status records last successful tick, worker heartbeat, overdue queue, and last backup result. Critical scheduling delays appear in the owner dashboard. Scheduler timezone logic uses the Clock abstraction, not host default time.

## 32 Logging and operations

Structured application logs include UTC time, severity, event code, correlation ID, module, safe actor identifier, and execution context. Mask financial descriptions, document names, contact details, credentials, and provider tokens by default. Separate request errors, worker failures, security events, and restore operations with rotation and bounded retention. Log level is configuration, not a UI toggle that exposes secrets.

Maintain health checks for database, storage, disk space, scheduler, queue, schema compatibility, TLS, and backup freshness. A user sees an actionable message and correlation ID; an owner can obtain a sanitized support bundle. Debug mode is off in household use. Recovery runbooks explain service restart, failed-job replay, certificate renewal, missing blobs, corrupted backup, and database migration failure.

## 33 Security and sensitive storage

Authenticate with server-side sessions and password_hash using Argon2id where available or a supported fallback. Regenerate session IDs on login and privilege changes; enforce HttpOnly, Secure, and appropriate SameSite cookies, idle and absolute expiry, login throttling, and audited local recovery. No default shared admin password. Recovery must work without email through an owner-held recovery method established at setup.

Use CSRF protection on cookie-authenticated mutations, same-origin policy and restrictive CORS, prepared SQL, contextual output escaping, safe file handling, content security policy, and dependency updates. Validate upload MIME using content, prohibit executable delivery, limit size and processing resources, and prevent archive traversal or decompression bombs. Provider URL fetches are allowlisted to avoid SSRF. APIs authenticate every read and write, including download and search.

The database application user is not root and receives only needed privileges; migration credentials are separate when practical. Bind database administration to local access. Apache exposes public only, directory listing is off, phpMyAdmin is restricted, and Windows firewall permits only intended LAN access. TLS protects LAN traffic; full-disk encryption and restrictive Windows ACLs protect the host. Application encryption of sensitive blobs uses authenticated encryption and keys outside the database, with recovery procedures. Encryption does not protect against an already authorized compromised host administrator.

Secrets, household data, backups, uploads, and logs never enter Git. Document caches and service workers initially cache only public static application assets; authenticated financial responses are network-only with appropriate no-store behavior. Clear scoped client state on logout, household switch, and permission revocation. Offline sensitive caching is a separately reviewed capability with encryption, retention, and lock-screen requirements.

## 34 Backup restore and portability

Backup is an encrypted versioned archive of a consistent database snapshot, document blobs, required non-secret configuration, and a manifest containing app version, schema version, installation UUID, household UUIDs, file checksums, creation time, encryption method, and supported restore versions. Do not package live database credentials or cloud tokens by default. Key recovery instructions are a required part of setup; an unreadable encrypted archive is not a usable backup.

Initial policy is daily local encrypted backup, weekly copy to another disk or selected destination, and monthly retained archive. Defaults may retain 7 daily, 4 weekly, and 12 monthly copies, configurable with disk-space warning. A backup on the WAMP disk alone does not protect against disk loss. Automatic backup tasks record completion and verification; copy success is not equivalent to restore success.

Use a maintenance write barrier during snapshot capture or another tested database-and-blob consistency strategy. Finish or reconcile staged files first, capture a transactional database snapshot with all tables on InnoDB, include immutable referenced blobs, then checksum and encrypt. DDL and restore are excluded during capture. Storage and backup credentials are protected independently.

Restore preflight authenticates the owner, checks archive type/version/checksums, estimates disk requirements, and refuses unsupported newer schemas. Restore into staging database and storage, validate references, migration compatibility, balanced journals, totals, and blobs, then take a pre-restore backup and switch under maintenance mode. Do not merge a full backup blindly into live books. On failure preserve the original installation and report a clear recovery path. Revoke old sessions and record the restore in a retained external operation log.

A same-install disaster recovery retains logical household and entity UUIDs but advances a restore epoch to invalidate stale sync cursors. A cloned independent installation receives a new installation UUID and sync identity; it does not impersonate the source. Initial release supports full installation restore; household-only portable export/import is a later explicit mode with UUID conflict rules. Backup archives are not sync transport. A release must include a demonstrated fresh-machine restore, not just a created ZIP.

## 35 Migrations configuration feature flags and installation

schema_migrations records ordered migration ID, checksum, applied timestamp, app release, and result. Migrations are committed, reviewed, and executed once under a migration lock. Seed reference data with stable keys; demo transactions exist only in fixtures. No untracked phpMyAdmin schema edits in deployed databases. MySQL and MariaDB DDL may implicitly commit, so rollback assumptions must be tested; failed migrations use backup recovery or forward repair.

Use expand-and-contract migrations for existing data, with preflight, backup, compatibility checks, and data transformation verification. A down migration is not a substitute for financial recovery. Preserve Git history, tag releases, and keep application rollback separate from database rollback. Refuse startup when the schema is too new or too old for the running code, except for a guarded upgrade UI.

config contains non-secret defaults; environment/private configuration contains host-specific values and secrets; household settings contain business choices. feature_flags declares availability by installation and household and can hide unfinished modules. A disabled feature cannot orphan journals or suppress historic reports. Disabling a module stops new operations while maintaining required settlement and read behavior. Never use a flag to bypass permissions or accounting validation.

The installer verifies runtime, chooses storage, creates a non-root application DB user through an authorized setup flow, runs migrations, establishes installation identity and owner, creates household and base currency, seeds chart/categories, configures timezone and backup recovery, checks scheduler/TLS, and locks setup routes. Detect existing installations; never drop or reseed them silently. Fresh household creation produces zero business records apart from deliberate defaults.

Maintain app semantic version, schema version, API version, and event/sync protocol version separately. License/install readiness consists of protected installation metadata, optional signed license claims, expiry/grace behavior, and offline verification hooks. Initial local operation should not require internet activation. License restrictions must retain access to historical data, export, backup, and recovery; no destructive expiry behavior. Commercial licensing policy and branding decisions remain product configuration rather than accounting code.

## 36 Sync readiness and identity

Initial WAMP is the authoritative writer and all browser commands commit there. installation_uuid identifies a server installation; device_uuid identifies a registered client/browser profile, not a secure hardware fingerprint; user_uuid identifies the actor. Clearing browser storage creates a new device identity. Devices have registration, trust state, last_seen_at, and revocation. Never use a device UUID as authentication.

Prepare portable UUID references, entity row_version, origin identity, immutable journal batches, tombstones, a monotonic server change sequence, and per-peer sync cursors. Per-entity sync_status is inadequate because one entity may be delivered to several peers at different times. sync_peers, sync_cursors, sync_conflicts, and change_feed remain infrastructure records. Tombstones cannot be purged until a documented peer acknowledgment/retention policy is satisfied.

Future disconnected clients queue immutable command IDs and draft intent, not authoritative journals. The server validates and posts commands using current permissions and versions; rejected or conflicting commands remain visible for review. No last-write-wins for amounts, posted entries, account balances, debt settlements, or locks. Nonfinancial preferences may use a simpler version policy. Attachments synchronize separately with checksums, resumable transfer, and permission checks.

Cloud replication should initially mirror complete committed household journal batches from one authority. Concurrent independent WAMP/cloud posting is a separate distributed-system feature requiring authority election, conflict policy, migration, revocation, and atomic batch validation. UUIDs and updated_at alone do not solve it. Sync must distinguish created, updated, tombstoned, reversed, and permission-revoked records, tolerate replay and out-of-order transport, and rebootstrap after restore epochs. Initial release does not claim offline writes or cloud synchronization.

## 37 AI OCR imports and integration boundaries

Define provider-neutral adapters for OCR, text parsing, model analysis, prices, bank files, and messaging. All are optional and configured privately. AI is a proposal layer; it never writes ledger lines, changes permissions, or posts an inferred transaction directly. A parsed proposal includes amount, currency, account/category candidates, date, contact, source, confidence, and unresolved fields. User confirmation invokes the same transaction command and audit path as manual entry.

Receipt OCR extracts structured evidence with field confidence and links to the original document. Duplicate detection uses content hash and reviewed merchant/date/amount candidates; uncertain duplicates are flagged rather than silently dropped. Imports use staging rows with raw provenance, mapping, validation, deduplication keys, and accept/reject status. Acceptance posts only reviewed rows and is idempotent. Detect actual file content, impose bounds, and never execute spreadsheet formulas or macros.

Cloud processing requires explicit household consent and per-operation visibility of what leaves the machine. Sensitive documents are excluded by default; local OCR may remain available. Redact unnecessary identifiers and enforce retention/deletion policies for provider results. AI report answers call read-only authorized calculation tools, show date/scope and source drilldowns, and label forecasts and assumptions. Natural language questions cannot expand the user's data access. Expense health suggestions and affordability forecasts are planning features with transparent calculations, not autonomous investment transactions.

## 38 Mobile navigation and reusable UI standards

Use five persistent primary destinations: Home, Money, central Quick Add, Plan, More. Home shows configurable summary and urgent items. Money owns Accounts, Transactions, Income, and Expenses as filters over the same transaction model. Plan owns Bills, Budget, Goals, and Sinking Funds. More exposes Debts, Committees, Assets, Investments, Family, Projects, Reports, Documents, Search, Backup, and Settings according to permissions and feature flags.

Quick Add begins with Expense, Income, Transfer, Borrow, Lend, and other enabled actions. Routine expense requires amount, category, and account; date defaults to household today and all optional fields remain available. Remember valid user defaults, offer recent/favorite categories, numeric input appropriate to currency, searchable account selectors, receipt camera/file input, and split editor. A simple saved entry should need no more than one confirmation after required fields; save state must make retries safe. Never show success until the server confirms posting or clearly identifies a saved draft.

Body/input text SHOULD be at least 16 CSS pixels at normal zoom. Primary touch targets SHOULD be at least 44 by 44 CSS pixels with spacing. Verify 320, 360, 390, 412, and 768 pixel widths and common desktop sizes. Pages must not require horizontal scrolling for routine entry, pinch zoom to read, hover to discover actions, or keyboard dismissal to find Save. Tables become responsive rows/cards or use a clearly bounded data-scroll region for specialist reports. Sticky navigation and actions respect safe areas and the software keyboard.

Use semantic landmarks, visible labels, keyboard navigation, clear focus, screen-reader errors, contrast-tested themes, reduced-motion support, and textual chart alternatives. Color alone never conveys debt, status, or warnings. Confirm destructive or financially consequential actions with the affected amount/date and reason; use undo only when the underlying reversal semantics are clear. Keep empty states, loading, offline/server-unreachable, validation, conflict, denied, and partial-data states explicit.

Reusable components include app shell, bottom navigation, desktop sidebar, MoneyInput, CurrencyText, DateInput, AccountPicker, CategoryPicker, ContactPicker, MemberPicker, SplitEditor, transaction row, balance card, progress indicator, status label, document uploader/viewer, filter bar, confirmation dialog, notification list, empty/error state, accessible chart wrapper, and responsive report table. Define CSS tokens for spacing, type, color, radius, elevation, and focus. Components render shared domain DTOs and never calculate their own authoritative totals.

On desktop, the same routes and components adapt to a sidebar, wider tables, multi-column widgets, keyboard shortcuts, and side-by-side entry/detail. Business logic and forms remain shared. PWA manifests, icons, install guidance, and static shell caching are included when trusted HTTPS is available. Installation prompts and offline behavior are browser/device-specific and tested; an installation request event does not prove a finished home-screen installation.

## 39 Recommended database domain map

The map lists recommended entities, not a requirement to create every future table in the first migration. Core tables are built when their release starts; reserve module names and interface contracts now. Tables carrying money use exact types and scope conventions above.

| Domain | Recommended tables | Key relationship or constraint |
|---|---|---|
| Installation | installations devices schema_migrations feature_flags license_claims | New clone identity and migration checksum |
| Identity | users sessions recovery_tokens | Credentials private; tokens hashed and expiring |
| Household | households household_memberships household_members | Membership unique per household and user |
| Authorization | roles permissions role_permissions membership_roles membership_account_scopes | Scope belongs to membership |
| Currency | currencies exchange_rates | Rate direction and dated source explicit |
| Accounting | ledger_accounts journal_entries journal_lines financial_periods | Exact balance; unique posting source; same household FKs |
| Money | accounts transactions transaction_splits transaction_funding_legs command_receipts | Account links one ledger; draft versions and command uniqueness |
| Classification | categories tags contacts transaction_tags split_tags | Stable category mapping and cycle protection |
| Projects | projects project_members | Household event is a project subtype |
| Bills | bills bill_lines bill_payment_allocations | Posted settlement allocation cannot exceed source or obligation |
| Recurrence | recurring_rules recurring_rule_versions recurring_occurrences | Unique rule revision and occurrence key |
| Budgets | budgets budget_periods budget_lines budget_revisions | Explicit periods and counting precedence |
| Obligations | obligations obligation_terms obligation_installments obligation_allocations | Borrowed/lent direction and linked ledger reconciliation |
| Funds | funds fund_cycles fund_allocations | Reservation history is non-ledger |
| Committees | committees committee_participants committee_rounds committee_allocations | One position per household participation |
| Assets | assets asset_valuations asset_events | Append valuation history and one owner per holding |
| Investments | securities investment_accounts holdings investment_lots investment_trades investment_prices | Trade links transaction; cost method version |
| Documents | documents document_links notes note_links | Controlled link resolver and private blob keys |
| Notifications | notifications notification_rules notification_deliveries | Unique rule/source/recipient delivery keys |
| Presentation | user_dashboard_widgets user_preferences device_preferences | Code-owned widget definitions and typed preferences |
| Operations | jobs outbox_events listener_receipts audit_events backup_runs restore_runs | Append-only events and leased idempotent jobs |
| Sync later | sync_peers sync_cursors change_feed sync_conflicts | UUID references sequence and restore epoch |
| Integration later | import_batches import_rows extraction_proposals integration_consents | Review before acceptance; provenance and deduplication |

Avoid separate expenses, incomes, bill cash payments, loan cash payments, generic current balances, asset_documents, and goal bank balances that duplicate these authoritative models. Reporting caches and search indexes are derived and rebuildable, with source sequence and calculation version.

## 40 Build phases priorities and release gates

| Phase | Deliverables | Exit condition |
|---|---|---|
| 0 Foundation | Installer migrations scope identity RBAC Money chart journal transactions splits corrections audit outbox UI shell basic balances backup skeleton | Foundation acceptance tests pass with real database and mobile UI |
| 1 Household essentials | Accounts categories contacts members documents income expense transfer card purchases and payments budgets bills partial settlements recurrence basic debt/lending funds dashboard reports notifications verified restore | Routine household lifecycle works end to end with reconciled totals and restore drill |
| 2 Household breadth | Committees assets net worth investments projects rich forecast calendar imports enhanced document protection | Cross-module scenarios preserve accounting and valuation ownership |
| 3 Assisted workflows | OCR and AI proposals optional price feeds anomaly rules richer family allowances | Consent review deduplication authorization and provenance validated |
| 4 Disconnected and hybrid operation | Offline draft/command queue sync conflict UI cloud mirror optional mobile wrapper | Replay revocation restore epoch convergence and no duplicate financial effects demonstrated |

Some small project and PWA components may be pulled into Phase 1 if they do not delay the accounting and recovery gates. RBAC, household isolation, private uploads, concurrency, audit, and backup design are foundation concerns, not features postponed until family sharing. Basic lending is part of obligations in Phase 1 rather than a second lending engine introduced later. Net-worth correctness is designed from the start even if asset and investment UI arrive later.

Prioritize vertical slices: household setup → opening balances → expense/income/transfer → corrections → reports → backup restore; then bills and obligations → recurring generation → budgets/funds; then valuations and committees. Do not build every future screen or placeholder table before validating a complete financial slice.

## 41 Non goals and anti patterns

The initial product does not provide business inventory, payroll, invoicing, brokerage execution, bank custody, automated tax filing, full organizer accounting for external committee members, unattended AI posting, multi-master cloud/WAMP accounting, or guaranteed notifications while the host is unavailable. Native Android/iOS applications and production cloud hosting are future delivery modes.

Prohibited patterns include floating-point money, mutable posted journals, separate movement engines per module, balances stored without a ledger, budget actuals calculated from header totals, goals treated as extra assets, loan receipts treated as income, card settlements counted as new expense, market valuations added to book values, permissions enforced only by hidden buttons, files stored under public, schema changes without migrations, hard-coded PKR or timezone, async journal generation, phone offline success messages before synchronization, and restoring backups by blindly appending records.

Avoid microservices, event sourcing of every entity, a custom query language, unlimited plugin execution, and a universal polymorphic financial table in the first release. A typed modular monolith with documented extension contracts is sufficient. Do not create advanced functionality merely because the base contains a hook.

## 42 Foundation acceptance criteria

Foundation approval requires evidence from the application and real certified database, not only syntax checks or static mockups. The checklist below is a release gate and should be tracked in the repository with test evidence and known limitations.

1. A fresh Windows/WAMP installation passes preflight, creates an owner and household, seeds only deliberate defaults, locks installer routes, and exposes only public through Apache. A second household starts with zero business records.
2. Two households and restricted memberships cannot access each other's records through guessed UUIDs, numeric IDs, API writes, foreign references, search, reports, widgets, downloads, or exports. Cross-household references also fail at database or service boundary.
3. Exact decimal fixtures cover PKR plus a zero-decimal and three-decimal currency, a foreign-currency transfer with fee, bounded rounding, invalid scale, and overflow. PHP and API round trips preserve decimal strings.
4. Opening balances, salary, split expense, cash transfer, borrowing, lending, principal-plus-fee repayment, card purchase/payment, refund, asset purchase, and reversal produce expected balanced journals and report classification.
5. Concurrent double-click, network timeout/retry, worker replay, and duplicate import acceptance result in one economic posting. A stale draft mutation conflicts. An injected failure between journal and allocation steps leaves no partial commit.
6. Posted journals cannot be edited or hidden by soft deletion. Correction retains original, reversal, and replacement with matching allocation reversals. Locked periods block backdated mutation and lock/post races; authorized current-period correction remains traceable.
7. Bill partial payments and debt allocations cannot exceed their source or outstanding amount. Refund/reversal restores outstanding obligations. Planned bills and actual payments never appear twice in expense or forecast totals.
8. Budget actuals reconcile to split expense journals. Parent/child category rules avoid duplicate counting. Reservations reduce unreserved cash without changing cash or net worth; insufficient backing is visible.
9. Every dashboard balance matches the corresponding report for the same scope/date/currency. Journal totals reconcile, and projections can be deleted and rebuilt to the same values.
10. Recurrence handles month end, missed ticks, paused rules, revisions, and duplicate ticks without duplicate targets. Outbox delivery is replay-safe, failures enter visible retry/dead-letter states, and scheduler health is available.
11. Uploads cannot execute, escape private storage, expose hidden entities, or serve quarantine content. Missing/orphaned file checks work. Sensitive access and financial changes create safe audit events without secrets.
12. An encrypted backup is restored to an isolated fresh installation; journals, entity UUIDs, allocations, blob checksums, and report totals match. Wrong key, corrupted archive, unsupported schema, and insufficient space fail without harming the live installation.
13. The application is readable at normal zoom and operable with touch and keyboard at 320/360/390/412/768 pixel widths and desktop. Entry, splits, attachments, validation, bottom navigation, and on-screen keyboard produce no clipped controls or routine page overflow.
14. Session expiry, CSRF, login throttling, escaping, unauthorized mutation, debug-off errors, and logout/household-switch cache clearing are verified. Secrets and real data are excluded from source control.
15. Supported MySQL and any advertised MariaDB version pass migrations and core financial integration fixtures. App/schema incompatibility fails safely. Database changes have migration checksums and a recovery plan.
16. Trusted HTTPS is tested on a real phone before claiming PWA service-worker/install capability. Without the later offline release, loss of server connectivity produces an honest unavailable or draft state and never a fabricated posted success.

## 43 Future extensibility map

| Future need | Existing extension boundary | Additional work before release |
|---|---|---|
| Urdu and additional locales | Catalogs locale formatting logical CSS | Translation RTL font input and screen-reader QA |
| New account or transaction subtype | Account metadata posting strategies | Mapping fixtures permission and report classification |
| Rich loan calculations | Versioned obligation terms calculator interface | Rate conventions regulatory/tax requirements if applicable |
| Accrual household books | Journal engine bill strategies | Recognition migration payable control and labeled reports |
| Bank statement import | Staging proposals command acceptance | Parser mappings deduplication matching and reconciliation |
| OCR and AI | Private documents provider adapters authorized tools | Consent review field confidence and source evidence |
| Automated prices | InvestmentPriceProvider valuation history | Provider credentials freshness outages and rate limits |
| Offline phones | UUID commands row versions device identity | IndexedDB security retry queue conflict handling and revocation |
| Cloud backup and sync | Archive interface change feed per-peer cursors | Encryption authentication restore epochs and convergence tests |
| Mobile wrapper | Versioned API responsive shell | Secure authentication notifications storage and store packaging |
| More household events | Projects and split dimensions | Templates calendar UX and scoped budget rules |
| Plugins and commercial editions | Module contracts feature flags license metadata | Signed trusted modules compatibility licensing and support policy |
| Advanced analysis | Shared calculation services provenance | Explainable rules input quality assumptions and user controls |

## 44 Canonical change control and implementation handoff

Store this specification beside the source and assign one product owner for decisions. Development begins with schema diagrams, OpenAPI contracts, permission policies, journal fixtures, and a restore runbook derived from this document. Each feature should cite the section it implements and state any deviation before code is merged.

Record architecture decision records for changes to money precision, recognition mode, ledger/category mapping, valuation ownership, authority/sync model, document encryption, supported engines, and correction semantics. A change includes data migration, backward compatibility, affected reports, security impact, and acceptance evidence. Update the canonical specification and version together; loose chat suggestions do not silently override it.

The recommended v1 decisions are actionable defaults rather than unresolved architecture questions. Setup-time choices are household name, owner, base currency, timezone, fiscal year, storage/backup location, and recovery method. Optional cloud services, licensing policy, OCR provider, and notification channels are disabled until configured. This keeps the foundation complete while allowing later modules to be added through deliberate contracts and migrations.

## 45 Source notes and technical references

Product source: Personal Finance App Features, referenced ChatGPT conversation 6acb4df6-7cb0-83e8-a6b0-4f91fbc3c1c7. This document consolidates the accessible discussion and the explicit scope in the current request; the earlier concept notes are superseded by the decisions in this version.

Technical references checked on 11 October 2026 support the runtime and browser constraints below. The architecture, accounting policies, precision, schema, and release phases are specification decisions, not claims that upstream documentation mandates this exact design.

• PHP supported versions — https://www.php.net/supported-versions.php — choose a maintained patched PHP 8.x branch; 8.4 and 8.5 are the recommended implementation baseline.
• MySQL exact numeric types — https://dev.mysql.com/doc/refman/8.4/en/fixed-point-types.html — DECIMAL provides exact fixed-point storage for monetary values.
• MDN secure contexts — https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Secure_Contexts — loopback localhost is distinct from a phone visiting an HTTP LAN address; deploy trusted HTTPS for phone PWA capabilities.
• MDN using service workers — https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers — service-worker use requires a secure context and a deliberately configured cache lifecycle.

Document status: master specification prepared for development. No application implementation, WAMP deployment, live database migration, or cloud synchronization is claimed by delivery of this document.

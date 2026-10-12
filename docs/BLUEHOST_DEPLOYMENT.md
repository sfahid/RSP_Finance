# Ready Personal Finance 0.2.0 — Bluehost / Readysols deployment

This package is prepared for Bluehost shared hosting. It has not been uploaded or activated on Readysols. It contains application code only, with no household records, credentials or local database export.

## Package layout
- `readyfinance-private/`: application, migrations, CLI tools and private storage. Upload alongside `public_html`, outside every domain's document root.
- `public_html/finance/`: public entry point, assets and PWA files. Upload as a new folder; do not replace the existing Readysols site.
- Suggested address: `https://YOUR_READYSOLS_DOMAIN/finance/`. The actual domain has not been supplied. For a subdomain, point its document root at this same finance directory and set base_path to an empty string.

## Installation
1. Take a hosting backup. Create an empty dedicated MySQL database and database user in cPanel. Bluehost may prefix their names with your account name. Grant this user all privileges on this dedicated database for initial schema installation only.
2. Choose maintained PHP 8.3 or newer with PDO MySQL, BCMath, OpenSSL, Fileinfo and Mbstring. Check both web and command-line PHP versions. Enable a valid SSL certificate for the chosen address; use HTTPS without bypassing certificate warnings.
3. Upload the two directories in the package as described above. The included public index.php assumes the private directory is `/home/CPANELUSER/readyfinance-private` and the public directory is `/home/CPANELUSER/public_html/finance`. If your hosting uses a different layout, adjust the single bootstrap path in public index.php. Never upload the private directory beneath public_html.
4. Copy private `config/bluehost.example.php` to `config/local.php`. Fill in the actual database DSN/user/password. Set base_path to `/finance` for the proposed subfolder, or empty for a dedicated subdomain. Set require_https=true and allow_web_setup=false. Generate installation_uuid using `php -r 'require "bootstrap.php"; echo \ReadyFinance\Core::uuid(), PHP_EOL;'` from the private directory and paste it into the config. Keep configuration private; do not send credentials in chat.
5. From the hosting terminal/SSH, run `php bin/preflight.php`, then `php bin/install-hosted.php` from the private directory. Installation refuses a nonempty database. If interrupted after DDL, use a fresh empty dedicated database rather than overwriting tables. Do not run the WAMP installer on shared hosting: it attempts CREATE DATABASE / CREATE USER.
6. Create the owner privately in the hosting terminal. Supply RPF_OWNER_NAME, RPF_OWNER_HOUSEHOLD, RPF_OWNER_EMAIL and RPF_OWNER_PASSWORD as temporary environment variables, then run `php bin/create-owner.php`. Use a password of 12–72 characters. On Bash, use `read -r -s RPF_OWNER_PASSWORD; export RPF_OWNER_PASSWORD` to avoid typing the password into command history, then unset all four variables after the command. No public web installer or default account is shipped. If terminal/SSH is unavailable, ask the hosting administrator to run these commands; do not create a public installer script.
7. Reduce the database user's privileges to SELECT, INSERT, UPDATE plus DELETE for login_attempts (DELETE on the dedicated database if cPanel cannot grant table-level privileges). Re-enable DDL only during reviewed migrations, using bin/migrate.php with the maintenance user's private RPF_DB_ADMIN and RPF_DB_ADMIN_PASSWORD environment values.
8. Private config permissions should be 600; private storage directories 700 where supported by Bluehost's PHP execution user. PHP must be able to write its own storage. Public directories normally 755 and files 644. Confirm `/finance/config/local.php`, `/finance/storage/` and `/finance/bootstrap.php` do not expose private content. Set display_errors=Off, log_errors=On, upload_max_filesize=10M, post_max_size=12M. Keep error logs outside public_html.
9. Configure an hourly cPanel cron job using the hosting account's PHP binary: `/PATH/TO/php /home/CPANELUSER/readyfinance-private/bin/scheduler.php >> /home/CPANELUSER/readyfinance-private/storage/logs/scheduler.log 2>&1`. Confirm the binary version and rotate logs. This generates bills; it never assumes they were paid. Automatic backup scheduling remains planned.
10. Open the HTTPS address, sign in and verify accounts, a small test expense/reversal, a document download, backup creation and recurring processing in a separate staging installation before using real records. Download encrypted backups regularly and test restoration into an isolated target. The current restore CLI is designed to provision a new database and may require hosting-administrator privileges; do not assume shared-hosting restore works without those privileges.

## Install on a phone
- Android: open the HTTPS app address in Chrome and tap **Install mobile app**, or select Install app / Add to home screen in Chrome's menu.
- iPhone/iPad: open the address in Safari, tap Share → Add to Home Screen, and enable Open as Web App where offered.
- Desktop Chrome/Edge can also offer installation. The exact prompt depends on browser/device support and eligibility. The help button does not prove an installation happened.
- This is an installable PWA, not an APK or an App Store submission. It opens in its own app window. Financial access requires connectivity. Offline navigation displays a connection screen. Public CSS/scripts/icons are cached; financial API responses, session HTML, documents and backups are never cached by the service worker.
- To update, upload the release assets and service worker together. Existing installed apps adopt the new worker when their old app windows close and reopen. A later release must use a new cache version.

## Local versus hosted data
The hosted installation starts empty. The local WAMP and hosted versions are separate installations; there is no live synchronization. This package does not transfer local household data. Migrating existing data requires an explicitly planned backup/restore procedure.

## Release validation
PHP and JavaScript syntax, service worker routing/cache exclusions, icons/manifest integrity and local browser install guidance are checked in this release. All 33 isolated accounting checks passed again. Hosted preflight, empty schema install, nonempty database refusal, private owner creation, repeat-owner refusal and absence of financial seed records passed against an isolated local MySQL database. All 33 isolated accounting checks passed again. Hosted preflight, empty schema install, nonempty database refusal, private owner creation, repeat-owner refusal and absence of financial seed records passed against an isolated local MySQL database. Physical Android/iPhone installation and Bluehost deployment are not verified. The account's PHP extensions, SSL, filesystem permissions, database version, cron access and restore privileges must be checked on the destination.

## Hosting references
- PHP selection: https://www.bluehost.com/help/article/php-config
- Subdomains: https://www.bluehost.com/help/article/subdomains
- Document roots: https://www.bluehost.com/help/article/bh-document-root-changes

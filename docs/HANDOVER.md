# Technical handover: deployment and operations

For the developer or IT person who puts the site live and looks after it.

## 1. Requirements

- Any shared hosting with **PHP 8.1+** (8.3 recommended) and the `pdo_sqlite`, `openssl`, `mbstring` and `curl` extensions. Hostinger Premium/Business plans have all of these.
- An **SSL certificate** for www.imaraafrika.org. It is free on Hostinger; switch it on before collecting any data.
- No database server is needed: the data lives in one SQLite file.

## 2. Folder layout on the server

Only `public/` may be reachable from the web. Everything else sits **one level above** it:

```
/home/USER/
├── public_html/        ← upload the CONTENTS of  public/
└── imara/              ← upload  app/  config/  tools/  docs/   (create  imara/data/  empty)
```

The PHP files look for the app at `../../app/` relative to `public_html/api/` and `public_html/admin/`. With the layout above that means `/home/USER/app`. Either:

- put `app/`, `config/`, `tools/` and `data/` directly in `/home/USER/` (simplest, and it matches the paths in the code), **or**
- keep them in `/home/USER/imara/` and change the single `require` line at the top of each file in `public_html/api/` and `public_html/admin/`.

`app/`, `config/` and `data/` each contain a `.htaccess` that denies web access. Keep them, as a second layer of protection.

**Never upload** `app/config.local.php`, `data/` or `dev/` from your own computer. The server gets its own config and its own empty database.

## 3. First-time setup on the server

Using SSH (Hostinger: hPanel → Advanced → SSH Access):

```bash
cd ~
php tools/setup.php --base-url=https://www.imaraafrika.org
```

This creates `app/config.local.php` with a new **encryption key**, creates the database, and asks for the first administrator's email and password.

> **Back up `app_key`.** Copy the `app_key` line from `app/config.local.php` into a password manager that two trusted people can open. Without it, the encrypted names, phone numbers and health notes cannot be read, not even from backups.

Without SSH, run `tools/setup.php` on your own computer with `--base-url=https://www.imaraafrika.org`, then upload the generated `app/config.local.php` and `data/imara.sqlite`.

## 4. Email alerts

Edit `app/config.local.php`:

```php
'mail_transport' => 'smtp',
'smtp' => [
    'host' => 'smtp.hostinger.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'hello@imaraafrika.org',
    'password' => '…mailbox password…',
],
'notify_emails' => ['hello@imaraafrika.org'],
```

Create the `hello@imaraafrika.org` mailbox in hPanel first. Then use Dashboard → Settings & data → **Send me a test email**. While `mail_transport` is `'log'`, emails are only written to `data/logs/mail.log`.

## 5. WhatsApp alerts

See [WHATSAPP-TEMPLATES.md](WHATSAPP-TEMPLATES.md).

## 6. Scheduled jobs (hPanel → Advanced → Cron Jobs)

| When | Command | Why |
| --- | --- | --- |
| Daily 02:00 | `php /home/USER/tools/backup.php` | Database backup into `data/backups/` (keeps 30) |
| Monthly, 1st | `php /home/USER/tools/retention.php` | Removes personal details past the retention period |

Also download a backup **off the server** every week (Dashboard → Settings & data → Download backup) and keep it somewhere private.

## 7. Editing website content

The public pages are generated from `src/`:

```
src/partials/   header, footer, logo (shared by every page)
src/pages/      one file per page
config/site.json          phone, email, address, tagline
config/form-options.json  every dropdown list (districts, services, facilities…)
```

After editing, run `python tools/build.py`. It rewrites `public/*.html`; then upload the changed HTML files.

- **Adding a district or service:** edit `config/form-options.json`, run the build, then upload **both** the HTML and the `config/` folder, because the server checks submissions against the same list.
- **Changing the phone number:** edit `config/site.json` and run the build.

## 8. Running it on your computer

Double-click `run-dev.bat` (Windows). It builds the pages, creates a local test setup on first run, and opens http://127.0.0.1:8090. Emails go to `data/logs/mail.log`.

## 9. Security checklist before launch

- [ ] HTTPS works and `http://` redirects to `https://` (handled by `public/.htaccess`)
- [ ] `https://www.imaraafrika.org/../app/config.local.php` and `/data/` are **not** reachable
- [ ] `env` is `'production'` in `config.local.php` (hides PHP errors)
- [ ] `app_key` backed up offline by two people
- [ ] Every staff member has their own account, with the lowest role they need
- [ ] Email alerts tested; WhatsApp tested or knowingly left off
- [ ] Daily backup cron running, and a backup has been **restored** once as a test
- [ ] Privacy policy reviewed by Imara (and a lawyer if possible); PDPO registration confirmed
- [ ] Real staff in place to answer requests within 24 hours, including urgent ones

## 10. How the pieces fit

| Part | Files |
| --- | --- |
| Patient form (3 steps) | `src/pages/get-care.html`, `public/assets/js/get-care.js`, `public/assets/js/forms.js` |
| Patient API | `public/api/patient-request.php` → `app/lib/requests.php` |
| Other forms | `public/api/submit.php` → `app/lib/submissions.php` |
| Alerts | `app/lib/notify.php`, `mailer.php`, `whatsapp.php` |
| Encryption | `app/lib/crypto.php` (AES-256-GCM, field level) |
| Database schema | `app/lib/db.php` (migrations run automatically) |
| Staff login and roles | `app/lib/auth.php` |
| Dashboard pages | `public/admin/*.php` |
| Template styling | `public/assets/css/style.css` (Dinenos template, unchanged except font loading) + `public/assets/css/imara.css` (Imara layer) |

Requests are protected by: a honeypot field and minimum fill time (bots), a per-device hourly rate limit, a one-time token plus a same-phone-same-service time window (duplicates), server-side validation that mirrors the browser checks, CSRF tokens and a strict CSP on every dashboard page, and parameterised SQL throughout.

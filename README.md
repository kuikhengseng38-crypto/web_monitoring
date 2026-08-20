# Website Monitoring System with Telegram Alert (Admin Only)

Simple PHP + MySQL admin panel that checks websites, stores history, and sends Telegram messages when a site goes **DOWN**, comes back **UP**, or becomes **SLOW**.

No user registration. One admin account only.

This project is for **educational purposes**. See [LICENSE](LICENSE).

## Screenshots

<table>
  <tr>
    <td align="center" width="50%">
      <strong>Dashboard</strong><br/>
      <img src="docs/screenshots/dashboard.png" alt="Monitoring dashboard" width="400"/>
    </td>
    <td align="center" width="50%">
      <strong>Manage websites</strong><br/>
      <img src="docs/screenshots/websites.png" alt="Manage websites" width="400"/>
    </td>
  </tr>
  <tr>
    <td align="center" width="50%">
      <strong>Monitoring logs</strong><br/>
      <img src="docs/screenshots/monitoring-logs.png" alt="Monitoring logs" width="400"/>
    </td>
    <td align="center" width="50%">
      <strong>Public status page</strong><br/>
      <img src="docs/screenshots/public-status.png" alt="Public status page" width="400"/>
    </td>
  </tr>
</table>

## Folder structure

```
web_monitoring/
├── admin/                 Admin pages (login, dashboard, websites, logs, settings)
├── assets/css/            Styles
├── assets/js/             Show/hide password, delete confirm
├── config/                config.php loads gitignored local secrets
├── config/config.example.php
├── config/database.local.php.example
├── cron/monitor.php       Automatic monitoring engine
├── docs/screenshots/      README screenshots
├── docs/cpanel.example.md Placeholder cPanel notes
├── includes/              Auth, Telegram, check logic, layout
├── sql/schema.sql         Table structure only (no real data)
└── index.php              Public status page
```

## XAMPP setup

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** + **MySQL**.
2. Copy this project folder into:
   - `C:\xampp\htdocs\web_monitoring`
3. Copy `config/config.example.php` to `config/config.local.php` and replace `YOUR_CRON_SECRET` and `YOUR_RECOVERY_KEY`.
4. Copy `config/database.local.php.example` to `config/database.local.php` and fill in your local MySQL details.
5. Open phpMyAdmin: `http://localhost/phpmyadmin`
6. Import `sql/schema.sql` (creates tables, no user data).
7. Open: `http://localhost/web_monitoring/install.php` and create the first admin account.
8. Log in, then **change the password immediately**.

PHP **cURL** must be enabled (default in XAMPP). In `php.ini` confirm `extension=curl` is not commented out.

## First-time admin steps

1. **Settings** → paste Telegram bot token and chat ID → **Send test message**.
2. **Websites** → add name, URL (`https://...`), interval in minutes, optional slow threshold.
3. Dashboard → **Check all websites now** (does not wait for the next interval).
4. Keep the PHP server running locally, or add a cPanel Cron Job after upload.

Never commit `config.local.php` or `database.local.php`.

## Telegram bot setup

1. Open Telegram and search **@BotFather**.
2. Send `/newbot`, follow the prompts, copy the **bot token**.
3. Start a chat with your bot and send any message (for example `/start`).
4. Get your **chat ID**:
   - Open: `https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates`
   - Find `"chat":{"id": 123456789}`
   - For a group, add the bot to the group, send a message, then read `getUpdates` again (group IDs are often negative).
5. Paste token and chat ID in **Admin → Settings**. Do not put real tokens in files that will be committed.

Alerts are sent **only when status changes** (or when a site first becomes slow). Repeated UP or repeated DOWN checks do not send another Telegram message.

Message examples:

```
ALERT: Website DOWN
Website: School portal
URL: https://example.com
Status: DOWN
Response time: N/A
Time: 2026-08-19 15:00:00
```

```
RECOVERY: Website back UP
...
```

```
WARNING: Slow response detected
...
```

## How monitoring works

`cron/monitor.php` loads websites whose `last_checked` is older than their interval (or never checked).

For each URL it:

1. Sends an HTTP request with cURL
2. Measures response time
3. Sets **UP** only for HTTP 2xx/3xx. **DOWN** on timeout, connection failure, 404, or other 4xx/5xx.
4. Saves a row in `logs`
5. Updates the website row
6. Compares with the previous status (**UP / DOWN / SLOW**) and sends a Telegram message on **every change** (for example UP → DOWN, DOWN → UP)
7. Optional **SLOW** warning if UP but slower than the threshold

Dashboard **Check all websites now** ignores interval and checks every site.

Status colors:

- Green = UP
- Red = DOWN
- Yellow = SLOW (still reachable)

## cPanel upload

Follow [docs/cpanel.example.md](docs/cpanel.example.md). Use placeholders only in git:

```text
https://yourdomain.com/cron/monitor.php?key=YOUR_CRON_SECRET
```

1. Create a MySQL database and user in cPanel, then grant ALL PRIVILEGES.
2. Copy the two example config files to `config.local.php` and `database.local.php` **on the server** and fill in real values there.
3. Import `sql/schema.sql` (structure only).
4. Upload the project. Do not commit or publish the local config files, SQL dumps, or zip packages that contain a working config.
5. Open `/install.php` once to create the admin account, then delete `install.php`.
6. Log in and change the password immediately.
7. cPanel → **Cron Jobs** → every 1 minute, using `cron/monitor.php?key=YOUR_CRON_SECRET`.

Host stays `localhost`.

## Forgot password

On the login page, use **Forgot password** with:

- Admin username
- Recovery key from `config/config.local.php` (`ADMIN_RESET_KEY`, set from `YOUR_RECOVERY_KEY`)
- New password

## Database tables

| Table     | Purpose |
|-----------|---------|
| `admins`  | Single hashed admin login |
| `websites`| Name, URL, interval, current status, last check, response time |
| `logs`    | History of each check and status-change flags |
| `settings`| Telegram token, chat ID, timeout, default slow threshold |

## Notes for beginners

- URLs must start with `http://` or `https://`.
- Local sites only you can reach (for example another PC on your LAN) can be monitored from this machine.
- Some websites block bots; a block may look like DOWN or a long response time.
- Keep the starter admin password only until first login, then change it.
- Do not commit real passwords, cron keys, Telegram tokens, SQL dumps, or zip files that contain a working config.

## License

This project is released under an [Educational Use License](LICENSE) for learning and teaching only. Commercial use is not permitted.

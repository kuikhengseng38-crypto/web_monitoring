# cPanel upload (example)

Copy this file if you want local hosting notes. Do **not** commit a version that contains real database names, passwords, cron keys, or server paths.

1. In cPanel **MySQL Databases**, create a database and user, then add the user to the database with ALL PRIVILEGES.
2. Copy `config/config.example.php` → `config/config.local.php`
3. Copy `config/database.local.php.example` → `config/database.local.php`
4. Fill in placeholders only on the server:
   - `DB_NAME` = `your_database_name`
   - `DB_USER` = `your_database_user`
   - `DB_PASS` = `your_database_password`
   - `CRON_KEY` = `YOUR_CRON_SECRET`
   - `ADMIN_RESET_KEY` = `YOUR_RECOVERY_KEY`
5. In phpMyAdmin, select your database and import `sql/schema.sql` (table structure only).
6. Upload the project folder. Include the two `*.local.php` files on the server only.
7. Open `/install.php` once to create the first admin account, then delete `install.php`.
8. Change the admin password immediately after login.
9. cPanel → **Cron Jobs** → every 1 minute:

```text
https://yourdomain.com/cron/monitor.php?key=YOUR_CRON_SECRET
```

or:

```text
wget -q -O /dev/null "https://yourdomain.com/cron/monitor.php?key=YOUR_CRON_SECRET"
```

Host stays `localhost`. Never commit `config.local.php`, `database.local.php`, SQL dumps, or zip files that contain a working config.

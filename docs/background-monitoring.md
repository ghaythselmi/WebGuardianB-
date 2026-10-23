# Background monitoring and email alerts

WebGuardian runs scheduled checks through Symfony's `app:monitor:websites` command. Each run checks availability, SSL expiry, and security headers; updates the latest website status; and stores a history row. Adding a website now performs its first server-side check immediately. Manual per-site checks use `POST /api/websites/{id}/check`, also saving history. The authenticated history endpoint is `GET /api/websites/{id}/checks` and returns up to 100 newest checks for a website owned by the current user. The dashboard's **Monitoring Analytics** section uses this endpoint to chart response times and show recent availability history. Existing sites gain their first history row at the next scheduled check or when **Update All** is used.

## Apply the database migration

From the backend directory, run:

```sh
php bin/console doctrine:migrations:migrate --no-interaction
```

This creates account creation/alert preference fields, per-website alert state, and the `website_check` history table. Newly created accounts record their creation date. Existing accounts keep this field empty because their original creation dates were not stored. Run the migration once per environment before enabling the scheduler.

## Configure email delivery

Provide these as environment variables to the PHP process (do not commit credentials):

| Variable | Example | Purpose |
| --- | --- | --- |
| `MAIL_HOST` | `smtp.example.com` | SMTP server; required to send alerts |
| `MAIL_PORT` | `587` | SMTP port; defaults to 587 |
| `MAIL_USERNAME` | `webguardian@example.com` | Optional SMTP username |
| `MAIL_PASSWORD` | supplied secret | SMTP password when authentication is enabled |
| `MAIL_ENCRYPTION` | `tls` | `tls`, `ssl`, or `none`; defaults to `tls` |
| `MAIL_FROM_ADDRESS` | `alerts@example.com` | Sender address; required |
| `MAIL_FROM_NAME` | `WebGuardian` | Sender display name |

Users can disable alerts from Profile. Enabled users receive a message when a website changes between online and offline, and when its SSL certificate enters the 30-, 7-, or 1-day warning window. Alert state is saved only after a successful email delivery, so failed messages are retried on a later run.

## Schedule it every five minutes

First verify the command manually:

```sh
php bin/console app:monitor:websites
```

On Linux, add a cron entry using the absolute backend directory and PHP executable:

```cron
*/5 * * * * cd /path/to/WebGuardianB && php bin/console app:monitor:websites --env=prod >> var/log/monitor.log 2>&1
```

On Windows, create a Task Scheduler task that runs `php.exe` with arguments `bin/console app:monitor:websites --env=prod`, sets the backend directory as **Start in**, and repeats every five minutes. Keep the task running under an account that can access the database and SMTP settings.

The Angular dashboard no longer needs to stay open for recurring checks. Its **Update All** action remains available for an immediate refresh.

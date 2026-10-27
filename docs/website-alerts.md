# Website down alerts

When a newly added website is offline, WebGuardian saves an in-app notification and emails the account address when **Email alerts** is enabled in the user's Profile. Clicking **Update** or **Update All** also alerts for any offline website, including one that was already offline. Scheduled checks alert on status changes, and a recovery creates a notification and email as well.

## Gmail sender setup

The sender defaults to `webguardianweb@gmail.com` through Gmail SMTP (`smtp.gmail.com:587`, STARTTLS). Store a Google app password in the backend environment; do not use or commit the account's normal password. For a local setup, add these values to `.env.local`:

```dotenv
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=webguardianweb@gmail.com
MAIL_FROM_NAME=WebGuardian
MAIL_USERNAME=webguardianweb@gmail.com
MAIL_PASSWORD=your-google-app-password
```

Email alerts cannot be delivered until a valid app password is configured. Delivery failures are logged by the backend; in-app notifications are still saved.

## Database and periodic checks

Apply the new notification table migration:

```powershell
php bin/console doctrine:migrations:migrate
```

The `/home` dashboard refreshes saved results every five minutes. New checks happen when a user adds a website or clicks **Update** / **Update All**. For automatic checks and status-change alerts while the user is away, schedule this command to run every five minutes using the host's task scheduler:

```powershell
php bin/console app:monitor:websites
```

Run the command from the backend project directory and under the same environment that contains the database and mail settings.

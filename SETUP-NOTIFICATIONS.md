# Turning on real email and SMS

Out of the box the reminder system is fully working, but both channels
write to `storage/reminders.log` instead of actually sending. That's
deliberate — a stock XAMPP install has no mail server and no SMS account,
so there is nothing to hand a message to.

This file covers what to do when the clinic is ready to send for real.
Nothing in the application code needs to change.

---

## Part 1 — Email

PHP needs an SMTP server. The quickest route is a Gmail account.

### Step 1. Create the App Password

Google stopped allowing plain account passwords for this, so you need a
16-character **App Password**:

1. The Google account must have **2-Step Verification turned on** — App
   Passwords don't appear without it.
2. Go to your Google Account → **Security** → **App passwords**.
3. Choose **Other (custom name)**, type `Paw Prints`, click **Generate**.
4. Copy the 16-character password. You won't be shown it again.

Use a dedicated clinic account (e.g. `pawprints.clinic@gmail.com`) rather
than someone's personal address.

### Step 2. Point XAMPP at Gmail

Two files, both in your XAMPP folder.

**`C:\xampp\php\php.ini`** — find `[mail function]`:

```ini
[mail function]
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = pawprints.clinic@gmail.com
sendmail_path = "C:\xampp\sendmail\sendmail.exe -t -i"
```

**`C:\xampp\sendmail\sendmail.ini`**:

```ini
smtp_server=smtp.gmail.com
smtp_port=587
smtp_ssl=tls
auth_username=pawprints.clinic@gmail.com
auth_password=the-16-character-app-password
error_logfile=error.log
```

Restart Apache from the XAMPP Control Panel.

### Step 3. Check it

Sign in and use **Forgot your password?** — or wait for a reminder to be
due. If mail still isn't arriving, `C:\xampp\sendmail\error.log` says why.

> **Gmail limits sending to around 500 messages a day.** Fine for one
> clinic. If Paw Prints ever outgrows that, move to a transactional mail
> service (Mailtrap, Brevo, SendGrid) — same SMTP settings, different
> host and credentials.

---

## Part 2 — SMS

SMS can't be free: every message goes through a telco, so it needs a
paid gateway account.

### Choosing a provider

For a Philippine clinic, local providers are far cheaper than
international ones — roughly ₱0.35–0.50 per message versus several times
that through Twilio.

| Provider | Roughly | Notes |
|---|---|---|
| PhilSMS | ₱0.35 / SMS | Cheapest of the local options |
| Semaphore | ₱0.50 / SMS | Well documented, widely used |
| IPROG SMS | ₱1.00 / SMS | Markets specifically to student projects |
| Twilio | Much higher | Only worth it if sending abroad too |

The code ships pointed at Semaphore's endpoint, but any provider that
takes a simple HTTP POST works — only the field names change.

At clinic volume this is genuinely cheap: 20 appointments a day is about
₱300/month at ₱0.50 each.

### Step 1. Sign up and top up

1. Create an account with your chosen provider.
2. Buy a small credit bundle to start.
3. Copy your **API key** from the dashboard.

### Step 2. Register a sender name

The name recipients see (e.g. `PAWPRINTS`). Register it in the provider's
dashboard — **allow 2–4 weeks**, since the telcos have to approve it.
Most providers give you a shared sender name to test with meanwhile.

Start this early if you want it ready for a specific date.

### Step 3. Fill in the config

Open `config/notify.php`:

```php
define('SMS_API_KEY', 'your-api-key-here');
define('SMS_API_URL', 'https://api.semaphore.co/api/v4/messages');
define('SMS_SENDER',  'PAWPRINTS');
```

That's it. As soon as `SMS_API_KEY` isn't empty, texts send instead of
being logged.

If you picked a different provider, check their docs for the field names
and adjust `deliver_sms()` in `includes/reminders.php` — it's about ten
lines.

---

## Part 3 — Make it run on its own

Reminders need a daily trigger. On Windows:

1. Open **Task Scheduler** → **Create Basic Task**
2. Name: `Paw Prints reminders`
3. Trigger: **Daily**, 7:00 AM
4. Action: **Start a program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\pawprints\cron.php`
5. Finish, then right-click the task → **Run** to test it

You should see a line like `[2026-07-24 07:00:01] 3 queued, 3 sent`.

On Linux, `crontab -e`:

```
0 7 * * * /usr/bin/php /var/www/pawprints/cron.php
```

Running it more than once a day is harmless — a `UNIQUE` key means
nobody is messaged twice.

---

## Part 4 — Before going live

**Consent.** The Data Privacy Act (RA 10173) prohibits unsolicited
commercial messages, and NPC Circular 2023-04 says implied consent
doesn't count. Appointment reminders for an existing client are
service messages rather than marketing, but the safe course is to have
owners tick a consent box when they register, and to keep a record of it.

**Opt-out.** Honour any request to stop within 24 hours. There's no
opt-out flag in the schema yet — if the clinic wants one, the cleanest
approach is a `reminders_opt_out` column on `owners`, checked inside
`queue_reminders()`.

**Check the phone numbers.** The SMS goes to whatever is on the owner's
client record. Numbers must be real mobile numbers; a landline will
simply fail at the gateway.

---

## Testing without spending anything

Leave both channels unconfigured and watch `storage/reminders.log`. Every
message that *would* have gone out is written there in full, with the
recipient and timestamp — enough to demonstrate the whole flow.

To force a run rather than waiting for the daily trigger:

```
C:\xampp\php\php.exe C:\xampp\htdocs\pawprints\cron.php
```

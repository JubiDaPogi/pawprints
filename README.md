# Paw Prints Veterinary Clinic — Web-Based Patient Tracking System

A PHP + MySQL web application for **XAMPP**. It tracks patients (pets),
their owners, clinic visits, vaccinations, and appointments, with two
kinds of login: **clinic staff** (full access) and **pet owners**
(view-only access to their own pets).

Built for the capstone *"Enhancing Veterinary Care: A Web-Based Patient
Tracking System for Paw Prints Veterinary Clinic."*

---

## What you need

- **XAMPP** (any recent version). Download: https://www.apachefriends.org
  - You only use two parts of it: **Apache** (the web server) and
    **MySQL** (the database).
- A web browser.
- A text editor to make changes (VS Code, Notepad++, Sublime — anything).

That's it. Everything is plain PHP; there is nothing to compile and
nothing to install with a package manager.

---

## Setup — step by step

### 1. Put the project into XAMPP's `htdocs` folder

Copy the whole **`pawprints`** folder into your XAMPP `htdocs` directory.

- Windows: `C:\xampp\htdocs\pawprints`
- macOS: `/Applications/XAMPP/htdocs/pawprints`

When you're done, the path `C:\xampp\htdocs\pawprints\index.php` should
exist.

### 2. Start Apache and MySQL

Open the **XAMPP Control Panel** and click **Start** next to both
**Apache** and **MySQL**. Both should turn green.

### 3. Create the database

1. Open **http://localhost/phpmyadmin** in your browser.
2. Click the **Import** tab at the top.
3. Click **Choose File** and select **`database.sql`** from the
   `pawprints` folder.
4. Scroll down and click **Go / Import**.

You should see a green success message, and a new database called
**`pawprints_db`** will appear in the left sidebar, already filled with
sample pets, owners, visits, and appointments.

> Re-importing `database.sql` at any time gives you a fresh, clean copy
> with the sample data again. (It drops and recreates the database, so
> anything you added will be wiped — that's handy while testing.)

> **Already installed an earlier version? You must re-import.**
> The account management module adds new columns to the `users` table
> and a new `audit_log` table. If you skip this step you'll get errors
> like *"Unknown column 'is_active'"*. Just import `database.sql` again
> as described above — it rebuilds everything.

> **Want to keep your existing data?** Re-importing wipes everything, so
> if you need to preserve records, run the one-off migrations in
> `migrations/2026_07_add_visits_soft_delete.sql` and
> `migrations/2026_07_add_vaccinations_soft_delete.sql` instead. Together
> they add a `deleted_at` column to the `visits` and `vaccinations` tables
> so deleted visits and vaccinations can be sent to the Archive and
> restored, without touching your other data. In phpMyAdmin: select the
> database, open the **SQL** tab, paste each file's contents, and click
> **Go**.

### 4. Open the app

Go to **http://localhost/pawprints** in your browser. You'll see the
login screen.

---

## Demo logins

The database comes with these accounts so you can try it immediately:

**Clinic staff (full access — add/edit/delete):**

| Email (this is the login) | Password   |
|---------------------------|------------|
| `clinic@pawprints.vet`    | `admin123` |

> **Every account signs in with an email address.** There are no short
> usernames anywhere in the system — the username *is* the email, and
> the app rejects anything that isn't a valid address.
>
> **Signing in with an email address.** Accounts created through the app
> use the person's email as their username, so they sign in with e.g.
> `johndoe@email.com` and their phone digits (`09171234567`) as the
> temporary password. The login box accepts either an email or an older
> plain username, and is case-insensitive. While the temporary password
> is still active, the phone number is also accepted with its formatting
> (`0917-123-4567`) — once the user sets their own password, only the
> exact new password works.

**Pet owners (sees only their own pets; can manage only their own account):**

| Email (this is the login) | Password   | Sees the pets of |
|--------------------------|------------|------------------|
| `maria.santos@email.com` | `owner123` | Maria Santos     |
| `jose.dc@email.com`      | `owner123` | Jose Dela Cruz   |
| `ana.reyes@email.com`    | `owner123` | Ana Reyes        |
| `ramon.a@email.com`      | `owner123` | Ramon Aquino     |

> **About the passwords:** for your convenience the sample accounts start
> with simple known passwords. The **first time** each account logs in
> successfully, the app quietly re-saves that password as a secure
> **bcrypt hash** (using PHP's `password_hash()`), which is how a real
> system should store passwords. Any new owner you create through the app
> is hashed the same way from the start. So the plain passwords above only
> exist until first login — after that the stored value is a proper hash.

---

## Pet owners & species

### New owner accounts become pet owners automatically

When staff create an account with the **Pet owner** role, the system also
creates that person's **client record** — so they appear straight away in
the "Owner" dropdown when adding a patient. Previously an account could be
created without a client record, and the new owner simply never showed up
on the Patients screen.

- If the email already belongs to a client, that existing record is reused
  instead of creating a duplicate.
- Editing an owner account keeps their client record's name, phone, and
  email in step.
- An optional **Address** field on the new-account form is saved to the
  client record.
- Both actions are written to the activity log (`owner_create`).

### Staff-managed species

Species used to be fixed at Dog / Cat / Bird / Rabbit. They now live in
their own `species` table that clinic staff control:

- On **Patients**, click **Species** to open the manager.
- Type a name and press **Add** — it appears immediately in the filter
  chips and in the Species dropdown on the patient form.
- Names are tidied to Title Case, so "guinea pig" and "Guinea Pig" can't
  both exist.
- A species still used by a patient **cannot be removed** — the system
  says how many patients would be affected instead of orphaning records.
- Adding and removing are recorded in the activity log.

Only clinic staff can manage species; the handlers call `require_staff()`.

---

## Security: passwords & automatic sign-out

### Password strength

Any password a user chooses for themselves must contain:

| Requirement | Example |
|---|---|
| At least 8 characters | `PawPrints1!` |
| An uppercase letter (A–Z) | `P` |
| A lowercase letter (a–z) | `aw` |
| A number (0–9) | `1` |
| A special character | `!` `@` `#` `$` `%` `^` `&` `*` `(` `)` `_` `+` `-` `=` `?` |

The Change-password form shows a **live checklist** that ticks each rule
off as you type, and warns immediately if the confirmation doesn't match.
The server re-checks on submit, so the rules hold even with JavaScript off.

This applies wherever a password is *set* — the change-password form and
a staff password reset. It does **not** apply at sign-in, so existing
accounts (including the demo logins) keep working; they're only asked for
a stronger password the next time they change it. Temporary passwords
issued from a phone number are exempt by design, since the account is
forced to change them at first sign-in — and a phone number can never
satisfy these rules, so it can't be reused as a permanent password.

### Automatic sign-out after inactivity

Clinic computers are often shared and left unattended, so a session that
sits idle is closed rather than left open on patient records.

- After **15 minutes** with no activity the session ends and the person is
  returned to the login page with an explanation.
- **2 minutes before** that, a dialog appears with a live countdown and a
  **Stay signed in** button.
- Any click, keypress, scroll, or touch counts as activity and resets the
  countdown.
- The automatic sign-out is written to the activity log as a `logout` entry
  noting it was caused by inactivity.

The server is the authority: `enforce_idle_timeout()` in
`includes/auth.php` runs on every request, so an expired session is
rejected even if the browser tab was closed or JavaScript disabled. To
change the timings, edit `IDLE_TIMEOUT` and `IDLE_WARN_AFTER` at the top
of that file.

---

## Where messages appear

Messages now show up where they belong:

- **Validation errors appear inside the form that caused them** — a wrong
  current password shows in the Change-password card, not as a toast in
  the page corner. Same for the profile, security, species, and
  user-account forms.
- **Page-level news still uses the floating toast** — "Bruno added to
  patient records", "Account removed", and similar confirmations that
  aren't tied to one form.
- If the error came from a form inside a **modal**, that modal reopens
  automatically and focuses the first field, so the message is never
  hidden behind a closed dialog.

Handlers choose using `set_form_error('<form key>', '<message>')` for
form-specific problems, or plain `set_flash('<message>')` for page-level
news. Forms render their message with `<?= form_alert('<form key>') ?>`.

---

## Account management & roles (RBAC)

The system has a full role-based account management module. There are two
roles — **staff** and **owner** — and what you can do is decided by your
role plus one optional permission flag.

### Owner-led listings

On **Appointments**, the *pet owner* is the headline and the pet is the
supporting line — clinic staff look people up by client, not by pet name.

On **Patients**, this goes further: the screen is grouped so **each
client appears exactly once**, as a block containing all of their pets.
An owner with four pets is one record with four pet cards inside it,
rather than four separate cards repeating the same name.

Blocks are keyed by **owner id**, not by name, so two different clients
who happen to share a name stay separate records. When that happens,
each block also shows a **Client #** reference so staff can tell them
apart; the badge doesn't appear when a name is unique.

Each block header carries the client's phone, email and address, and a
count of their pets. Searching filters the pets first, so a search shows
only the matching pets under each owner.

Owner names are shown formally as **Lastname, Firstname M.**:

| Stored name | Displayed as |
|---|---|
| Maria Cruz Santos | `Santos, Maria C.` |
| Jose Ramos Dela Cruz | `Dela Cruz, Jose R.` |
| Ramon Bautista Aquino | `Aquino, Ramon B.` |
| Juan Dela Cruz Santos *(two-word middle)* | `Santos, Juan D. C.` |

`format_name_formal()` in `functions.php` handles this, degrading
sensibly when a part is missing.

A **pet owner's own** dashboard is unchanged — their pets stay the
headline, since every record there is already theirs.

Search still matches names in natural order, so typing "maria santos"
finds `Santos, Maria C.`

### Automatic appointment reminders

Pet owners are reminded of their appointments without anyone at the
clinic having to do anything:

- an **email** the day *before* the appointment
- a **text message** on the *morning of* the appointment

Both go to the owner's contact details on their client record. Staff are
never messaged, and there is no button to press — the run is recorded in
the activity log as `reminders_run` so it can be checked after the fact.

**Scheduling it.** `cron.php` is the runner. On XAMPP, point Windows Task
Scheduler at it once a day:

```
Program:   C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\pawprints\cron.php
Trigger:   daily, 7:00 AM
```

If no scheduled task is set up, the system falls back to running the
cycle on the first page view of each day, so a demo install still sends.
Either way an owner can't be messaged twice — a `UNIQUE (appointment_id,
channel)` key makes a repeat run a no-op.

**Deleted records never get reminders.** A reminder waits in the queue
for up to a day, so it is re-checked at the moment of sending, not only
when it was queued. It is cancelled instead of sent if, in the meantime:

- the **patient record was deleted** (or its owner's record was)
- the **appointment was deleted**
- the appointment was marked **Completed**
- the appointment **date has passed**

Deleting a patient also cancels their waiting reminders straight away,
and **restoring** the patient resumes them:

- appointment still days away → the reminders wait for their day, then
  send as normal
- appointment **today** → anything already overdue sends immediately, and
  an email written for "tomorrow" is reworded to say **today** so it
  doesn't arrive contradicting itself
- appointment already **past** → stays cancelled, since there is nothing
  useful left to say

**Sending for real.** A stock XAMPP install has no mail server and no SMS
gateway, so both channels write to `storage/reminders.log` instead —
useful for showing exactly what would have gone out.

To switch real delivery on, see **`SETUP-NOTIFICATIONS.md`**. In short:
a Gmail App Password plus two lines in `php.ini` for email, and an API
key from a Philippine SMS gateway (around ₱0.50 a message) for texts.
No application code changes either way.

### Security questions & account recovery

Every account answers **three** security questions at first sign-in —
enforced in `header.php`, so no page works until it's done.

The three offered are **picked per account** from a bank of eight, using
a shuffle seeded from the user id. Two people setting up on the same day
get different questions, but reloading the form shows the *same* three
rather than reshuffling mid-entry. Any of them can still be changed from
the dropdowns, and the same question can't be chosen twice.

**Forgot your password?** on the sign-in form runs a three-step recovery:
email → **one of the three questions chosen at random** → new password.
A different question is drawn on each attempt, so a single known answer
can't be reused.

Safeguards:

- answers are **hashed** with bcrypt, never stored in plain text
- comparison ignores case and extra spaces, so "Bantay" matches " bantay "
- **three wrong answers** ends the attempt
- unknown and unconfigured emails give the **same message**, so the form
  can't be used to discover which addresses are registered
- a recovery session **expires after 15 minutes**
- the new password must meet the full strength rules and can't be the
  one already in use
- `recovery_start`, `recovery_failed` and `password_change` are audited

### Archive — nothing is really deleted

Deleting a **patient**, **user account** or **species** no longer removes
the row. It sets a `deleted_at` timestamp, the record disappears from
every screen, and it can be put back exactly as it was from
**Archive** in the sidebar.

Access follows the same split as everywhere else — restoring a patient is
clinical work, restoring a login is administration:

| | Patients | Species | User accounts |
|---|:--:|:--:|:--:|
| Clinic staff (admin) | yes | yes | yes |
| **Clinic staff (limited)** | **yes** | **yes** | **no — tab hidden** |
| Pet owner | no access | no access | no access |
| Pet owner with admin rights | yes | yes | yes |

The user-accounts tab isn't merely hidden: `actions/restore.php`
re-checks the permission for that type, so a forged POST is refused
server-side.

**Permanent deletion.** Each row also offers **Delete forever**, which
really removes it — this is the only irreversible action in the system,
so it is restricted to accounts with **full admin rights**. Limited staff
can restore but never permanently delete; `actions/purge.php` starts with
`require_manage_users()`, so hiding the button isn't the only protection.

Safeguards on a permanent delete:

- only records **already in the Archive** can be purged, so a live
  record can't be destroyed by posting its id
- deleting a patient **also destroys their visits, vaccinations and
  appointments** — the confirmation states exactly how many
- you cannot permanently delete **your own account**
- a species still named by **any** patient row — including a soft-deleted
  one that might yet be restored — is refused
- every purge is written to the activity log as a `purge` entry describing
  what was removed

This matters most for patients: the old behaviour used
`ON DELETE CASCADE`, so removing a pet also destroyed their **visits,
vaccinations and appointments**. Those rows are now untouched, so a
restored patient comes back with their full history.

Restoring checks for conflicts created since the deletion:

- a patient can't be restored if its **species** has since been removed
- an account can't be restored if another **active account now uses that
  email**
- a species can't be restored if the **name has been re-added**

Each restore is recorded in the activity log (`patient_restore`,
`user_restore`, `species_restore`).

### The email address is the login

There is no `username` column. A person's **email address is their login
identifier**, stored once in `users.email` (`NOT NULL UNIQUE`, so the
database itself prevents two accounts sharing one).

- Sign-in matches the email case-insensitively.
- Creating an account asks only for the email; there is no separate
  "choose a username" step.
- Changing someone's email changes the address they sign in with —
  they can't drift apart, because there is only one value.

`audit_log.actor_username` is **not** a duplicate: it is a deliberate
snapshot of who performed each action, kept so the history stays
readable after an account is renamed or deleted. It now stores the email
address.

### Names: first, middle, last

Names are stored **only** as `first_name`, `middle_name` and `last_name`
— on both `users` and `owners`. There is no redundant combined column,
so a name can never be stored twice and drift out of step.

- **First**, **middle** and **last** are all required (`NOT NULL` in the
  database, enforced in the browser and re-checked server-side).
- Screens that need one display name build it on demand:
  - in SQL via `CONCAT_WS`, which skips NULLs so a missing middle name
    doesn't leave a double space (see `name_sql()` in `functions.php`)
  - in PHP via `build_full_name($first, $middle, $last)`
- Account search matches against the assembled name, so typing a middle
  name or surname still finds the person.
- Owner dropdowns are sorted by **last name, then first name**.
- Multi-word surnames (e.g. *Dela Cruz*) are preserved exactly as typed.

Both the **New/Edit account** modal and **My Account → Profile** show the
three separate fields.

### What pet owners can and cannot see

Clinical findings stay with the clinic. A pet owner signed in to their
own workspace sees:

| Visible to owners | Hidden from owners |
|---|---|
| Pet details (breed, sex, age, colour) | Diagnosis |
| Vitals (weight, temperature, heart rate) | Treatment / medication |
| **Full vaccination record** — vaccine, date given, next due | Clinical notes |
| Their upcoming appointments | Attending vet |
| Vaccination-due reminders in the bell | "Under treatment" alerts |

The **Visits & Diagnoses** tab does not appear on an owner's view of the
chart; **Vaccinations** is their default tab.

This is enforced in three places, so a single missed check can't leak
records:

1. **Data layer** — `patient.php` and `dashboard.php` don't even *query*
   the `visits` table for an owner, so the rows never reach the page.
2. **View layer** — the visits tab and its panel are wrapped in
   `if ($staff)`.
3. **Component** — `render_pet_card()` re-checks `is_staff()` before
   printing a diagnosis blurb, so a caller passing visit data by mistake
   still can't expose it.

Owners remain restricted to their *own* pets by the existing owner check
on the patient chart.

### One sign-in form for everyone

Clinic staff and pet owners use the **same** sign-in form — there is no
role to choose. The account's own role decides which workspace opens
after signing in:

- a **staff** account lands in the clinic workspace
- a **pet owner** account lands in their own pets' workspace

This removes a whole class of confusion. There is no way to pick the
"wrong" side, so there is no wrong-tab rejection to explain, and the
form can't hint at whether an address belongs to staff or an owner.
Every failed attempt returns the same message — *"Incorrect email or
password. Please try again."* — whether the address is unknown or the
password is wrong.

### Two separate things: role and admin rights

**Role** decides which workspace you land in:

- **Clinic staff** → the clinic workspace (all patients, appointments, reports)
- **Pet owner** → the owner workspace (only their own pets)

**Admin rights** (`can_manage_users`) is a *separate per-account
permission* that decides whether you also see **User Accounts** and
**Activity Log**. It is independent of role, which gives four useful
combinations:

| Account | Workspace | Patients | User Accounts + Activity Log |
|---|---|---|:--:|
| **Staff (default), admin unticked** | **Clinic** | **all** | **no** |
| Staff, admin ticked | Clinic | all | yes |
| Pet owner (default) | Owner | own pets only | no |
| Pet owner, admin granted | Owner | own pets only | yes |

The first row is the **limited staff / assistant login**, and now the
default for every new clinic-staff account: a receptionist or vet
assistant who works with patients all day but cannot create, edit, or
delete user accounts. Tick *Allow user-account management* only for an
account that should also administer other users. They show a
**Limited** tag in the accounts list; anyone with admin rights shows
an **Admin** tag.

New accounts default sensibly: staff and pet owners both start without
admin rights, and it's opt-in from there. Both are editable at any time.

Everyone gets **My Account** regardless.

### "Unless additional permissions are explicitly granted"

That requirement is implemented with the `users.can_manage_users` column.

- Staff start with `0`, same as owners — a limited login by default.
  Admin rights are a deliberate opt-in, not implied by role.
- Owners default to `0` — locked to their own account only.
- A staff member can grant either a staff or an owner account access:
  **User Accounts → edit the account → tick "Allow user-account
  management" → Save.**

Granting or revoking is itself recorded in the activity log as
`permission_grant` / `permission_revoke`.

The check lives in `includes/auth.php`:

```php
function can_manage_users() {
    $u = current_user();
    if (!$u) return false;
    return !empty($u['can_manage_users']);   // a permission, not a role
}
```

Pages call `require_manage_users()`, which bounces anyone unauthorised
back to the dashboard with a message. Authorisation is re-checked **inside
every action handler**, not just when drawing the page — so a user cannot
bypass it by POSTing a form directly.

### My Account (`account.php`)

Available to every logged-in user, for their **own** account only:

- **Profile** — full name, email, phone. For owners this also updates
  their client record in the `owners` table, so the clinic's contact
  details stay in sync. Saving requires typing the account's **current
  password** to confirm the change.
- **Change password** — requires the current password, enforces a minimum
  of 8 characters with at least one letter and one number, and the new
  password must differ from the old one. The session ID is regenerated
  after a successful change.
- **Security settings** — a security question plus an answer, which is
  stored **hashed** (never in plain text). Saving also requires the
  current password, same as Profile. (The one-time setup a brand-new
  account is sent to at first sign-in is separate and does not ask for
  it, since there's no prior account state to protect yet.)
- **Recent account activity** — that user's own last few audit entries.

### User Accounts (`users.php`)

For staff (and permitted owners). Search and filter accounts, then:

- **Create** an account. Sign-in details are generated from the person's
  contact info, so staff never invent credentials:
  - the **email address becomes the username**
  - the **digits of the phone number become the temporary password**
    (e.g. `0917-555-0142` becomes `09175550142`)
  - **"Require a password change at next sign-in" is always on** for new
    accounts — the checkbox is ticked and locked, so the temporary
    password only works once.

  After saving, the confirmation message shows the username and temporary
  password so staff can hand them to the new user. At first sign-in the
  user is sent straight to the password screen, and their new password
  must meet the full strength rules (8+ characters, a letter and a
  number) — which the phone number itself does not, so it cannot be
  reused.
- **Edit** any field; leaving the password box blank keeps the current one.
- **Activate / deactivate** — a deactivated user cannot log in; the attempt
  is logged as `login_denied`.
- **Delete** — removes the login only. Pets, visits and client records are
  never deleted with it.
- **Force a password change** — tick the box and that user must set a new
  password at next login before doing anything else.

**Safety guards built in.** You cannot change your *own* role, status or
permission, and you cannot deactivate or delete yourself. The system also
refuses to remove or demote the **last active staff account**, or to
revoke, deactivate, or delete the **last account with admin rights** — so
the clinic can never lock itself out of user management.

### Activity log (`audit.php`)

Every account-related action is recorded to the `audit_log` table with
who did it, what changed, the target account, a timestamp and the IP
address. Logged actions include:

`login`, `login_failed`, `login_denied`, `logout`, `profile_update`,
`password_change`, `security_update`, `user_create`, `user_update`,
`user_delete`, `user_activate`, `user_deactivate`, `permission_grant`,
`permission_revoke`.

The log is read-only in the interface and can be filtered by category
(sign-ins, account changes, security).

### Security measures used

- Passwords hashed with `password_hash()` (bcrypt); verified with
  `password_verify()`.
- Security answers hashed too — never stored or compared in plain text.
- **CSRF tokens** on every account form, validated server-side before any
  change is written.
- **Re-authentication on My Account.** Every form that changes an
  existing account (Profile, Change password, Security settings) requires
  the current password before anything is saved — so a device left
  signed in and unattended can't be used to quietly take over the
  account. Checked server-side; failed attempts are activity-logged the
  same way a failed sign-in is.
- All database access uses **prepared statements**.
- Output escaped with `htmlspecialchars()` to prevent XSS.
- Session ID regenerated on login and after a password change.
- Validation runs on the server, not just in the browser.

---

## What each part does

```
pawprints/
├── index.php            Login screen (staff + owner tabs)
├── dashboard.php        Home page after login (different for staff vs owner)
├── patients.php         Staff: full patient list, search, add new patient
├── patient.php          One pet's chart: vitals, visit history, vaccines
├── appointments.php     Schedule / view appointments
├── reports.php          Staff: simple analytics (by species, by month, etc.)
├── logout.php           Ends the session
├── database.sql         Creates the database + sample data (import this)
├── README.md            This file
│
├── migrations/          One-off SQL updates for existing installs
│   └── 2026_07_add_visits_soft_delete.sql
│
├── config/
│   └── database.php     Database connection settings (see below)
│
├── includes/
│   ├── auth.php         Login checks (is the user staff? logged in?)
│   ├── functions.php    Small helpers (dates, ages, formatting)
│   ├── components.php   Reusable pieces of the page (cards, modals)
│   ├── header.php       The top bar + sidebar shown on every page
│   └── footer.php       Closes the page; small bits of JavaScript
│
├── actions/            The "do something" handlers (form submissions):
│   ├── login.php            Check a login
│   ├── create_patient.php   Save a new pet
│   ├── update_patient.php   Edit a pet
│   ├── delete_patient.php   Remove a pet
│   ├── add_visit.php        Log a clinic visit
│   ├── add_vaccine.php      Record a vaccination
│   ├── create_appointment.php   Book an appointment
│   └── complete_appointment.php Mark one as done
│
└── assets/
    └── css/
        └── style.css   All the styling / look of the app
```

**Where to make changes:**

- To change how something **looks** → `assets/css/style.css`.
- To change the **words** on a page → open that page's `.php` file
  (e.g. `dashboard.php`) and edit the text between the HTML tags.
- To change what happens when a **form is submitted** → the matching
  file in `actions/`.
- To change the **database structure or sample data** → `database.sql`
  (then re-import it).

---

## Database connection settings

The file `config/database.php` is set up for a **default XAMPP install**:

- Host: `localhost`
- Username: `root`
- Password: *(empty)*
- Database: `pawprints_db`

If your MySQL uses a different username or password, open that file and
change the two lines near the top. Most default XAMPP installs need no
change at all.

---

## Troubleshooting

**"Connection failed" / a database error when I open the app**
MySQL probably isn't running, or the database wasn't imported. Check that
**MySQL is green** in the XAMPP Control Panel, and that you imported
`database.sql` (step 3). If you set a MySQL root password, put it in
`config/database.php`.

**The page shows PHP code as plain text instead of running**
You opened the file directly (e.g. double-clicked it, so the address bar
shows `file:///...`). PHP only runs through the web server — always visit
**http://localhost/pawprints** instead.

**"Object not found" / 404 at http://localhost/pawprints**
The folder isn't in `htdocs`, or it's named differently. Make sure the
path is `htdocs/pawprints/index.php`. The address after `localhost/` must
match the folder name.

**The styling looks broken / plain**
Make sure the `assets/css/style.css` file came along with the rest of the
folder and that you're viewing through `http://localhost/...`.

**I want the sample data back**
Re-import `database.sql` in phpMyAdmin (step 3). It resets everything.

---

## Notes for your defense / documentation

- **Stack:** PHP (procedural, with PDO for database access) + MySQL/MariaDB,
  served by Apache via XAMPP — the standard local web stack.
- **Security touches worth mentioning:** all database queries use
  **prepared statements** (PDO) to prevent SQL injection; all user text is
  escaped on output with `htmlspecialchars()` to prevent XSS; passwords are
  stored as **bcrypt** hashes via `password_hash()` / `password_verify()`;
  and pages check the session so an owner cannot open another owner's pet
  by editing the URL.
- **Roles:** access is separated into *staff* (full create/read/update/
  delete) and *owner* (read-only, and only their own pets).

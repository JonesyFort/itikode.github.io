# ITiKODE: Interactive E-Learning Module (OOP in Python)

Students learn, teachers review, admins manage everything. This folder is the whole system: a PHP website with a built-in SQLite database. There is nothing else to install except PHP.

**Fastest way to start (Windows):** install PHP once (Part 1), then double-click **`start.bat`**. A browser tab opens at <http://localhost:8000>.

---

## Contents

1. [What you need](#1-what-you-need)
2. [Install PHP (one time)](#2-install-php-one-time)
3. [Get the project ready](#3-get-the-project-ready)
4. [Run the system](#4-run-the-system)
5. [Log in](#5-log-in)
6. [Try it on a phone](#6-try-it-on-a-phone)
7. [Look inside the database](#7-look-inside-the-database-optional)
8. [Stop, reset and start fresh](#8-stop-reset-and-start-fresh)
9. [Troubleshooting](#9-troubleshooting)
10. [Notes for the project owner](#10-notes-for-the-project-owner)
11. [Reference](#reference)

---

## 1. What you need

| Item | Notes |
|---|---|
| **PHP 8.1 or newer** | Free. See Part 2. Extensions needed: `pdo_sqlite`, `sqlite3`, `mbstring`. |
| A web browser | Chrome, Edge, Brave, Firefox or Safari. |
| Internet connection | Needed for the fonts and for the **Python code editor** in the lessons (it downloads Python the first time). Everything else works offline. |
| DB Browser for SQLite | Optional, only to look at the database (Part 7). |

You do **not** need XAMPP, MySQL, Node.js or Composer.

---

## 2. Install PHP (one time)

First check if you already have it. Open **Command Prompt** (Windows key, type `cmd`) and run:

```
php -v
```

If it shows **PHP 8.1 or higher**, skip to Part 3. If it says "php is not recognized", install it:

### Windows

1. Go to **<https://windows.php.net/download>**.
2. Under the newest **PHP 8.x**, download the **x64 Thread Safe ZIP** (not the "Debug Pack" or "Development package").
3. Create the folder `C:\php` and extract the ZIP **into it**, so `C:\php\php.exe` exists.
4. **Turn on the needed extensions:**
   1. In `C:\php`, copy `php.ini-development` and rename the copy to **`php.ini`**.
   2. Open `php.ini` in Notepad, press **Ctrl+F**, and remove the `;` at the start of these lines:
      ```
      extension_dir = "ext"
      extension=mbstring
      extension=pdo_sqlite
      extension=sqlite3
      ```
   3. Save the file.
5. **Add PHP to PATH:**
   1. Press the Windows key, search **Environment Variables**, open **Edit the system environment variables**, click **Environment Variables**.
   2. In the **top box (User variables)** select **Path**, click **Edit**, **New**, type `C:\php`, then **OK** on every window.
6. **Close all Command Prompt windows**, open a new one, and check:
   ```
   php -v
   php -m | findstr /i "sqlite mbstring"
   ```
   You should see a version number, then `mbstring`, `pdo_sqlite`, `sqlite3`.

> **If `php -v` shows an error about `VCRUNTIME140.dll`:** install the free *Microsoft Visual C++ Redistributable (x64)* from Microsoft, then try again.
>
> **Tip:** use `C:\php` (not `Program Files`). Windows blocks edits inside `Program Files` unless you run as administrator.

### macOS

```
brew install php
php -v
```
(Install Homebrew first from <https://brew.sh> if you don't have it. macOS PHP already includes SQLite and mbstring.)

### Linux (Ubuntu/Debian)

```
sudo apt update
sudo apt install php-cli php-sqlite3 php-mbstring
php -v
```

---

## 3. Get the project ready

1. Unzip the project folder somewhere with a **short path and no spaces**, for example:
   - Windows: `C:\ITiKODE`
   - Mac/Linux: `~/ITiKODE`
2. Avoid OneDrive, Desktop or "Downloads" folders if you can. Sync tools can lock the database file.
3. The folder you unzip should contain `index.php`, `login.php`, `start.bat` and this `README.md` directly inside it.

---

## 4. Run the system

### Easiest (Windows): double-click `start.bat`

It creates the database the first time, opens your browser, and starts the server. **Keep the black window open** while you use the site. Closing it stops the site.

### Manual way (Windows, Mac, Linux)

Open a terminal **inside the project folder** and run:

```
php setup.php
php -S localhost:8000 router.php
```

(Windows: if the project is on another drive, switch with `cd /d D:\path\to\ITiKODE`. Mac/Linux: use `./start.sh`.)

Then open **<http://localhost:8000>** in your browser.

- `php setup.php` creates the database and sample accounts. It is safe to run only once; use `--reset` to wipe it (Part 8).
- The first run may take a few seconds.

### XAMPP alternative (if you already use XAMPP)

1. Copy the project folder into `C:\xampp\htdocs\`.
2. Open the XAMPP Control Panel and **Start** Apache (MySQL is not needed).
3. Open <http://localhost/ITiKODE/>.

---

## 5. Log in

| Role | Email | Password |
|---|---|---|
| Admin | admin@itikode.edu | Admin@Itikode2025 |
| Teacher | teacher@itikode.edu | Teacher@2025! |
| Student | student@itikode.edu | Student@2025 |

- Students can also **Create an account** on the login page.
- The **admin console** is at <http://localhost:8000/admin/> (or the "Admin console" button when logged in as admin).
- These are demo passwords. Change them after first login (click your name, then *Change password*).

**What to try:**
- *Student:* open the Pre-test (one question at a time, **Check Answer**), then Motivation, the Topics and the **Try it yourself** code editors.
- *Teacher:* you land on the **Teacher panel**. Build a lesson (Lessons & content, then *New section*), check the Gradebook, and post an announcement. Students see it on their dashboard.
- *Admin:* manage accounts, review the audit log and change security settings in the Admin console. (Lessons are built by teachers.)

---

## 6. Try it on a phone

**Quick look on your computer:** open the site, press **F12**, then **Ctrl+Shift+M**, and pick a phone from the device list.

**On a real phone (same Wi-Fi as the computer):**

1. Stop the server (Ctrl+C), then start it for the network. Windows: double-click **`start-phone.bat`**. Or run:
   ```
   php -S 0.0.0.0:8000 router.php
   ```
2. If Windows Firewall asks, click **Allow access** (private networks).
3. Find the computer's address: run `ipconfig` and read **IPv4 Address** (like `192.168.1.23`). Mac/Linux: `ifconfig` or `ip addr`.
4. On the phone, open `http://192.168.1.23:8000` (use your own address).

Only do this on Wi-Fi you trust. Switch back to `localhost` afterwards.

---

## 7. Look inside the database (optional)

The data is one file: **`data/itikode.db`** (created on first run).

1. Download **DB Browser for SQLite** from <https://sqlitebrowser.org/dl/> (Standard installer) and install it.
2. Open it, click **Open Database**, set the file type to **All files**, choose `data/itikode.db`, and tick **Read-only**.
3. Click the **Browse Data** tab and pick a table:

| Table | What it holds |
|---|---|
| `users` | Accounts, roles, status (passwords are stored as hashes) |
| `user_progress` | Sections each student finished, and their scores |
| `sections` | The 13 lessons and quizzes |
| `questions` | Quiz questions, options and correct answers |
| `audit_log` | Sign-ins, failures and admin changes |
| `settings` | Site name, passing score, security switches |

Student scores in one view (**Execute SQL** tab):

```sql
SELECT u.name, s.name AS section, p.score, p.completed_at
FROM user_progress p
JOIN users u ON u.id = p.user_id
JOIN sections s ON s.id = p.section_id
ORDER BY p.completed_at DESC;
```

**Safety:** keep *Read-only* on, close DB Browser when done (an open editor can cause "database is locked"), and make changes in the **Admin console** instead of the database. Copy `itikode.db` somewhere safe before any experiment.

---

## 8. Stop, reset and start fresh

| I want to... | Do this |
|---|---|
| Stop the server | Click its window and press **Ctrl+C** (or just close the window). |
| Restart | Run `start.bat` again. Your data is kept. |
| Check that everything works (optional self-test, never touches your data) | `php tests/smoke.php` |
| Wipe everything and recreate sample data | `php setup.php --reset` |
| List the sample accounts | `php setup.php --users` |
| Restore the designed lessons after editing them | `php setup.php --refresh-lessons` (overwrites edits to Motivation, Introduction and Topics 1 to 4) |
| Use another port | `php -S localhost:8080 router.php`, then open `http://localhost:8080` |

---

## 9. Troubleshooting

| What you see | Fix |
|---|---|
| `'php' is not recognized` | PHP isn't installed or not on PATH. Redo Part 2, steps 5 and 6, and open a **new** terminal. |
| `could not find driver` | SQLite isn't enabled. In `php.ini` remove the `;` before `extension=pdo_sqlite` and `extension=sqlite3`, save, restart the server. |
| `Call to undefined function mb_substr()` | Remove the `;` before `extension=mbstring` in `php.ini`, then restart the server. |
| `php --ini` says *Loaded Configuration File: (none)* | There's no `php.ini` yet. Copy `php.ini-development` to `php.ini` in the PHP folder (Part 2, step 4). |
| `Access is denied` when saving `php.ini` | PHP is inside `Program Files`. Run Notepad as administrator, or move PHP to `C:\php`. |
| *Failed to listen on localhost:8000 (reason: ...)* | Another program uses that port. Use `php -S localhost:8080 router.php` and open port 8080. |
| Page is blank or shows a PHP error | Make sure you started the server from inside the project folder (where `router.php` is). |
| `database is locked` | Close DB Browser for SQLite, then refresh the page. |
| `attempt to write a readonly database` | The `data` folder isn't writable. Move the project out of OneDrive/Program Files, or give your user write access to `data`. |
| Code editor says *Loading Python...* forever, or *Retry* | It needs internet the first time. Check your connection, then press **Retry**. School networks that block `cdn.jsdelivr.net` will block it. |
| Fonts look plain | The Google Fonts link is blocked offline. The system still works. |
| Sent back to the login page after logging in | Clear the site's cookies for `localhost` (Brave/Chrome: lock icon, then *Cookies*, then remove), close old tabs, and log in again. Also make sure only one copy of the system is running, and check Admin, *Audit log* for a `session.lost` entry and send it to the project owner. |
| Locked out after failed logins | Wait 15 minutes, or as admin open *Accounts* and clear the lockout. |

---

## 10. Notes for the project owner

- **Sharing with the team:** zip only this project folder (not the Figma/Vite files). Delete `data/itikode.db` first so everyone starts with a clean, fresh database (it is recreated on first run).
- **Demo switches** in `config/config.php`: set `DEMO_MODE` to `false` to hide the demo account cards on the login page; set `SHOW_RESET_LINK` to `false` once real e-mail exists.
- **Passwords** for the three sample accounts should be changed before real use. Settings (pass score, lockouts, 2FA) live in **Admin > Settings**.
- **Not for the public internet as is.** This is set up for local or classroom use. For a real server use HTTPS, turn the demo switches off, and put the project behind a proper web server (Apache/Nginx with PHP-FPM).

---

# Reference

## Roles

| Capability                                      | Student  | Teacher  | Admin    |
|-------------------------------------------------|----------|----------|----------|
| Sections unlock in order                        | yes      | all open | all open |
| See quiz answer keys                            | no       | yes      | yes      |
| Build lessons, quizzes and their order          | no       | yes      | no       |
| Run classes: join codes, roster, import students| no       | yes      | no       |
| Gradebook, student progress, CSV export         | no       | yes (own classes) | no |
| Join a class with a code                        | yes      | no       | no       |
| Post to the classroom board                     | no       | yes      | no       |
| Manage accounts, settings, audit log            | no       | no       | yes      |

After signing in, teachers land on the **Teacher panel** (`/teacher/`) and admins on the **Admin console** (`/admin/`). Both have a "View as learner" shortcut.

## Teacher panel (`/teacher/`)

* **Lessons & content**: add, edit, reorder, hide/publish, duplicate or delete (with password) any section. **No HTML needed.** A lesson is a banner (subtitle, label, colour, optional side box) plus *parts*, and each part picks a layout: **Explanation + code practice** (explanation, fact chips, bullet points, analogy, example code, and an optional *Try it yourself* sandbox with task, starter code, output checks, hint and solution), **Card grid** (coloured cards and icon tiles), **Outcomes + checklist** (numbered outcomes and a tick-box panel) or **Video + reflection**. The built-in lessons use these same layouts, so editing them keeps their design. *Preview lesson* shows exactly what students will see before saving. Quizzes, assessments and evaluations have a question editor (options, correct answer, explanation). Lessons with two or more code sandboxes show one part at a time with a **Next** button.
* **Classes**: create a class and share its 6-character **join code**. Students type it on their dashboard ("Join a class") or in the registration form. Teachers can also add existing students who are not in a class yet, or **import a list or CSV** (`name, email`): new emails get a student account with a temporary password (shown once, downloadable as a CSV, and the student must change it at first login). Each class has a roster, can close joining, regenerate its code, be archived, or deleted (students keep their accounts). Teachers only see students in their own classes.
* **Gradebook & analytics**: every student's progress, pre-test, assessment scores, post-test, growth and code practice solved, with search, an optional 30-second refresh and a **Download CSV** button. Click a student for a detailed page.
* **Overview**: class averages and a "Needs attention" list (not started, low progress, low assessment average, or no activity for a week).
* **Announcements**: post updates, coding tips or deadlines to **one class or everyone**. They appear on the matching students' dashboards (pinned posts first; deadlines show a countdown).

## Admin console (`/admin/`)

* **Accounts**: create students/teachers/admins, edit name/email/role, suspend or re-activate, clear lockouts, sign out of every device, set a temporary password (the user must change it), remove two-factor, reset progress, delete.
* **Audit log**: searchable record of sign-ins, failures, lockouts and every admin and teacher change, with IP.
* **Settings & security**: platform/module name, passing score, open registration, lockout rules, idle timeout, mandatory two-factor for admins and teachers.

## Security features

* Argon2id (or bcrypt) password hashing with transparent rehash; strength meter and policy (12+ characters for staff).
* CSRF tokens on every form; HttpOnly + SameSite session cookies; session ID rotated on login; idle and absolute timeouts; sessions can be bound to the browser (`BIND_SESSION_TO_UA` in `config/config.php`, off by default); admin idle cap of 15 minutes.
* Brute-force protection: per-account lockout (unknown emails lock identically, so accounts cannot be discovered), per-IP throttling, honeypot field.
* Two-factor sign-in (TOTP; works with Google/Microsoft Authenticator, Authy, 1Password) with one-time recovery codes and replay protection.
* Forced password change for admin-created accounts; reset tokens are stored hashed and expire in 30 minutes; resets, suspensions and role changes end every session.
* Admin safeguards: password re-confirmation on sensitive actions; cannot delete, demote or suspend yourself or the last admin; no remember-me for admins.
* Security headers, protected data directory, full audit trail.

## Look and feel

The learner pages (dashboard and every section) use the layout from the Figma design: rounded frame, green sidebar with numbered sections, white top bar, hero cards, and a one-question-at-a-time quiz with a **Check Answer** button. Styles live in `assets/app.css` (classes starting with `lx-`), the quiz player in `assets/learn.js`, and the page frame in `includes/shell.php` + `includes/sidebar.php`.

Quiz answers are checked on the server: the browser never receives the answer key before a student commits to a choice, and the first choice for each question is locked for that attempt. Use **Retake** to start a new attempt. To rename the sidebar subtitle (for example to "Module 1: OOP in Python"), change the module name in **Admin > Settings**.

## Lessons with live coding

Motivation, Introduction & ILOs and Topics 1 to 4 use the designed lesson layout: learn card, facts, analogy, example, and a **Try it yourself** editor that runs real Python in the browser (Pyodide, loaded from a CDN, so the first run needs internet). Every part of Topics 1 to 4 has **two steps**: (1) *Learn + Try it yourself*, then (2) a **card game + Practice**. In the card game (a 4 x 4 board: 8 words, two matching cards each) students flip two cards at a time and find the matching words; each match is added to a **Collected items** list. In the Practice, blanks (`___`) in the code must be **typed** by the student, using the collected words as a guide. Both steps must be solved before the next part unlocks. A student who is stuck gets a **Skip for now** button after 5 wrong runs (teachers can change this per part); a skipped practice stays unsolved, is marked on the stepper, and must be solved before the lesson's Next button to the quiz appears. The system records wrong runs, card-game misses, time and skips so teachers can see who struggled (student page: *Practice effort*; gradebook: *Skipped* column; overview: *Needs attention*).

Topics 1 to 4 show **one practice at a time**: solve it, press **Next practice** (pinned at the bottom of the screen), and the next one replaces it, like turning a page. Students can go back to earlier practices, or use *Skip for now* if they are stuck; teachers and admins can open any practice freely. The editor checks the program's output, lights a star when it matches, and remembers each student's progress in their browser.

* Teachers edit these lessons in the **Teacher panel** with simple forms (no HTML), including the practice code and the card words of each round. The editor can fill the card words from the blanks in your code, warns if a word the blanks need is missing, and can switch a round to "clue and answer" cards. Saving a built-in lesson keeps its design.
* When the built-in lessons change in a new version, teachers see an **Update built-in lessons** button on the Teacher overview (or run `php setup.php --refresh-lessons`). Updating overwrites any teacher edits to those six lessons; students' progress is kept.

## Layout

```
itikode-php/
├── admin/            Admin console (overview, sections, section editor, accounts, audit, settings)
├── assets/           app.css, auth.js, learn.js (quiz player, Python editor, mobile menu)
├── config/           config.php (switches + sample accounts), database.php (connection + first-run seed)
├── database/         schema.sql (all tables)
├── data/             itikode.db (created on first run, git-ignored)
├── includes/         seed_lessons (designed lesson HTML), auth, security, functions, content (DB-backed), ui, shell (learner layout), sidebar, header/footer (account + admin), seed_content (first-run lessons)
├── login.php  two_factor.php  register.php  forgot_password.php  reset_password.php  logout.php
├── account.php       Password, two-factor, sessions, activity
├── index.php  section.php
├── router.php        Router for `php -S`
└── setup.php         CLI database setup
```

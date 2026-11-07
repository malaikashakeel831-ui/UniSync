# UniSync — Complete Project Guide

**Team:** Syeda Laraib (BCSF24A002) · Khadija Shakeel (BCSF24A034)
**What this guide covers:** Linux setup → GitHub for a 2-person team → database → Apache → understanding the code → testing → deployment → submission.

> ⚠️ **Your teacher's three rules, and how this guide meets them**
> 1. **Linux only, no XAMPP.** Everything here runs on Ubuntu with **Apache + MySQL + PHP** (the "LAMP" stack: Linux, Apache, MySQL, PHP). Do **not** install XAMPP/WAMP/Laragon.
> 2. **Git commit history is checked.** Both of you commit from your own GitHub accounts, in small steps, as you set up and test each module. The steps are in Part 3.
> 3. **Nothing counts after the deadline.** Your last commit and push must be **before Sunday's deadline**. Push early, then submit the Google Form. Do not commit "small fixes" after the deadline. They change the timestamp and the submission may be ignored.

---

## Part 0 — Plan for the time you have

| When | Who | What |
|---|---|---|
| First 1–2 h | Both | Part 1 (Linux + LAMP) and Part 2 (GitHub accounts, git config) on **each** laptop |
| Next 1 h | Laraib | Create the repo, add Khadija, push the first modules (Part 3) |
| Same time | Khadija | Clone, set up the database, push her modules (Part 3) |
| Next 1–2 h | Both | Run the app, test with the checklist (Part 7), fix anything, commit |
| Next 1 h | One of you | Deploy online (Part 8). This is optional but impressive. |
| Before deadline | Both | Final push, tag `v1.0`, submit the form, practise the demo (Part 9) |

---

## Part 1 — Set up Linux and the LAMP stack (each member, on own laptop)

### 1.1 Get Linux

Pick **one** option:

- **A. You already have Ubuntu** (installed or dual-boot): skip to 1.2.
- **B. Windows 10/11 → WSL2 Ubuntu** (fastest, a real Ubuntu Linux inside Windows):
  1. Open **PowerShell as Administrator** and run:
     ```powershell
     wsl --install -d Ubuntu-24.04
     ```
  2. Restart the PC. Ubuntu opens and asks you to create a Linux username and password. Remember them.
  3. From now on, open the **Ubuntu** app from the Start menu. All commands below go there.
- **C. VirtualBox + Ubuntu Desktop ISO** if WSL is not allowed or doesn't work.

> 💡 WSL2 runs a genuine Ubuntu kernel, so it is Linux, not an XAMPP-style package. If you are unsure whether your teacher accepts WSL, ask quickly or use option C.

### 1.2 Install Apache, MySQL, PHP and Git

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server php libapache2-mod-php php-mysql php-mbstring php-intl git unzip curl
```

Check the versions:
```bash
php -v          # PHP 8.x
mysql --version # MySQL 8.0.x
apache2 -v
git --version
```

Start the services:
```bash
# Normal Ubuntu:
sudo systemctl enable --now apache2 mysql
# WSL (if systemctl says "System has not been booted with systemd"):
sudo service apache2 start
sudo service mysql start
```

Open **http://localhost** in your browser. You should see the "Apache2 Ubuntu Default Page". ✅

Optional but recommended, to secure MySQL:
```bash
sudo mysql_secure_installation
# "VALIDATE PASSWORD component?" -> y, then choose 1 (MEDIUM)
# answer y to: remove anonymous users, disallow root login remotely, remove test database, reload privileges
```
> On Ubuntu, MySQL's root user logs in with `sudo mysql` (no password needed). The project uses its own user called `unisync`, created in Part 4.

### 1.3 Editor
Install **Visual Studio Code**. On WSL, install the **"WSL" extension**, then in Ubuntu type `code .` inside the project folder to open it.

---

## Part 2 — GitHub setup (each member)

1. Both of you need a GitHub account (use your university email if possible).
2. Tell git who you are. **Use the same email as your GitHub account**, or your commits will not be linked to your profile and the history will look like it was all done by one person.
   ```bash
   git config --global user.name  "Syeda Laraib"          # Khadija uses her own name
   git config --global user.email "laraib@example.com"    # your GitHub email
   git config --global init.defaultBranch main
   ```
3. Log in to GitHub from the terminal. The easiest way is the GitHub CLI:
   ```bash
   sudo apt install -y gh
   gh auth login        # GitHub.com → HTTPS → Login with a web browser
   ```
   (Alternative: create an SSH key with `ssh-keygen -t ed25519 -C "you@email"` and add `~/.ssh/id_ed25519.pub` in GitHub → Settings → SSH keys.)

---

## Part 3 — Create and share one repository (team workflow)

### 3.1 Laraib creates the repository (owner)
1. GitHub → **New repository** → name `unisync` → **Public** (or Private and add your teacher as a collaborator) → no README → **Create**.
2. **Settings → Collaborators → Add people →** Khadija's GitHub username. Khadija accepts the email invitation.

### 3.2 Laraib: set up the project folder on Linux
We keep the project in `/var/www/unisync` so Apache can serve it.
```bash
sudo mkdir -p /var/www/unisync
sudo chown -R $USER:$USER /var/www/unisync
cd /var/www/unisync
git init
git remote add origin https://github.com/<laraib-username>/unisync.git
```
Copy the project files you received (unzip `unisync.zip`) into `/var/www/unisync`.
- WSL: your Windows Downloads folder is at `/mnt/c/Users/<WindowsName>/Downloads/`
  ```bash
  cd /tmp && unzip /mnt/c/Users/<WindowsName>/Downloads/unisync.zip && cp -r unisync/. /var/www/unisync/ && cd /var/www/unisync
  ```

### 3.3 Commit in meaningful steps (both of you)

**Do not push everything as one giant commit.** Your history should show the real work: setting up, testing and committing each part, by the person responsible for it. Before committing a part, **read the files and run the feature** so you can explain them in the viva.

Suggested split (it follows your documentation's Team Responsibilities):

**Laraib — first commits (from her laptop):**
```bash
cd /var/www/unisync
git add .gitignore README.md database/
git commit -m "Add database schema, seed data and project README"

git add app/config.php app/config.local.example.php app/db.php app/.htaccess .htaccess
git commit -m "Add configuration and PDO database connection"

git add app/helpers.php app/csrf.php app/auth.php app/bootstrap.php
git commit -m "Add authentication, sessions, CSRF protection and helpers"

git add public/login.php public/register.php public/logout.php public/profile.php app/views/auth_art.php
git commit -m "Add registration, login with lockout, logout and profile"

git push -u origin main
```

**Khadija — clone and add the UI layer (from her laptop):**
```bash
sudo mkdir -p /var/www/unisync && sudo chown -R $USER:$USER /var/www/unisync
git clone https://github.com/<laraib-username>/unisync.git /var/www/unisync
cd /var/www/unisync
```
Copy the remaining files from the zip into the folder **without overwriting** what is already there (`cp -rn unisync/. /var/www/unisync/`), then:
```bash
git checkout -b feature/ui-layout
git add public/assets/ app/views/header.php app/views/footer.php app/views/guest_header.php app/views/error.php public/index.php public/.htaccess
git commit -m "Add design system, layout, landing page and assets"
git push -u origin feature/ui-layout
```
On GitHub: **Compare & pull request → Create pull request**. Laraib opens it → **Merge pull request**. That is the professional team workflow, and teachers like seeing PRs.

Then each of you continues, one feature at a time:

| Member | Branch | Files | Commit message |
|---|---|---|---|
| Laraib | `feature/courses-tasks` | `public/semesters.php public/courses.php public/tasks.php public/api/task_toggle.php` | Add semester, course and task management with AJAX completion |
| Laraib | `feature/dashboard` | `public/dashboard.php app/services/AnalyticsService.php app/services/NotificationService.php public/notifications.php public/api/notifications_read.php` | Add dashboard, workload meter and notifications |
| Khadija | `feature/timetable` | `public/timetable.php` | Add weekly timetable and month calendar with heavy days |
| Khadija | `feature/gpa` | `app/services/GpaService.php public/gpa.php public/api/whatif.php` | Add GPA/CGPA planner with what-if and required marks |
| Khadija | `feature/planner` | `app/services/PlannerService.php public/planner.php` | Add rule-based smart study planner |
| Khadija | `feature/groups` | `public/groups.php public/group_view.php public/api/kanban_move.php` | Add group projects with Kanban board |
| Khadija | `feature/analytics` | `public/analytics.php` | Add Chart.js performance analytics |
| Laraib | `feature/admin` | `public/admin/` | Add admin dashboard and user management |
| Both | `main` | `docs/` + fixes found in testing | Add project guide / Fix … |

For each row:
```bash
git checkout main && git pull            # always start from the latest main
git checkout -b feature/gpa
git add <files>
git commit -m "<message>"
git push -u origin feature/gpa
# GitHub → open Pull Request → the other member reviews → Merge
```

> Note: `app/bootstrap.php` loads all four services, so the app only runs fully once every service file is in. Until then, test individual pages after pulling the latest `main`.

### 3.4 Everyday git commands

| Situation | Command |
|---|---|
| Get your partner's latest work | `git checkout main && git pull` |
| See what changed | `git status` and `git diff` |
| See history | `git log --oneline --graph --all` |
| Undo changes to a file you haven't committed | `git restore <file>` |
| Update your branch with new `main` | `git checkout feature/x && git merge main` |

**Merge conflict?** Git marks the file like this:
```
<<<<<<< HEAD
your version
=======
partner's version
>>>>>>> main
```
Keep the correct lines, delete the markers, then `git add <file> && git commit`.

**Never commit** `app/config.local.php`. It holds your database password, and `.gitignore` already excludes it.

---

## Part 4 — Create the database (each member)

```bash
cd /var/www/unisync
sudo mysql < database/create_database.sql                 # creates DB "unisync" + user "unisync"
mysql -u unisync -p'UniSync@2026' unisync < database/schema.sql
mysql -u unisync -p'UniSync@2026' unisync < database/seed.sql
cp app/config.local.example.php app/config.local.php      # your local DB settings (not committed)
php database/demo_seed.php                                # demo students + data for the presentation
```

Check it worked:
```bash
mysql -u unisync -p'UniSync@2026' unisync -e "SHOW TABLES; SELECT email, role FROM users;"
```

> Prefer phpMyAdmin? `sudo apt install phpmyadmin` (choose apache2), then open http://localhost/phpmyadmin. It is optional and the command line is enough.

---

## Part 5 — Configure Apache (each member)

Point Apache at the **public/** folder, so `app/` and `database/` can never be opened from the browser.

```bash
sudo tee /etc/apache2/sites-available/unisync.conf > /dev/null <<'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/unisync/public
    <Directory /var/www/unisync/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/unisync_error.log
    CustomLog ${APACHE_LOG_DIR}/unisync_access.log combined
</VirtualHost>
EOF
sudo a2enmod rewrite headers
sudo a2dissite 000-default
sudo a2ensite unisync
sudo apache2ctl configtest            # must say "Syntax OK"
sudo systemctl reload apache2         # WSL: sudo service apache2 reload
```

Open **http://localhost**. You should see the UniSync landing page. 🎉

| Account | Email | Password |
|---|---|---|
| Admin | admin@unisync.local | Admin@123 |
| Student 1 | laraib@demo.com | Demo@1234 |
| Student 2 | khadija@demo.com | Demo@1234 |

**Quick alternative for coding** (no Apache config): `php -S localhost:8000 -t public`, then open http://localhost:8000.

Errors? `sudo tail -f /var/log/apache2/unisync_error.log`, and set `'debug' => true` in `app/config.local.php`.

---

## Part 6 — Understand the code (for the viva)

### 6.1 How a request flows
```
Browser ──► public/tasks.php
              └─ require app/bootstrap.php
                   ├─ config.php (+ config.local.php)
                   ├─ secure session (HttpOnly, SameSite, timeout)
                   ├─ verify_csrf() on every POST
                   └─ loads db, helpers, auth, services
              └─ require_student()      ← must be logged in, account active
              └─ handle POST (validate → prepared SQL with user_id from session) → redirect
              └─ query data → include views/header.php → HTML → views/footer.php
```
This is the **Post/Redirect/Get** pattern: every form posts, then redirects, so refreshing never re-submits.

### 6.2 Files and what they do

| File | Purpose |
|---|---|
| `app/bootstrap.php` | Starts everything: config, session, security headers, automatic CSRF check |
| `app/db.php` | `db()` PDO connection; helpers `q()`, `q1()`, `qa()`, `qv()` run prepared statements |
| `app/auth.php` | `attempt_login()` (lockout logic), `require_login/student/admin()`, `require_group_member()` |
| `app/csrf.php` | `csrf_token()`, `csrf_field()`, `verify_csrf()` with `hash_equals` |
| `app/helpers.php` | `e()` escaping, `url()`, `redirect()`, flash messages, `abort(404/403)`, `audit()` |
| `app/services/GpaService.php` | Course %, letter, points, semester GPA, CGPA, required marks |
| `app/services/PlannerService.php` | Priority score + allocation of study blocks + overload warnings |
| `app/services/AnalyticsService.php` | Task counts, workload, weekly hours, streak, on-time rate |
| `app/services/NotificationService.php` | Creates due-soon / overdue / group alerts without duplicates |
| `public/api/*.php` | JSON endpoints for AJAX (task toggle, Kanban move, what-if, notifications) |
| `public/assets/js/app.js` | Theme toggle, sidebar, dropdowns, modals, AJAX, drag-and-drop, validation |
| `public/assets/css/app.css` | The complete design system (light + dark mode, responsive) |

### 6.3 Key code to explain

**Ownership check (the golden rule).** The user id comes from the session, never from the browser:
```php
$task = q1('SELECT * FROM tasks WHERE id = ? AND user_id = ?', [$taskId, uid()]);
if (!$task) abort(404);   // not found OR not yours: same answer, nothing revealed
```

**Password storage:** `password_hash($pass, PASSWORD_DEFAULT)` at registration, `password_verify()` at login.

**Lockout:** each wrong password increments `failed_logins`. At 5, `locked_until = NOW() + 15 min`.

**CSRF:** every form has `<?= csrf_field() ?>`, and `fetch()` sends the token in an `X-CSRF-Token` header. `bootstrap.php` rejects any POST without a valid token (HTTP 419).

**XSS:** everything printed goes through `e()` = `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

**GPA formula** (`GpaService::calculate`):
```
course % = Σ(obtained/total × weight) / Σ(weight of graded items) × 100
GPA      = Σ(points × credit hours) / Σ(credit hours)
needed % = (target% − earned) / remaining weight × 100     (> 100 → "not achievable")
```
Worked example from the documentation: Quiz 10% (8/10) + Midterm 30% (24/30) → 32/40 = 80% → A- (3.67). ✔ This is verified by test 10.

**Study planner** (`PlannerService::generate`):
```
score = 10 / max(days until due, 0.5) + importance(high 3, medium 2, low 1) + 2 if exam
sort by score ↓ → for each task, place ≤2 h blocks into the earliest days with free hours
before its deadline → if hours remain → "Need X more hours before <day>" warning
```
Hours already logged against a task are subtracted, and pressing **Done** on a block logs a `study_sessions` row.

**Admin privacy:** admin pages run only `COUNT(*)` queries and account info. Students' pages call `require_student()`, which sends admins away.

---

## Part 7 — Testing checklist (from your documentation)

| # | Test | How | Expected |
|---|---|---|---|
| 1 | Duplicate email | Register with `laraib@demo.com` | "already registered" |
| 2 | Weak / mismatched password | `abc` / `xyz` | Validation errors |
| 3 | Brute force | Wrong password 5× | "Account locked for 15 minutes" |
| 4 | Logged-out access | Log out, open `/dashboard.php` | Redirect to login |
| 5 | IDOR | As Laraib, open `/tasks.php?edit=<Khadija's task id>` | 404 page |
| 6 | Student → admin | As student, open `/admin/users.php` | 403 |
| 7 | Role tampering | DevTools: add `<input name="role" value="admin">` to the register form | Account is still student |
| 8 | CSRF | DevTools: delete the hidden `csrf` input, submit | 419 "Session expired" |
| 9 | XSS | Task title `<script>alert(1)</script>` | Shown as text, no popup |
| 10 | GPA accuracy | Quiz 10% 8/10, Mid 30% 24/30 | 80% = A- (3.67) |
| 11 | Overload | Add a task due tomorrow with 20 estimated hours | Red overload warning in Planner |
| 12 | Group privacy | Register a third user, open `/group_view.php?id=1` | 403 Not a member |
| 13 | Deactivated user | Admin deactivates a student → student logs in | Login refused |
| 14 | Mobile | DevTools → phone mode (Ctrl+Shift+M) | Sidebar becomes ☰ menu, no sideways scrolling |

All 14 were run against this code and pass. Take screenshots of a few for your report and put them in `docs/screenshots/`.

---

## Part 8 — Deploy online (free)

### Option A — InfinityFree (free PHP + MySQL hosting on Linux/Apache)
1. Sign up at **infinityfree.com** → **Create Account** → choose a free subdomain, e.g. `unisync-pu.infinityfreeapp.com`.
2. Open the **Control Panel** → **MySQL Databases** → create a database named `unisync`. Note the **MySQL Host** (like `sql123.infinityfree.com`), **Username** (`if0_xxxxxxx`), **Database name** (`if0_xxxxxxx_unisync`) and the password (your hosting account password, shown in the client area).
3. Click **phpMyAdmin** next to the database → **Import** → upload `database/schema.sql` → Go. Then import `database/seed.sql`.
   - For demo data online: run `php database/demo_seed.php` on your laptop, then `mysqldump -u unisync -p'UniSync@2026' unisync > demo_dump.sql`, and import `demo_dump.sql` in phpMyAdmin instead of `seed.sql`.
4. Create a file `app/config.local.php` **on your laptop, outside git** with the hosting values:
   ```php
   <?php
   return [
       'db_host' => 'sql123.infinityfree.com',
       'db_name' => 'if0_xxxxxxx_unisync',
       'db_user' => 'if0_xxxxxxx',
       'db_pass' => 'your-hosting-password',
       'debug'   => false,
   ];
   ```
5. Upload with **FileZilla** (`sudo apt install filezilla`). Use the FTP details from the control panel: host `ftpupload.net`, port 21, your `if0_` username and password. Open the `htdocs` folder on the right and **upload the whole project contents** (`app/`, `public/`, `database/`, `.htaccess`, …) into `htdocs`. Delete the default `index2.html` if it is there.
   The root `.htaccess` sends every request into `public/` and blocks `app/` and `database/` (403).
6. Wait a few minutes for the new domain to activate, then open your subdomain. Log in as admin and **change the admin password** in Profile.

### Option B — Your own Linux server (VPS, e.g. a cheap Ubuntu droplet)
Follow Parts 1, 4 and 5 on the server, using `git clone` instead of copying files, then add HTTPS:
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com
```

Put the live link in your README and on the submission form.

---

## Part 9 — Submission checklist and demo script

**Before the deadline:**
- [ ] `git checkout main && git pull` on both laptops. Everything is merged.
- [ ] `app/config.local.php` is **not** on GitHub (check the repo in the browser).
- [ ] README shows your names, roll numbers and (if deployed) the live URL.
- [ ] Tag the submission:
  ```bash
  git tag -a v1.0 -m "Final submission"
  git push origin main --tags
  ```
- [ ] Open the repo on GitHub → **Commits**: both names appear, and the last commit time is before the deadline.
- [ ] Submit the Google Form with the repo link (and the live link).
- [ ] **Stop committing.**

**Demo (about 7 minutes), following section 12 of your documentation:**
1. Register a new student → empty dashboard with the welcome notification.
2. Add a semester, 3 courses (pick colours), and timetable slots.
3. Add tasks, including one overdue (red), and tick one done (AJAX, no reload).
4. GPA Planner: enter marks → live GPA; type what-if marks; choose target "A" → marks needed.
5. Planner: set hours → generated plan; add a huge task due tomorrow → overload warning.
6. Groups: create a group, add the teammate's email, drag Kanban cards → progress bar moves.
7. Analytics charts; toggle dark mode 🌙; show the phone layout.
8. Log in as the second student, change the URL id to the first student's task → 404.
9. Log in as admin → statistics → deactivate an account → that student can't log in.

---

## Part 10 — Troubleshooting

| Problem | Fix |
|---|---|
| "Database connection failed" | Check `app/config.local.php`; is MySQL running? (`sudo service mysql start`) |
| Blank page / 500 | `sudo tail /var/log/apache2/unisync_error.log`; set `'debug' => true` |
| 404 on every page except home | Run `sudo a2enmod rewrite`, make sure `AllowOverride All` is set, reload Apache |
| CSS not loading | Check that DocumentRoot points to `/var/www/unisync/public` |
| "Session expired" on every form | Cookies are blocked, or you opened the site under two hostnames (localhost vs 127.0.0.1) |
| Permission denied writing files | `sudo chown -R $USER:$USER /var/www/unisync` |
| `git push` rejected | `git pull --rebase origin main`, fix any conflicts, push again |
| Locked out during testing | Wait 15 minutes, or ask admin → Manage Users → Unlock |
| `ERROR 1819 Your password does not satisfy the current policy` | Use a stronger password in `create_database.sql` **and** `config.local.php` (8+ chars, upper + lower case, number, symbol) |
| WSL: services stopped after restart | `sudo service apache2 start && sudo service mysql start` |

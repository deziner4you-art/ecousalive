# ECO A+ PRO — Project Documentation

## For Any AI Assistant
Before starting any work, read both files:
1. `CLAUDE.md` — architecture, roles, deployment, invariants
2. `AI_CONTEXT.md` — payroll D4U business logic, formulas, JS/PHP patterns

After completing any task, update the relevant section in `AI_CONTEXT.md`:
- New feature add ki → us section mein document karo
- Business logic change hui → formula/example update karo
- New DB column/table → Database Tables section update karo
- Bug fix → relevant pattern note karo
- JS function change → JS Functions section update karo

`AI_CONTEXT.md` ko hamesha current rakho — ye next AI session ka starting point hai.

## Overview

EcoQuality USA's internal content management system for Amazon A+ listings.
Manages tasks from Pending → content generation → QA → SEO → Listing → Published.
Handles invoicing (client-facing) and payroll (worker-facing) from one interface.

**Live URL:** `https://ecousa.deziner4you.com`
**Test URL:** `https://ecousa.deziner4you.com` (test DB, same URL)

---

## Architecture

Custom PHP MVC — no framework, no Composer, no npm. Hostinger shared hosting compatible.

```
aplus/
├── app/
│   ├── Controllers/      AuthController, TaskController, InvoiceController,
│   │                     PayrollController, AdminController, DashboardController
│   ├── Helpers/          Database.php (db() singleton), Response.php (json_*),
│   │                     Migration.php (reference only — NOT called at runtime)
│   ├── Middleware/       AuthMiddleware, RoleMiddleware
│   └── Models/           Task, User, Invoice, Payslip
├── config/
│   ├── database.php      Reads .env → defines DB_* constants
│   ├── app.php           APP_NAME, APP_VERSION, CSRF_TOKEN_NAME, GA_ID
│   └── roles.php         ROLE_ADMIN … ROLE_SEO constants + ALL_ROLES array
├── docker/               Local dev only (PHP 8.2 + MySQL 8)
├── ecousa-main/          Original monolith backup (production rollback)
├── public/               Web root — Hostinger document root points here
│   ├── index.php         Front controller (single entry point)
│   ├── .htaccess         RewriteEngine → index.php
│   ├── css/app.css       All CSS extracted verbatim from monolith
│   ├── js/               core.js, tasks.js, admin.js, invoices.js,
│   │                     payroll.js, pwa.js
│   └── assets/           manifest.json, sw.js, icon-192.png
├── routes/web.php        55+ action → [Controller, method] dispatch table
├── views/
│   ├── layouts/          app.php (main), auth.php (login)
│   ├── auth/             login.php
│   ├── dashboard/        admin, worker, client, qa, writer, seo, listing
│   ├── tasks/            index.php (filter bar + task list)
│   ├── invoices/         index.php
│   └── payroll/          index.php
├── .env                  DB credentials (never commit)
└── .env.example          Safe template
```

---

## Databases

| Purpose | Name | User | Host |
|---|---|---|---|
| **Testing** | `u486267412_eco_aplus_test` | `u486267412_eco_aplus_test` | `127.0.0.1` |
| **Production** | `u486267412_eco_aplus` | `u486267412_eco` | `127.0.0.1` |

**CRITICAL:** Never modify the database schema. Both DBs are production-identical.
The test DB is a live clone for safe testing only. After testing, swap `.env` credentials back to production.
`Migration.php` exists as reference only — `runMigrations()` is never called from `index.php`.

To switch to production after successful testing, update `.env`:
```
DB_NAME=u486267412_eco_aplus
DB_USER=u486267412_eco
DB_PASS=Eco12345@DB
APP_URL=https://deziner4you.com/aplus
```

---

## Roles & Access

| Role constant | String | Access |
|---|---|---|
| `ROLE_ADMIN` | `administrator` | Everything |
| `ROLE_CLIENT` | `eco_client` | Products + Invoices + Hold/Urgent |
| `ROLE_WORKER` | `worker` | Assigned tasks + Work controls |
| `ROLE_QA` | `qa` | In QA tasks + submit links |
| `ROLE_WRITER` | `d4u_writer` | Pending tasks + Save Draft |
| `ROLE_SEO` | `seo_manager` | SEO Review tasks |
| `ROLE_LISTING` | `eco_listing` | Work Done → Publish |

---

## Request Lifecycle

```
Browser → public/index.php
         ↓
         AuthMiddleware::handle()   (session + force-logout check)
         ↓
         $action = POST/GET action
         ↓
         if action → routes/web.php → [Controller, method]()
         else      → DashboardController::show() → role view
```

All `fetch()` calls from JS use `index.php?action=X` (backward compatible).

---

## JavaScript Architecture

Six files loaded in order by `views/layouts/app.php`:

| File | Responsibility |
|---|---|
| `core.js` | Globals (`ROLE`, `ALL_TASKS`…), CSRF fetch interceptor, tab switching, work-status helpers |
| `tasks.js` | `loadTasks`, `renderSmart`, `buildCard`, diff viewer, blunder system, worker assignment |
| `admin.js` | User CRUD, permissions grid, login analytics |
| `invoices.js` | Invoice panel, cart, create/print/share |
| `payroll.js` | Payslips, worker rates, ledger, progress dashboard |
| `pwa.js` | Service worker + install banner |

**PHP → JS bridge** (injected in layout before all JS):
```html
<script>
var ROLE       = <?= json_encode($user['role']) ?>;
var USER_ID    = <?= json_encode($user['id'])   ?>;
var APP_URL    = <?= json_encode(APP_URL)        ?>;
var CSRF_TOKEN = <?= json_encode(csrf_token())   ?>;
</script>
```

**CSRF:** `core.js` wraps `window.fetch` — every POST FormData automatically gets `_csrf` appended.

---

## Security

- `.env` blocked by root `.htaccess`
- `app/`, `config/`, `routes/`, `views/` each have `Deny from all`
- Session: `cookie_httponly=1`, `cookie_samesite=Lax`
- CSRF tokens on all mutating POST actions
- `verify_csrf()` checks `$_POST['_csrf']` or `HTTP_X_CSRF_TOKEN`
- Passwords: `password_hash()` / `password_verify()` (bcrypt)
- All DB queries use PDO prepared statements

---

## Local Development (Docker)

```bash
cd docker
docker-compose up -d
```

App at `http://localhost:8080`

Copy `.env.example` to `.env` and set local DB credentials.
The Docker MySQL uses:
- DB: `eco_aplus_local`
- User: `eco` / `local_pass`

---

## Deployment to Hostinger

1. Upload `aplus/` directory (excluding `APLUS/`, `APLUS SPLIT PARTS/`, `ECO/`, `ECO_LEE/`, `Social Media/`, `smm-handle/`, `docker/`)
2. Set document root to `aplus/public/` in hPanel
3. Ensure `.env` has correct production credentials
4. Test all major actions before going live

**Zero-downtime cutover:**
1. Deploy to `aplus_v2/` alongside live `aplus/`
2. Smoke-test `aplus_v2/public/`
3. Rename: `aplus/` → `aplus_backup/`, `aplus_v2/` → `aplus/`

---

## Key Invariants

- ❌ No DB schema changes — tables and columns as-is
- ❌ No action name changes — all JS `fetch('index.php?action=X')` calls unchanged
- ❌ No session variable rename — `$_SESSION['eco_user']` throughout
- ❌ No CSS class name changes — `.Working`, `.InQA`, `.WorkDone`, etc.
- ❌ No password hashing algorithm change

---

## Task Status Flow

```
Pending
  ↓ (writer saves draft)
Generated
  ↓ (client approves/updates)
Approved | Updated
  ↓ (worker starts)
Working → Paused → Working
  ↓ (worker submits)
In QA
  ↓ (QA submits links)
SEO Review
  ↓ (SEO submits)
Work Done
  ↓ (listing publishes)
Published
```

Special flows:
- **Hold** — client can hold any non-done product
- **Blunder** → **Reworking** → **Reworking Done** → back to QA
- **Info + A Plus** — worker does Info Work → Content Pending → (writer generates) → Approved → Work Done

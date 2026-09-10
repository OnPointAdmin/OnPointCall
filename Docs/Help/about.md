# About OnPoint Marketing’s Lead Booking Application

OnPoint Marketing’s Lead Booking Application is the company’s timeshare-tour calling platform. It replaces the previous Google Sheets workflow. The goal of every call is to book a lead on a timeshare tour for resort-client partners.

**The application never places phone calls.** Agents dial manually on their personal phones. That is a deliberate TCPA posture: a human initiates every call, and the software cannot dial for them.

## Who uses it

| Role | Where they work | What they do |
|------|-----------------|--------------|
| **Admin** | `/admin` | User management, configuration, compliance settings, and all manager capabilities |
| **Manager** | `/admin` | Import leads, assign lists, run reports, manage callbacks, and day-to-day operations |
| **Agent** | `/agent` | Get next lead, disposition calls, book tours, and manage personal callbacks |

Calling ability comes from **list assignment**, not from role alone. A manager or admin who is assigned to a calling list also gets the agent workspace for that work.

## How work flows

1. **Import CSV** — Leads land in a holding area. They are not callable until released.
2. **Optional checks** — Queued screening may run on import: Soft Score, FCC Reassigned Numbers, Salesforce partner qualification, and DNC.com scrub.
3. **Assign Leads** — A manager releases matching holding leads onto a calling list (for example Standard or TNB).
4. **Agent calling** — Agents use Get Next Lead (callbacks first, then the shared pool). Each lead is leased for 20 minutes while an agent works it.
5. **Disposition and booking** — After each attempt the agent records an outcome. Booked leads open a Formstack booking URL with lead data pre-filled.

## What it is built in

| Layer | Technology |
|-------|------------|
| Application | Laravel 13 (PHP 8.4) |
| Admin | Filament 4 |
| Agent workspace | Livewire, Blade, and Tailwind |
| Database | PostgreSQL 16 |
| Reverse proxy | Caddy |
| Background work | Laravel database queue and scheduler |
| Email | Laravel Mail (SMTP or Resend in production; log driver locally) |

Sign-in uses email and password. Administrators invite users from Admin → Users.

## How it is hosted

The same **Docker Compose** stack runs locally and in production:

| Service | Purpose |
|---------|---------|
| `caddy` | HTTPS reverse proxy to PHP |
| `app` | Laravel (PHP-FPM) |
| `queue` | Background jobs (imports, checks, digests) |
| `db` | PostgreSQL |

**Local development** runs on Windows with Docker. Open http://localhost for sign-in, `/admin` for management, and `/agent` for the calling workspace.

**Production** runs on a single VPS. The application directory is `/opt/onpointcall`. Deploys pull GitHub `master` with `scripts/deploy.ps1`. Migrations run forward only — the database is never wiped as part of a deploy.

Nightly database backups (`pg_dump`) are stored off-box in Backblaze B2.

## Integrations

| Service | Purpose |
|---------|---------|
| **Soft Score** (`prod.onpointapi.com`) | Lead quality scoring on demand and optional import batch |
| **Salesforce** | Partner qualification checks against CRM data |
| **FCC Reassigned Numbers** | Phone number reassignment screening on import |
| **DNC.com** | Do-not-call scrub on import |
| **Formstack** | Tour booking forms (default URL in App Settings; per-list overrides for TNB) |

## Getting help

More how-to articles for specific admin screens will be added under **Help** in the sidebar. For technical operations (Docker, deploy, tests), see `Docs/COMMANDS.md` in the repository.

# On Point Marketing — Call Center Web App Specification

**Version 2.0 — for AI-assisted build (Cursor). Supersedes v1.0 entirely.**

Major changes from v1: calling lists with a shared lead pool replace direct agent assignment; disposition-driven lead ownership; skip tracking; per-list cadence and exhaustion settings; multi-tenant groundwork; Google + Microsoft auth; Salesforce pull integration.

---

## 1. What this is

A mobile-first web application for a small telemarketing team (≤5 agents per tenant to start) that books timeshare tours. Agents call leads from their **personal cell phones**. The app manages which leads get served, when, and to whom — it never places calls itself.

**The win condition:** disposition = `Booked` (a booked timeshare tour).

**Core model:** leads belong to **Calling Lists** and sit in a shared pool. Agents are assigned to lists, not leads. An agent taps **Next Lead** and the system serves the best eligible lead from that list. A lead becomes "owned" by an agent only through specific dispositions (see Section 8). Attempt cadence (which time-of-day block each successive attempt must land in) is enforced per list.

---

## 2. Hard constraints — never violate these

### 2.1 TCPA safety (legal, non-negotiable)

1. **The system NEVER initiates, launches, or places a call.** It presents lead phone numbers as `tel:` links. A human finger taps every dial. The Next Lead pull model reinforces this: a human explicitly requests each lead, then manually dials it. No auto-dial, no auto-advance to the next call, no countdown-then-dial, no predictive or power dialing of any kind.
1a. **Manual-dial states (state mini-TCPA posture, e.g. Florida FTSA):** for leads whose state is in the `manual_dial_states` setting, the served-lead screen must NOT render a `tel:` link at all. The number displays as large plain formatted text; tapping it copies it to the clipboard and records `dialed_at`. The agent keys the number into their own phone. This is a belt-and-suspenders measure: even the convenience link is removed where state law scrutinizes automated selection/dialing systems most aggressively.
2. **Calling-hours enforcement:** a lead is never served outside the legal calling window in the **lead's local timezone**. Default 08:00–21:00 lead-local, configurable per state (some states end at 20:00).
3. **DNC respect:** a lead flagged DNC is never served again. No override.
4. No random or sequential number generation anywhere. Every lead comes from an imported list or a Salesforce pull with a known source.

### 2.2 Cost

- Hosting: a **Linux VPS at Contabo** (where the company already hosts other servers). An entry Cloud VPS tier (~4 vCPU / 8GB RAM, ~$5–8/mo) is more than sufficient; choose the US data center nearest the team. The existing Laravel prize-wheel app should be migrated onto the same VPS so one server hosts both. Nothing in this spec is provider-specific — plain Nginx + PHP + MySQL, portable to any Linux VPS.
- **Mandatory: automated nightly off-server backups** — MySQL dump + uploaded files pushed to external object storage (Backblaze B2 or S3, ~$1/mo). Do not rely on provider snapshots. Test restore as part of Phase 1.
- No per-agent licensing. No third-party telephony (no Twilio). Salesforce API usage must fit the org's included API limits.

### 2.3 Design philosophy

- **KISS.** When in doubt, build the simpler version.
- **Zero agent friction.** Sign in once with Google or Microsoft — in a computer browser or as a phone PWA — hit Next Lead, work. Agents dial on their own separate cell phone.
- **Honor-system logging.** Call attempts are agent-reported. No call verification, recording, or duration tracking. Do not build any of it.
- **Manager-controlled at the list level.** Managers control which leads enter which lists and which agents work which lists. Within a list, serving is automatic per the cadence rules.

---

## 3. Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | **Laravel** (latest LTS) | Matches the existing prize-wheel app, which migrates onto the same VPS |
| Database | **MySQL** (own database, shared MySQL server with prize wheel is fine) | |
| Frontend | Laravel Blade + Alpine.js (or Livewire) | Server-rendered. No SPA framework. |
| Client | **Responsive web app / PWA** | Desktop browser is the primary agent environment (agents dial on a separate personal phone). PWA-installable on mobile for agents working from their phone. Offline support NOT required. |
| Auth | **Laravel Socialite: Google + Microsoft** | See Section 4.3 |
| Salesforce | REST API, **JWT Bearer flow** via a Connected App | Pull-only in v1. See Section 12. |
| Hosting | Contabo Cloud VPS (US data center), Nginx + PHP-FPM, HTTPS via Let's Encrypt | Nightly off-server backups mandatory (Section 2.2) |
| Timezone data | Bundled static zip→IANA timezone dataset | See Section 7 |

---

## 4. Tenancy, users, and auth

### 4.1 Multi-tenant groundwork (build the plumbing, not the product)

The company may later sell access to lead-buying partners. Build tenancy into the foundation now; build tenant-facing product later.

**Build now:**
- `tenants` table. Every domain table carries `tenant_id` (indexed, FK).
- **Laravel global scopes enforce tenant isolation on every query.** A model without its tenant scope applied must be impossible to query in app code. Write a test that proves cross-tenant leakage fails.
- All settings, calling lists, Salesforce connections, and users are per-tenant.
- Seed exactly one tenant: On Point Marketing.

**Explicitly do NOT build yet:** tenant signup, billing, tenant-admin UI, per-tenant domains/branding. When a partner signs, onboarding = inserting rows, not rewriting code.

### 4.2 Roles

Three roles (enum on users):

- **Administrator** — everything within their tenant, including user management, tenant settings, and destructive actions (list deletion, force-DNC, releasing ownership).
- **Manager** — all daily operations: lists, imports, the unassigned-bucket query tool, Salesforce pulls, dashboard, all-agent callbacks, all lead history. Not: user management or tenant settings.
- **Agent** — calling only: Next Lead on assigned lists, leads they own, My Callbacks, own scoreboard. Cannot browse the pool (anti-cherry-picking; see 9.2).

**Calling ability is granted by calling-list assignment, not by role.** Any user — including managers and administrators — assigned to a calling list receives the full agent experience (Next Lead, served-lead screen, My Callbacks, scoreboard) alongside their role's screens, and appears in serving, stats, and the leaderboard exactly like an agent. "Agent" in this spec means "any user working leads," regardless of role.

### 4.3 Auth

- Sign-in page shows **two buttons: Google and Microsoft** (Socialite providers `google` and `microsoft`/Azure). Microsoft requires a free Entra ID app registration; works for personal and work Microsoft accounts. No M365 licenses needed.
- Identity matches on **email address** against the tenant's user allowlist. Either provider works for the same user as long as the email matches.
- Unrecognized authenticated emails get a "not authorized" page.
- **Password auth exists in schema and code but is disabled by a per-tenant setting (default off).** Reserved for a future partner whose users lack both providers. While disabled: no password can be set or used, no reset flows exposed.

---

## 5. Data model

Laravel migrations. All tables get `id`, `created_at`, `updated_at`, and (except `tenants`) `tenant_id` unless noted. Types indicative.

### 5.1 `tenants`
| Column | Type |
|---|---|
| name | string |
| is_active | boolean |

### 5.2 `users`
| Column | Type | Notes |
|---|---|---|
| name | string | |
| email | string, unique per tenant | |
| google_id / microsoft_id | string, nullable | |
| password | string, nullable | Unused while password auth disabled |
| role | enum: administrator, manager, agent | |
| is_active | boolean, default true | Deactivation: can't sign in, gets no serves; their owned leads surface on the manager's Needs Attention view |

### 5.3 `calling_lists`
| Column | Type | Notes |
|---|---|---|
| name | string | e.g. "General", "Tour No-Shows" |
| is_active | boolean | Inactive lists serve nothing |
| exhaustion_cap | int | **Per-list setting.** Max total call attempts (all agents combined) before the lead is Exhausted |
| block_rotation | string | e.g. `morning,afternoon,evening` — per-list cadence order |
| min_hours_between_attempts | int, default 18 | Per list |
| skip_cooldown_minutes | int, default 120 | How long before a skipped lead can be re-served to the same agent |
| booking_form_url | string, nullable | Current Formstack booking form URL; `{op_id}` placeholder substituted per lead |

### 5.4 `calling_list_agent` (pivot)
`calling_list_id`, `user_id`. An agent may belong to multiple lists.

### 5.5 `leads`
| Column | Type | Notes |
|---|---|---|
| calling_list_id | FK calling_lists | Every lead belongs to exactly one list; manager can move leads between lists |
| op_id | string, unique per tenant, indexed | Salesforce OP_Id — dedup key |
| phone | string | Raw 10 digits, always string |
| first_name, last_name, address, city, state, zip, email | string | |
| age_range, annual_income, marital_status, gender, home_owner | string | Demographic context |
| venue, event | string | Call-opening context |
| lead_submit_date | date, nullable | |
| partner_list | string | Wyndham, HGV, etc. |
| timezone | string | IANA tz, derived on import (Section 7) |
| source | enum: csv, salesforce, migration | |
| source_file | string, nullable | CSV filename or SF pull batch ref |
| batch_date | date | |
| pool_state | enum: pooled, served, owned, completed, exhausted, dnc | See Section 8.4 |
| owned_by_user_id | FK users, nullable | Set only when a disposition confers ownership |
| served_to_user_id | FK users, nullable | Soft lock holder |
| served_at | datetime, nullable | Soft lock timestamp |
| status | enum: new, in_progress, completed | Derived, never manually set |
| disposition | enum, nullable | Latest disposition (Section 8) |
| dnc | boolean, default false | |
| callback_at | datetime, nullable | UTC |
| notes | text, nullable | Running notes |
| total_call_count | int, default 0 | Denormalized from call_attempts |

### 5.6 `call_attempts` — unified activity log (calls AND skips)
Permanent rows; never updated or deleted except the 10-minute undo (9.6).

| Column | Type | Notes |
|---|---|---|
| lead_id | FK | |
| agent_id | FK users | |
| type | enum: call, skip | **Skips are logged here** so lead history and agent skip-rates are queryable |
| disposition | enum, nullable | Required for type=call; null for skip |
| skip_reason | string, nullable | Optional free text on skip |
| notes | text, nullable | |
| attempted_at | datetime UTC | |
| lead_local_block | enum: morning, afternoon, evening | Computed at log time in the lead's tz; drives cadence. Set for calls only. |
| dialed_at | datetime, nullable | tel: tap timestamp if captured |

### 5.7 `salesforce_connections` (per tenant — the "configurable connector")
| Column | Type | Notes |
|---|---|---|
| instance_url | string | |
| client_id | string | Connected App consumer key |
| jwt_certificate | text (encrypted) | Or reference to key file |
| jwt_username | string | Integration user |
| pull_filter | text | Manager-editable criteria (Section 12.2). **Pull criteria are still being decided by the business — this field is where they land as configuration, not code.** |
| field_mapping | JSON, nullable | SF field → lead column overrides; sensible defaults hardcoded for the primary org |
| default_calling_list_id | FK, nullable | Where pulled leads land unless chosen at pull time |
| is_active | boolean | |

### 5.8 `import_batches`
`type` (csv | salesforce), `filename_or_ref`, `imported_by`, `row_count`, `imported_count`, `skipped_count`.

### 5.9 `settings` (per tenant)
| Key | Default |
|---|---|
| block_morning_start / block_morning_end | 08:00 / 12:00 |
| block_afternoon_end | 17:00 |
| block_evening_end | 21:00 (also default legal cutoff) |
| state_calling_windows | JSON per-state overrides, e.g. `{"FL":{"end":"20:00"}}` |
| serve_lock_minutes | 15 |
| manual_dial_states | JSON array of state codes where tap-to-dial is suppressed; default `["FL"]` (see 2.1 pt 1a) |
| password_auth_enabled | false |

Block boundaries are tenant-level; rotation order, min-gap, exhaustion, and skip cooldown are per-list (5.3).

---

## 6. The serving engine — core logic

All times lead-local unless stated. Implement as one pure service class (`LeadServingService`) with framework-free core logic and thorough automated tests (Section 15).

### 6.1 Next Lead: what the agent gets when they tap it (on a chosen list)

Priority order:
1. **The agent's due callbacks on this list** — owned leads with `callback_at` ≤ now (and inside the legal window). Overdue first.
2. **The agent's other owned, currently-eligible leads** on this list, if any (rare — ownership is mostly callbacks).
3. **Pool leads**, filtered by ALL of:
   - `pool_state = pooled`
   - inside the lead's legal calling window (state-aware)
   - cadence-eligible (6.2)
   - not skipped by THIS agent within the list's `skip_cooldown_minutes`
   - `total_call_count` < list's `exhaustion_cap`
   
   Ordered by: fewest attempts first is NOT required — order by `lead_submit_date` DESC (freshest first). 
4. Nothing eligible → friendly empty state: "No leads ready right now. Next lead becomes available at ~{time}." (compute the soonest upcoming eligibility if cheap to do; otherwise a static message).

### 6.2 Cadence (block rotation) — the canonical rule

> "If I call a lead Monday before noon, then Tuesday I want to call it after noon, then Wednesday after 5."

1. A lead's next attempt must land in the block **after** its last call's `lead_local_block`, following the list's `block_rotation` order, wrapping around if attempts exceed the rotation length.
2. A lead may not be served until `min_hours_between_attempts` (list setting, default 18h) after its last call — this forces next-day behavior without hardcoding days.
3. Zero-attempt leads are eligible in any block.
4. Skips do not advance the rotation, do not count toward min-gap, do not count toward exhaustion.
5. Cadence follows the **lead**, not the agent — attempt 2 can be served to a different agent on the list than attempt 1. This is the pool model's main advantage.

### 6.3 Soft lock (serve lock)

- On serve: `pool_state = served`, `served_to_user_id`, `served_at` set. The lead is invisible to all other serving for `serve_lock_minutes` (default 15).
- Released by: logging a call (→ per Section 8), skipping (→ back to `pooled` + skip cooldown for that agent), or lock expiry with no action (→ back to `pooled`, no history row).
- Two agents must never be served the same lead concurrently — take a DB-level lock (e.g. `SELECT ... FOR UPDATE` on the candidate row) during serve selection.

### 6.4 Callbacks override cadence

An owned lead with `callback_at` due ignores block rotation and min-gap entirely (still respects the legal window). Served to its **owner** via priority 1. If the owner is deactivated, it surfaces on the manager's Needs Attention view for manual reassignment or release to pool.

### 6.5 Skip

- Skip button on the served-lead screen. Optional one-line reason.
- Writes a `call_attempts` row with `type = skip`. Lead returns to pool immediately; this agent can't be re-served it until the list's skip cooldown passes.
- Skips appear in the lead's history and feed the manager's skips-per-agent metric (11.5). No effect on cadence, caps, or the lead's disposition.

---

## 7. Timezone derivation

- Bundled static zip→IANA timezone dataset (5-digit preferred, 3-digit prefix acceptable).
- Derive `leads.timezone` on import/pull from zip; fallback state→dominant-zone map; final fallback `America/New_York` + a warning counted in the import summary.
- Store all datetimes UTC; convert to lead-local only for eligibility and display.

---

## 8. Dispositions, ownership, and lead lifecycle

### 8.1 Disposition list (exact names, do not rename)

| Disposition | Class | Ownership effect | Pool effect |
|---|---|---|---|
| Booked | success | Agent owns permanently (scoreboard) | `completed` — out of pool |
| Callback | open | **Agent takes ownership** | Owned; served to owner at callback_at |
| No Answer | redialable | No ownership | Back to pool; cadence advances |
| Left VM | redialable | No ownership | Back to pool; cadence advances |
| Not Interested | terminal | — | `completed` — out |
| DNC | terminal | — | `dnc` — out forever; lead.dnc = true |
| Bad Number | terminal | — | `completed` — out, never redialed |
| Wrong Number | terminal | — | `completed` — out, never redialed |
| Not Qualified | terminal | — | `completed` — out |

Ownership is **strictly disposition-driven**. There is no "keep this lead" button.

### 8.2 Status derivation (automatic, never manually set)
Zero attempts → `new`; any attempt with non-terminal disposition → `in_progress`; terminal or Booked → `completed`.

### 8.3 Exhaustion
When `total_call_count` reaches the list's `exhaustion_cap`, `pool_state = exhausted`. Exhausted leads stop serving and appear in the manager's per-list Exhausted view, where the manager can: move to another list (resets nothing but makes it servable under that list's cap — the cap check uses the new list's setting), release back to pool with a raised cap, or mark terminal.

### 8.4 pool_state machine
`pooled` ⇄ `served` (lock) → back to `pooled` (skip/expiry) or → `owned` (Callback/Booked) or → `completed` / `dnc` (terminal) or → `exhausted` (cap). `owned` returns to `pooled` if a later call on it logs a redialable disposition (callback attempted, no answer → back to pool with cadence advanced) — unless it re-books/re-callbacks.

---

## 9. Agent experience (responsive web app — desktop AND mobile)

**Primary usage pattern: agents run the app in a browser on a computer and dial on their separate personal cell phone.** The app must be fully usable on a desktop screen (this is the layout to design first), and equally usable on a phone (PWA-installable) for agents who prefer to work entirely from mobile. Same screens, responsive layout, no feature differences except dialing affordances (9.3).

### 9.1 Home
- **Scoreboard banner** (always visible): `🎯 BOOKED: n | Callbacks: n | Calls today: n`.
- List picker (only lists they're assigned to; auto-selected if just one).
- Big **NEXT LEAD** button.
- **My Callbacks** button with a badge count; badge red if any are overdue.

### 9.2 No pool browsing
Agents cannot see or browse pooled leads — Next Lead is the only door. They CAN see a list of leads they own (their callbacks / booked history). This is deliberate anti-cherry-picking design; do not add a pool browse view for agents. The lead search in 9.2a is the sole, guarded exception.

### 9.2a Agent lead search (inbound-callback lookup)
Purpose: a lead calls an agent back ("I have a missed call from this number") and the agent must find them immediately.
- Search box on the agent home screen. Query by **phone, first name, last name, or email**.
- **Guardrails so this never becomes pool browsing:** minimum 3 characters; no blank or wildcard queries; results capped at 10; results list shows name, city/state, and status only. Every search is logged (`lead_searches`: user, query, result count, timestamp) and visible to managers.
- Opening a result shows the full lead screen. If the lead is servable/pooled, opening it places the standard serve-lock and the agent can dial and disposition normally (the person may literally be on the phone). If the lead is owned by another agent, it opens **read-only** with the owner's name shown ("Owned by Maria — transfer the caller or take a message"). DNC and terminal leads open read-only.

### 9.3 Served lead screen
- All lead context: name, city/state, venue, event, partner list, demographics, submit date, running notes, full call/skip history.
- **The phone number is the hero element**: extra-large, high-contrast, chunk-formatted `(347) 993-6772` — designed to be read off a computer screen while the agent keys it into their phone.
- **Dialing affordances by device** (detect touch/mobile via user agent + pointer media query):
  - **Desktop:** no `tel:` link ever (desktop tel: handlers open FaceTime/Skype prompts — worthless). Clicking the number copies it to the clipboard, shows a "Copied — dial on your phone" toast, and posts `dialed_at`.
  - **Mobile:** giant **CALL** button (`tel:` link; tapping posts `dialed_at`) — **unless the lead's state is in `manual_dial_states`**, in which case no `tel:` link renders on any device; mobile behaves like desktop (tap number = copy + `dialed_at`).
  - Everything else on the screen is identical across devices and modes.
- Disposition buttons (large tap targets), notes field, callback datetime picker (required iff Callback; shows the lead's local zone explicitly, validates against legal window).
- **Skip** button (smaller, secondary styling) with optional reason.
- **Open Booking Form** button — **always visible on the lead screen** (matching the spreadsheet's per-row Open Form link, and highlighted when disposition Booked is selected): opens `booking_form_url` with `{op_id}` substituted (iframe within the app if the form allows embedding — test X-Frame-Options; otherwise new tab).
- Logging without tapping CALL is allowed (`dialed_at` stays null).

### 9.4 My Callbacks screen
- All the agent's scheduled callbacks, sorted by scheduled time ascending.
- **Overdue and not yet called = highlighted red, pinned to top.** ("Not yet called" = no call_attempts row on that lead after callback_at.)
- Each row: lead name, scheduled time (agent-local, with lead-local shown if different), phone, jump into the lead screen.

### 9.5 Undo
"Undo last log" available to the logging agent for 10 minutes: deletes that call_attempts row and reverts disposition/status/ownership/pool_state/counters to prior state. Replaces the spreadsheet's (Clear) sentinel and 5-minute correction window.

### 9.6 Retired spreadsheet concepts — do not rebuild
Log Call checkbox, (Clear) sentinel, 5-minute typo window, per-agent sheet push/pull. All replaced by the per-attempt history table and Undo.

---

## 10. Lead intake

### 10.1 CSV import (kept — also the partner path for non-Salesforce tenants)
- Upload CSV in the SSIS layout: `caller_id, first_name, last_name, address, city, state, zip, email, AgeRange, annual_income, Marital Status, Gender, HomeOwner, OP_Id, jornayaleadid, trustedform, Venue, Event, original_lead_submit_date, PartnerList`.
- Manager chooses the target Calling List at upload time.
- Dedup by OP_Id within tenant; jornayaleadid/trustedform ignored (existing business decision); phone stored as digit string; timezone derived.
- Summary: imported / skipped / tz-warnings. Row in `import_batches`.

### 10.2 Salesforce pull (Section 12)
Same downstream behavior as CSV: dedup, timezone, lands pooled in a chosen Calling List.

### 10.3 Migration from the current Google Sheets system
- One-time import mode accepting the current Master_Leads 30-column CSV export.
- Manager maps rows to Calling Lists at import (simplest: everything into "General", then move). Assigned-agent and assignment-history columns are informational only — pool model has no direct assignment; leads land `pooled`. Leads with disposition Callback and a callback_at may optionally be assigned ownership by matching agent name → user (report mismatches, don't guess).
- total_call_count, status, disposition, notes, callback_at, dnc, batch date, file name carry over. Do NOT fabricate historical call_attempts rows.
- Sheets system runs in parallel until cutover; this app never touches Google Sheets.

---

## 11. Manager experience

### 11.1 Calling Lists screen
CRUD lists; per-list settings (exhaustion cap, rotation, min-gap, skip cooldown, booking form URL); assign/remove agents; per-list counts (pooled / owned / completed / exhausted / dnc); move leads between lists (bulk, filterable).

### 11.2 Leads master view
Filter/search all leads: list, pool_state, status, disposition, state, source, batch. Lead detail: full cross-agent call+skip history, ownership, everything. Manual actions: move list, force-DNC, release ownership. Salesforce link per lead: `https://onpointmrg.my.salesforce.com/{op_id}`.

### 11.3 Intake screens
CSV import (10.1), Salesforce pull (12.3), migration (10.3), import history.

### 11.4 Callbacks (all agents)
Cross-agent version of 9.4 — every scheduled callback, overdue-red, filterable by agent. Manager can reassign a callback's ownership or release to pool.

### 11.5 Dashboard
- 🎯 Booked leaderboard; calls logged today per agent; **skips per agent (today / 7 days)** — the cherry-picking detector; disposition totals and by-agent matrix; per-list pool health (pooled / eligible-now / exhausted counts); overdue callback count.
- Live on page load; no refresh button needed.

### 11.6 Needs Attention view
Exhausted leads per list; owned leads of deactivated agents; overdue callbacks older than 48h; tz-warning leads.

### 11.7 Users & Settings
User CRUD with role + active toggle; tenant settings (5.9); Salesforce connection config (12.2).

---

## 12. Salesforce integration (pull-only in v1)

### 12.1 Auth
Connected App + **JWT Bearer flow** with an integration user — server-to-server, no human login, no stored passwords. Credentials per tenant in `salesforce_connections` (the configurable-connector groundwork for partners who may later attach their own orgs).

### 12.2 Pull criteria — configuration, not code
The business is still finalizing pull criteria. Therefore: the manager edits `pull_filter` in the connection settings — a SOQL WHERE-clause fragment (advanced mode) or a simple builder (field/operator/value rows ANDed together; builder preferred, WHERE fragment acceptable for v1). The pull query selects Lead records matching the filter, mapped via `field_mapping` defaults to the lead columns in 5.5. When the criteria are decided, they get typed into this setting — zero code change.

### 12.3 Pull flow
Manager: Pull from Salesforce → choose target Calling List (default from connection) → preview count → confirm → leads imported with `source = salesforce`, dedup by OP_Id, timezone derived, `import_batches` row written. Clear error surface if Salesforce is unreachable or auth fails.

### 12.4 Explicitly NOT in v1 (future phases, in likely order)
1. Disposition sync back to Salesforce Lead records (business undecided).
2. Booking record creation in Salesforce (blocked on the business defining/rebuilding the booking object — booking forms are currently Formstack; see 9.3's booking form button for the interim).
3. Partner-org connections beyond the schema groundwork.

Design the Salesforce client class so push operations can be added without touching pull code.

---

## 13. Non-functional requirements

- Mobile-first; every agent screen comfortably one-handed on a phone.
- Server-rendered pages, <~1s on 4G. No caching layer needed at this scale.
- Destructive manager actions require explicit confirm.
- Seed/demo command: `php artisan demo:seed` — 1 tenant, 3 agents, 2 lists with different cadence settings, 60 leads across 3 timezones with varied attempt/skip/callback history, for exercising the serving engine end to end.
- Secrets (Salesforce cert, OAuth client secrets) in `.env` / encrypted columns; never in the repo.

## 14. Explicitly out of scope — do not build

Automated dialing of any kind (Section 2.1 is the compliance posture); telephony APIs / recording / verification / duration; Salesforce push (12.4); tenant signup/billing/admin UI; native mobile apps; offline mode; DNC list cross-checking on import (future); email/SMS notifications; agent pool browsing.

## 15. AI Layer — build AI-ready from day one

**Principle: AI operates the system through the same deterministic code humans do. AI never decides legality, consent, or serving eligibility — those stay hard-coded (Sections 2, 6, and consent gating). AI's role: understand intent, analyze outcomes, recommend. AI writes no data in v1; read-and-recommend only.**

### 15.1 Tool-first architecture (required from Phase 1)

Every business capability is exposed as a callable internal "tool" — a service method with typed, validated input/output DTOs, independent of HTTP/controllers. Controllers are thin wrappers over tools; future AI features are thin wrappers over the same tools. Required tool coverage (grow as features grow):

- `countLeads(FilterSpec): CountResult` — the count engine behind both the manual query builder and NL querying
- `queryLeadHistory`, `getAgentStats`, `getListHealth`, `getCallbacksOverview` — read tools for briefs/analysis
- `updateListCadence`, `moveLeadsBetweenLists` — write tools; in v1 callable only by human-initiated UI actions

Rules: every tool enforces tenant scoping and consent gating internally (never relies on the caller); every tool invocation is loggable with its caller (human user vs. future AI agent). This structure also makes a future MCP server over the app a thin adapter.

### 15.2 `FilterSpec` — one filter schema to rule them all

A single validated filter object (venue, event, state, partner-list/consent identity, date ranges, source, freshness, disposition, attempt count, etc.) used by: the manager query builder UI, the partner count/order tool, AND the NL query feature. The AI fills in a FilterSpec; it never writes SQL. Validation rejects unknown fields. FilterSpec serializes to human-readable text ("Venue contains Bass Pro; State = TX; submitted within 60 days") for display and for order records.

### 15.3 Natural-language count queries (first AI feature)

- Text box on the query screens (manager and, later, partner): user types intent → LLM (Anthropic API, Haiku-class model) receives the FilterSpec JSON schema + available filter dimensions and values → returns a FilterSpec → app validates, executes via `countLeads`, and displays BOTH the count and the human-readable filter applied, pre-loaded into the manual builder so the user can verify/tweak.
- Consent gating and tenant scoping are automatic because execution goes through the same tool as manual queries. The LLM cannot widen access — it can only fill in a filter that the deterministic layer then enforces.
- On low-confidence/ambiguous parses, show the best-guess filter unexecuted and ask the user to confirm. Never silently guess on order-placement flows.
- API key server-side in `.env`; per-tenant usage counter for future billing.

### 15.4 Data captured now to enable pattern intelligence later

`call_attempts` (5.6) is the training log. Ensure it always captures: lead_local_block, disposition, agent, attempted_at (day-of-week derivable), and the lead's venue, event, partner_list, state, and attempt number are joinable at analysis time. No schema addition needed beyond keeping this joinability intact — do not denormalize lead attributes away from history.

### 15.5 Later AI features (design targets, not v1 builds)

- **Daily manager brief:** LLM + read tools → morning summary (bookings, notable agent trends, list run-dry projections, overdue callbacks).
- **Cadence recommendations:** analyze contact/booking rates by block, day, list, venue → recommend per-list cadence changes with expected impact → **manager approves with one click; never silent auto-tuning.**
- **Pre-call lead summary** on the served-lead screen (attempt history + context in two sentences).
All follow the same pattern: read tools in, recommendation out, human approves any write.

## 16. Required automated tests

The serving engine (Section 6) is the only genuinely tricky logic — test it hard:
- Block rotation across all three blocks incl. wraparound; min-gap enforcement; legal-window edges (07:59 vs 08:00 lead-local; 21:00 cutoff; per-state 20:00 override); DST transition days; callback override of cadence and window-clamping of callbacks; serve-lock exclusivity under concurrent requests; lock expiry; skip cooldown per agent; exhaustion at exactly the cap; ownership transitions per 8.1; tenant isolation (a query without tenant scope must fail the test suite).

## 17. Build phases (each shippable)

**Phase 1 — Foundation:** Laravel app on the Contabo VPS (incl. backup pipeline + tested restore), tenancy plumbing + isolation tests, Google + Microsoft auth + allowlist, users/roles, all migrations, settings seed, PWA shell. Establish the tool-first service pattern and the `FilterSpec` object (Sections 15.1–15.2) here — everything later builds on them.

**Phase 2 — Lists & intake:** Calling Lists CRUD + per-list settings + agent assignment, CSV import with dedup + tz derivation, leads master view.

**Phase 3 — Serving core:** `LeadServingService` with the full Section 6 engine + Section 16 tests, Next Lead flow, served-lead screen, call logging, skip, undo, ownership transitions, callbacks incl. My Callbacks screen, scoreboard.

**Phase 4 — Manager visibility:** dashboard incl. skip metrics, all-agent callbacks, Needs Attention, exhausted-lead handling, lead moves.

**Phase 5 — Salesforce pull:** connection config UI, JWT auth, pull flow with configurable filter, import history.

**Phase 6 — Migration & cutover:** Master_Leads import mode, parallel run, cutover checklist.

**Phase 7 — First AI feature:** natural-language count queries per Section 15.3, on the manager query screens. (Daily brief and cadence recommendations come later, after real usage data accumulates — they are design targets, not part of this build.)

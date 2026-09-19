---
name: App review
overview: Thorough review of what is built vs used, bugs and design flaws, plus questions and suggested follow-ups. No implementation in this pass — pick workstreams from the questions below.
todos:
  - id: decide-auth
    content: Decide password invites vs Google/Microsoft OAuth (and whether unused Socialite fields stay)
    status: pending
  - id: decide-lookup-auth
    content: Decide lookup/disposition rules (holding, terminal, claim required) then lock them in DispositionService
    status: pending
  - id: decide-roles
    content: Decide Admin vs Manager Filament permissions (today they are the same)
    status: pending
  - id: decide-cleanup
    content: Decide whether to delete dead widgets, scaffold files, tmp scripts, and unused lookup remnants
    status: pending
  - id: decide-ops
    content: Decide Docker scheduler, digest timing, and deactivation side effects
    status: pending
isProject: false
---

# App review — unused work, bugs, and design questions

This is a **review and decision plan**, not a build plan. Core calling (import → holding → release → get-next → lease → disposition → cadence/compliance) is in good shape and well tested. The gaps below are leftovers, authorization holes, and product decisions that the code currently guesses at.

Architecture source of truth is still [plans/call-center-architecture.md](call-center-architecture.md). Several later plans (`configurable-dispositions`, `cadence-standalone`) are **already implemented** but still marked pending.

---

## What is solid

Do not rip this up. It matches locked owner decisions.

- Callbacks first, then shared pool; `FOR UPDATE SKIP LOCKED`; 20-minute lease
- Skip → bottom of list; skip does not increment attempts
- No undo
- Import → holding → release with lead-type ↔ list guard
- DNC / RND / Soft Score / Qualification import pipeline
- Configurable dispositions and standalone cadences (beyond original architecture)
- Agent workspace, scoreboard, lookup, booking URL builder
- Settings history on config models
- Daily digest command, nightly backup command (see ops questions)

---

## 1. Built but not actually used

Safe to delete or hide unless you still want them.

| Item | Where | Why it looks unused |
|------|--------|---------------------|
| **LiveDashboardStats widget** | `app/Filament/Widgets/LiveDashboardStats.php` | `$isDiscovered = false`; never mounted on the dashboard. Live bookings/calls/skips/orphaned-callbacks cards exist but nobody sees them. |
| **CallingListDispositionStats widget** | `app/Filament/Resources/CallingLists/Widgets/CallingListDispositionStats.php` | Same: discovery off. Calling-list view already inlines the same counts in Blade. |
| **Read-only lookup partial** | `resources/views/livewire/agent/partials/lead-readonly.blade.php` | Workspace never includes it. Design handoff still mentions it. |
| **Dead lookup state** | `Workspace` `$lookupLeadId`, `$lookupReadOnly`, `getLookupLeadProperty()` | Always reset; Blade never reads them. Lookup loads the main panel instead. |
| **Laravel welcome page** | `resources/views/welcome.blade.php` | `/` redirects to agent login. |
| **Settings History create/edit scaffold** | `SettingsHistories/Pages/Create*`, `Edit*`, empty `SettingsHistoryForm.php` | Resource is read-only (`canCreate`/`canEdit` false); those pages are not registered. |
| **Socialite + OAuth columns** | `composer.json` `laravel/socialite`; `users.google_id` / `microsoft_id`; User form fields | No OAuth routes or login UI. Auth is email + temp password. README still says Socialite. |
| **`inspire` command** | `routes/console.php` | Laravel default. |
| **Empty `app.js`** | `resources/js/app.js` | Vite builds CSS only. Harmless. |
| **`debug-b697ad.log`** | repo root | Leftover debug session file. |
| **`scripts/tmp-*`** | ~20 one-off prod verify/deploy scripts | Not app runtime. Operational leftovers. |
| **PHPUnit ExampleTest** | `tests/Unit/ExampleTest.php` | `assertTrue(true)`. |

**Used, but easy to confuse with unused**

- **Import mappings** — used on every CSV import.
- **Lead types catalog** vs `calling_lists.lead_type` slug — catalog is labels/CRUD; slug is the runtime key. Intentional.
- **Disposition reasons** vs **dispositions** — parent outcome vs sub-reason. Intentional.
- **Two DNC paths** — scrub on import vs push on agent DNC. Different stages.
- **`leads:migrate-slim` vs `leads:migrate-leadmaster`** — two one-time migration tools; LeadMaster is the full one.
- **Lead Claims Filament page** — read-only lease list. Useful for “who is sitting on a lead?”; unused if managers never open it.
- **Companies CRUD** — works, but architecture said v1 is one company and no client onboarding UI.

**Half-migrated (in use, but duplicated)**

`App\Enums\Disposition` still drives Assign Leads last-disposition options, Lead History filters/labels, digest/dashboard booked counts, DNC history lookup, and LeadMaster mapping. Agent buttons and `DispositionService` already use `DispositionDefinition`. Custom dispositions you create in admin will **not** show up in those leftover enum lists.

---

## 2. Bugs and real holes

### P0 — Agent can disposition almost any same-company lead

`DispositionService::apply()` does **not** check:

- the user holds an active claim on that lead
- the user is assigned to the lead’s calling list
- the lead is in a workable status (`callable` / owned `callback`)

`Workspace::$leadId` is a public Livewire property. Combined with lookup, an agent can open (and disposition) leads they were never handed by get-next.

`selectLookupLead()` only special-cases **DNC** as read-only. It does **not** use `LeadLookupService::isReadOnly()` / `canWorkImmediately()`, which already know booked/terminal/other-agent-callback/holding rules. Tests currently **encode** holding as workable (`test_lookup_opens_holding_lead`). That is a product decision that leaked into tests, not proof it is right.

`claimForLookup()` has no row lock. Two agents looking up the same number at once can race; the unique index on `lead_id` then throws.

**Suggested default (if you agree):** lookup may *find* any lead; only callable / owned-due-callback inside legal hours become editable; holding, booked, terminal, DNC, and other-agent leases are read-only; `DispositionService` refuses apply without a matching claim.

### P0 — Holding bypasses the release pipeline

Architecture: nothing is callable until a manager releases it. Lookup currently claims holding leads and the UI treats them as workable, so an agent can Booked/DNC/Skip a lead that never went through DNC/RND/qualification gates or list assignment.

**Question:** when a holding lead calls an agent back, should they work it immediately (current) or only view + ask a manager to release (architecture)?

### P1 — User deactivation is login-only

Toggling `active` off:

- blocks the next login
- does **not** kill sessions
- does **not** expire claims
- leaves callbacks on that owner until someone uses Callbacks Board

Architecture: kill sessions, release leases, keep callbacks on the manager board for reassignment.

### P1 — Docker never runs the scheduler

`claims:expire`, `dashboard:email-digest`, and `db:backup` are scheduled in `bootstrap/app.php`. Compose has `app` + `queue` + `db` + `caddy` — **no** `schedule:run` sidecar and no cron. Without host cron, leases do not auto-expire, digest never sends, backups never run.

Digest also requires the clock’s `H:i` to **exactly** match send time. If that minute is missed, that day is skipped.

### P1 — DNC is not a hard invariant in admin

Agent path cannot recycle DNC. Filament lead form lets anyone with panel access set `status` to anything, including off DNC. Architecture: DNC is permanent, not a toggle.

### P1 — Merge duplicates is thin and untested

`LeadMergeService` moves history, writes a merge row, deletes the duplicate. It does not:

- merge/choose phone, name, Salesforce ids, extra fields
- drop or reassign the duplicate’s claim
- handle unique `(company_id, phone)` collisions
- pick a smarter survivor than “first selected”

No tests.

### P1 — Agent Dashboard ignores managers who call

Requirements: calling ability comes from **list assignment**, not role; managers who call appear on scoreboards. The Filament home page is labeled **Agent Dashboard** but is a manager report, and the Rep filter / agent table only includes `role = agent`. A manager assigned to lists is invisible there.

### P2 — Timezone is state-only

`TimezoneResolver` accepts `$zip` and `$city` and ignores them. Split-timezone states (FL panhandle vs peninsula, TX, IN, KY, TN, ID, OR, ND) can get the wrong legal window. Architecture asked ZIP-first.

### P2 — Multiple live claims per user

`lead_claims` is unique on `lead_id` only. Opening a callback or lookup while a pool lead is leased creates a second claim. `activeClaimForUser()` uses unordered `first()`, so get-next resume is nondeterministic. `putBackCallback` already has special-case code for “remaining other claim.”

### P2 — Import dedupe ignores `phone_2`

Dedupe is `external_lead_id` or primary `phone`. A new primary that matches someone else’s `phone_2` can insert a duplicate contact.

### P2 — Agent can edit phone with no DNC re-scrub

Workspace editable fields include `phone`. Soft Score / qualification may re-queue; DNC/RND do not.

### P2 — Filament nav clutter / sort collisions

Lead History and Callbacks Board both sort `2` in Leads. Lead types and Cadences both sort `3` in Configuration. Managers and admins see the same giant nav (Companies, Lead Claims, Settings History, OAuth id fields, etc.).

---

## 3. Design flaws (not necessarily bugs)

**Two logins.** Agent is `/agent/login` (agent guard); admin is `/admin/login` (web guard). Same user can be in both at once. That is why managers can call without losing Filament. It is also why people get “wrong login page” confusion, and why middleware must remember which guard is in play (`EnsureCanCall` already does; `SetCompanyContext` / `EnsurePasswordChanged` use default `$request->user()`).

**Admin and manager are the same in Filament.** There are **no policies**. `canAccessPanel` is admin **or** manager. A manager can edit companies, users, state windows, dispositions, app settings, booking URLs. Architecture reserved settings / user management / destructive actions for admin.

**Lead form is a raw record editor.** Status, attempt count, callback owner, timezone, calling list — all freely editable. Easy to desync cadence, claims, and DNC.

**Compliance is configuration, not a federal floor.** Missing state + missing DEFAULT rule ⇒ `canDialNow()` is always false (fail closed — good). There is no hardcoded 8am–9pm behind the admin UI; a bad State Rule edit can widen the window.

**Skip vs cadence.** Skip sets `last_attempt_at` and advances day-part but does not increment `attempt_count`. Timing is consumed; attempt budget is not. That may be what you want (skip = “not now”) or it may strand leads in later day-parts.

**Pool candidate cap of 250.** Get-next loads 250 ranked rows, then filters hours/cadence in PHP. If the top 250 are all outside the window, deeper ready leads starve. Cadence plan already called this out (used to be 50).

**Callback time is company TZ, legality is lead TZ.** Tested and intentional; easy for an agent to mis-schedule a west-coast lead.

**Companies UI vs “v1 one tenant.”** Schema is multi-tenant-ready. `CompanyScope` does nothing when context is unset (console/jobs must remember `company_id`). Building Companies CRUD now is a footgun if you are not actually selling this yet.

**Stale docs.** README still says Socialite. Architecture YAML todos are all `pending`. `allowed_emails` is in the architecture data model but was dropped.

---

## 4. Questions (please answer these)

These are the forks that should drive the next work, not cleanup for its own sake.

### Auth and access

1. **Stay on emailed temp passwords**, or still want **Google + Microsoft** sign-in (no passwords) as originally planned?
2. If passwords stay: remove Socialite, `google_id` / `microsoft_id` from the User form, and fix README — or keep the columns for a later SSO pass?
3. **Admin vs manager:** should managers lose Companies, Users (invite/deactivate), State Rules, App Settings, Dispositions, Cadences, Lead Types? What must a manager still do day-to-day (import, assign, recycle, dashboards, callbacks)?
4. Should a **manager/admin who is assigned to lists** show on Agent Dashboard / leaderboard / digest like an agent?

### Lookup and “no cherry-pick”

5. Lookup of a **holding** lead: **read-only** (architecture) or **work it now** because they are on the phone (current tests)?
6. Lookup of **booked / terminal / other-agent callback**: confirm **read-only banner** (service already thinks this; workspace does not fully apply it except DNC)?
7. May an agent work a callable lead that is **not on any of their lists** if they looked it up by phone? (Current: yes.)
8. Opening lookup/callback while already holding a lead: **replace the claim** (put the old lead back) or **allow two claims**?

### Compliance invariants

9. Can a manager **ever** take a lead off DNC, or must DNC be irreversible even in Filament (recycle already blocks it)?
10. When you deactivate a user, auto-expire their claims **now**? Auto-open Callbacks Board / notify? Or keep today’s “login blocked, leftovers sit until someone notices”?
11. Is **ZIP-accurate timezone** worth a zip-to-TZ table, or is state centroid good enough for the states you actually dial?

### Ops

12. In Docker/prod, should we add a **`scheduler` Compose service** (`schedule:run` every minute), or do you already have host cron on the VPS?
13. Digest: keep “exact minute” send, or send if `now >= send_time` and not yet sent today (needs a send-log row)?
14. Are nightly B2 backups actually configured in prod, or is `db:backup` local-only right now?

### Cleanup vs product

15. **LiveDashboardStats** (live bookings/calls/skips/orphans): wire it onto the manager home page, or delete it?
16. **Lead Claims** page: keep for ops, or hide from nav (still queryable)?
17. **Companies** page: hide until you actually add a second tenant?
18. Delete `scripts/tmp-*` and `debug-b697ad.log` from git, or leave them as a private runbook?

### Cadence / skip (only if you care)

19. Should Skip **also** wait the attempt-gap, or only bump queue rank (current)?
20. Agent **editing** name/phone/address on the workspace: keep it? If they change phone, re-run DNC/RND?

---

## 5. Suggested follow-ups (after you answer)

Pick one stream; do not boil the ocean.

### A. Lock the calling contract (highest leverage)

- Authorize `DispositionService` (claim + workable status; optional list assignment)
- Make lookup use `isReadOnly` / `canWorkImmediately` consistently
- Single active claim per user
- Deactivation: expire claims + session
- Tests for IDOR, holding, leased-by-other

### B. Role and nav hygiene

- Filament policies: manager = daily ops, admin = settings/users/compliance config
- Rename **Agent Dashboard** → **Manager Dashboard**
- Include list-assigned non-agents in stats if you said yes
- Hide or drop unused nav (Companies, Lead Claims, OAuth fields) per answers

### C. Dead-code cleanup (small, safe)

- Remove unused widgets, `lead-readonly`, lookup remnants, welcome view, Settings History scaffold, `inspire`, debug log
- Gitignore `debug-*.log` and optionally `scripts/tmp-*`
- Finish disposition enum → `DispositionDefinition` on history filters / Assign Leads / digest booked counts so custom dispositions appear everywhere

### D. Ops hardening

- Compose `scheduler` service (or document VPS cron in README)
- Digest “already sent today” guard
- Confirm backup disk / restore drill

### E. Docs catch-up (cheap)

- Mark architecture + cadence + dispositions plan todos to match reality
- README: password auth, not Socialite; scheduler requirement
- Drop `allowed_emails` from the architecture data model

---

## 6. Out of scope unless you ask

- Building OAuth
- ZIP geo database
- Hardening merge into a full CRM merge UI
- Selling/renting multi-tenant (billing, super-admin, isolation test suite)
- Rewriting the agent workspace (redesign prompt already exists)

---

## Files that matter for the P0/P1 items

- `app/Services/Leads/DispositionService.php`
- `app/Services/Leads/LeadLookupService.php`
- `app/Services/Leads/LeadClaimService.php`
- `app/Livewire/Agent/Workspace.php` (`selectLookupLead`, public `$leadId`)
- `app/Filament/Resources/Leads/Schemas/LeadForm.php` (status editor)
- `app/Filament/Resources/Users/Schemas/UserForm.php` (`active` with no side effects)
- `docker-compose.yml` + `bootstrap/app.php` (no scheduler process)
- `app/Support/TimezoneResolver.php`
- `app/Services/Leads/LeadMergeService.php`
- `app/Filament/Pages/Dashboard.php` (label + agent-only filter)

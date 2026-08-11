# Call Center Application — Requirements

**Purpose of this document:** complete functional and non-functional requirements for a custom call center application. You are asked to propose the system architecture, data model, and technology stack you believe best satisfies these requirements. No stack or architecture is prescribed here — propose and justify your own.

---

## 1. Business context

- The company runs timeshare-tour telemarketing. Leads are consumers who attended marketing events (boat shows, fairs, venue promotions) and opted in. The goal of every call is to book the lead on a timeshare tour for one of several resort-client partners.
- Small team: an owner/administrator, 1–2 managers, and up to ~5 calling agents within the next 12 months. Managers sometimes make calls themselves.
- Agents are remote/distributed and call **from their own personal cell phones**. There is no company phone system, no dialer hardware or software, and there never will be (see compliance).
- The current system is a Google Sheets + Apps Script workflow (manager pushes lead lists to per-agent sheets, pulls results back). It works but has hit its limits: manual push/pull, fixed per-agent lists that leave some agents idle while leads sit untouched with others, and no control over what time of day leads are called.
- The application will be built by the owner personally using AI coding assistants. The owner is technical enough to deploy and operate a web application but is not a professional software engineer. Simplicity of implementation and operation is a first-class requirement (KISS). Prefer boring, well-documented technology over novel approaches.
- Leads come from multiple U.S. states and time zones.

## 2. Users, roles, and authentication

- Three roles: **Administrator** (everything, including user management, settings, destructive actions), **Manager** (all daily operations), **Agent** (calling work only).
- **Calling ability must come from work assignment, not from role.** Any user — including a manager or the administrator — who is assigned to calling work gets the full agent calling experience (and appears in stats/leaderboards) in addition to their role's other capabilities.
- Sign-in with **Google accounts and Microsoft accounts** (agents have a mix of both). No passwords to manage. Access restricted to an approved list of email addresses controlled by the administrator.
- Deactivating a user must immediately stop their access and return any in-progress work to the available pool.

## 3. Compliance requirements (hard constraints — highest priority)

These are non-negotiable and must be enforced by the system deterministically (never by configuration a user could casually flip off, never by an AI/heuristic component, never left to agent judgment):

1. **The system must never initiate, place, or launch a phone call, and must not contain any telephony capability at all.** Agents dial manually on their own personal phones. This is a deliberate legal posture under the TCPA (federal autodialer rules): a human must initiate every call, and the software must be incapable of dialing.
2. **Legal calling hours:** the system must never present a lead for calling outside 8:00am–9:00pm **in the lead's own local time zone** (derived from lead address/state/zip). Individual states may have stricter windows; the allowed window must be configurable per state.
3. **State-specific manual-dial mode:** for a configurable list of states (Florida will be in it — the company dials Florida heavily and Florida's mini-TCPA is aggressive), the application must not offer *any* click/tap-to-dial convenience for those leads — not even a `tel:` hyperlink. The number is displayed for the agent to hand-dial. In all other states a tap-to-dial link is desirable on mobile devices.
4. **Do Not Call is permanent:** once a lead is marked DNC, the system must never present that lead for calling again. No override by any role.
5. **Attempt limits:** a configurable maximum number of call attempts per lead (currently 3). Once exhausted, the lead must never be presented for calling again unless a manager explicitly recycles it.
6. **Auditability:** every call attempt, disposition, assignment change, and lead search must be recorded with who/when, retained for the life of the lead.

## 4. Lead data and intake

- Leads currently arrive as CSV exports with these fields: phone (10-digit), first name, last name, address, city, state, zip, email, age range, annual income, marital status, gender, homeowner status, venue, event, original lead submit date, partner list (which resort clients this lead may be offered to — e.g. a lead may be eligible for some partners and not others), a unique lead ID from the company's CRM, and consent-verification tokens (Jornaya / TrustedForm) which must be preserved.
- Import must deduplicate on the unique lead ID.
- Imported leads land in an **unassigned holding area** — they must not become callable until a manager deliberately releases them to calling work.
- Managers need a **query tool** over the unassigned area: filter by state, venue, event, source file, import batch/date, zip, and partner eligibility; see the matching count; then assign either all matches or the N freshest matches to a chosen body of calling work.
- Anticipate a one-time migration of a few thousand existing leads (with call history: attempts so far, dispositions, notes, callbacks) from the spreadsheet system.

## 5. Organizing and controlling calling work

- Managers organize callable leads into named groupings ("calling lists" conceptually) and control which users work each grouping. Example: everyone works "General"; only two senior agents work "Tour No-Shows".
- **Every movement of leads is a deliberate manager action.** Nothing becomes callable, moves between groupings, or gets recycled automatically.
- **Agents must not be able to choose which lead to call next, browse available leads, or cherry-pick.** The system selects each next lead for them. (Sole exception: the lookup in §8.)
- Two agents must never be working the same lead at the same time.
- No agent should sit idle while callable leads exist in a grouping they're assigned to; leads must not be stranded with an unavailable agent.

## 6. Calling cadence requirements

- Repeated attempts on the same lead must land in **different parts of the day on different days**. The company divides the day into Morning (before noon), Afternoon (noon–5pm), and Evening (after 5pm), in the *lead's* local time. Example intent: attempt 1 in the morning; attempt 2 the next day in the afternoon; attempt 3 the following day in the evening — so a lead who never answers at 10am isn't called at 10am three times.
- A minimum time gap between attempts on the same lead (order of ~18 hours) must be enforced.
- Cadence rhythm/settings should be configurable per grouping of calling work by a manager.
- Scheduled callbacks (§7) override cadence: they happen when promised.

## 7. Dispositions, outcomes, and callbacks

After every call the agent records exactly one outcome. Required outcomes and their consequences:

| Outcome | Consequence |
|---|---|
| **Booked** (the success goal) | Lead is done; counts on the agent's scoreboard/leaderboard permanently. |
| **Callback** (lead asked to be called at a specific date/time) | The lead now belongs to *that agent*; the system must present it back to that same agent at the scheduled time. |
| **No Answer / Left Voicemail** | Lead returns to shared availability for a future attempt per cadence rules; any eligible agent may make the next attempt. |
| **Not Interested / Wrong Number / Bad Number** | Terminal; never called again. |
| **DNC** | Terminal and permanent per §3.4. |

- Agents also need: free-text notes per attempt, a **Skip** action (must supply a reason; skips are logged and reported per agent; skipping must not count as an attempt), and a short **undo window** (~10 minutes) to correct a mis-logged outcome.
- Each agent needs a personal callbacks view; overdue callbacks must be visually unmissable. Managers need the same view across all agents.

## 8. Lead lookup (guarded exception to no-browsing)

- Agents need to find a specific lead when someone calls them back ("I have a missed call from this number"): search by phone, first name, last name, or email.
- Must be designed so it cannot be used to browse or cherry-pick: require a minimum-length query, no wildcard/blank searches, cap the results, log every search for manager review.
- A found lead that is available may be worked immediately (the person is on the phone right now). A lead owned by another agent or in a terminal state opens read-only.

## 9. Booking handoff

- Booking data entry happens in an **existing external web form** (hosted form service). The application must show, on every lead's screen, a link/button that opens that form with the lead's unique ID substituted into a configurable URL template. (This mirrors the current spreadsheet's per-row "Open Form" link.)
- Creating bookings inside the application itself is explicitly out of scope for v1.

## 10. Agent working environment

- **Primary setup: the agent runs the application in a web browser on a computer, with their cell phone beside them for dialing.** The lead's phone number must be displayed prominently (large, easy to read across to a phone while keying it in). On desktop, clicking the number should copy it (never trigger OS calling apps like FaceTime/Skype).
- The application must also be fully usable on a phone's browser for agents who work entirely from mobile.
- On the lead screen the agent must see: name, city/state, the lead's current local time, the source venue/event and lead freshness (their call opener), **which partner clients this lead is eligible for** (so no one pitches a tour the lead can't be offered), the demographic profile (age range, income, marital status, homeowner), full prior attempt history with notes, outcome entry, notes, callback scheduling, skip, and the booking-form link.
- Requesting work must be one action ("give me the next lead" or equivalent). Zero training burden: an agent should be productive after five minutes of orientation.
- A personal scoreboard (bookings, callbacks pending, work remaining) always visible to the agent; a bookings leaderboard across all callers.

## 11. Manager visibility

- Live dashboard: bookings, calls made, outcomes — updating as agents log them, without any manual sync step.
- Full per-lead history: every attempt, by whom, when, outcome, notes, assignment changes.
- Per-agent activity: calls per day, outcomes breakdown, skip rates, search activity.
- All-agent callbacks board with overdue flagged.

## 12. Non-functional requirements

- **Cost ceiling: total recurring cost as close to $0 as possible; hard ceiling $100/month; target ≈$10–20/month.** No per-seat licensing of any kind for agents.
- Scale honestly targeted: ≤10 concurrent users, tens of thousands of leads, a few thousand call attempts per week. Do not architect for scale beyond ~10× this.
- The company self-hosts on inexpensive infrastructure it already operates; assume a single small server is available. Recommend what you see fit within the cost ceiling.
- **Automatic nightly backups stored off the application server, with a documented and tested restore procedure.** Losing lead/call history is a business-ending event.
- Business hours availability matters (agents can't work when it's down); five-nines does not. Simple recovery beats complex high availability.
- Maintainable by one non-professional developer with AI assistance: favor a single deployable application, minimal moving parts, mainstream well-documented technology.
- The data model should anticipate (without building): future direct CRM integration for lead intake (currently Salesforce; CSV remains the v1 intake), and a future where **partner companies run their own separated call floors on the same application** (full data isolation per company). Do not build partner-facing features now, but do not paint the data model into a corner.

## 13. Explicitly out of scope for v1

- Any telephony, dialing, call recording, or call verification (permanently out, per §3.1)
- Direct CRM/Salesforce integration (future)
- Partner-facing features, billing, lead marketplace (future)
- AI features of any kind (future; and per §3, AI must never make compliance decisions even then)
- Native mobile apps (browser only)
- In-app booking forms (§9)
- Password-based login

---

**Deliverable requested from you:** propose the architecture, data model, key workflows/algorithms (especially: how you satisfy §5 + §6 + §7 simultaneously), and technology stack, with reasoning. Flag any requirements you believe are in tension with each other.

# Handoff: OnPoint Call — Agent Workspace Redesign

## Overview
Redesign of the agent-facing call workspace: dial, talk, disposition, pull next lead. Optimized for speed/clarity on desktop, with mobile fallback. Locks the phone number as the hero element, groups lead fields into scannable sections, and puts disposition actions in a sticky right-side panel above secondary tabs (scoreboard/leaderboard/callbacks/lookup).

## About the design files
The HTML file in this bundle (`Agent Workspace Redesign (standalone).html`) is a **design reference/prototype**, not production code — it's a static mock with fake demo data and inline styles, built to show layout, states, and interaction flow. **Do not embed or iframe this HTML in the app.** The task is to recreate this design inside the existing Laravel + Livewire + Alpine + Tailwind stack, using the Blade partials already drafted in `blade/` as the starting point — adjust them to match your actual `Lead`, `Call`, and Livewire component code (property names, relationships, enums) rather than the placeholder names used here.

## Fidelity
High-fidelity: exact colors, spacing, and copy are shown. Typography is Instrument Sans (Google Fonts) at the sizes/weights in the CSS below — swap for the app's existing font if it already has one.

## Files included
- `Agent Workspace Redesign (standalone).html` — open in any browser to see/interact with the full prototype (light + dark mode, 4 lead-state scenarios via the "Preview state" switcher at the top — that switcher itself is prototype-only, not part of the build).
- `blade/layouts/agent.blade.php` — page shell, header, light/dark toggle.
- `blade/livewire/agent/workspace.blade.php` — main workspace: lead panel (left) + disposition/tabs (right).
- `blade/livewire/agent/partials/lead-panel.blade.php` — active lead card: header, edit mode, field sections, call history.
- `blade/livewire/agent/partials/phone-display.blade.php` — hero phone block.
- `blade/livewire/agent/partials/lead-readonly.blade.php` — compact read-only card (used in sidebar Lookup tab).
- `blade/resources/app.css` — supporting styles (mostly Tailwind; check for any custom rules).
- `blade/NOTES.md` — design rationale for section grouping and disposition layout.

## Screens / views
Single screen, two-column layout (`grid-cols-1 md:grid-cols-3` — stacks to one column below `md`):

**Left/main column — Active Lead card**
- Header row: "Active Lead" label + lead ID/attempt count, Edit/Cancel/Save buttons, "Open Booking Form" link (hidden when read-only).
- Status banner (booked/DNC/callback-overdue) shown as a colored bar under the header when applicable.
- "Call dispositioned" bar with "Get Next Lead" button, shown once a disposition is applied (locks the card read-only).
- Hero: phone number (clamp(34px, 8vw, 56px), extrabold), click-to-copy, name below it, manual-dial-only amber badge, secondary contact line.
- Field sections, each with a `12px uppercase bold` label and `auto-fit minmax(150px,1fr)` grid below it: Contact (email, local time, last call) → Address (city/state/zip, address) → Opportunity context (venue/event, partners, source file, lead ID) → Demographics/profile (age range, income, marital status, gender, homeowner, soft score — only renders if any field is populated) → Tour/TNB (only renders if populated) → Extra fields (collapsed behind a disclosure toggle, 3-col grid when open).
- Run Soft Score button (hidden when read-only).
- Call History table below the card: div-based grid rows (not `<table>`, to dodge Blade/HTML foster-parenting issues) — Date/Time, Outcome (colored pill), Agent, Duration, Note.

**Right column — sticky, disposition above tabs**
- Disposition card (hidden entirely when lead is booked/terminal/dnc): Booked (large, solid emerald) → Callback/No Answer/Left VM/Not Available (2×2 grid) → callback reveals an inline datetime picker + Continue button when selected → divider → Not Interested/Not Qualified/Wrong Number/Bad Number (2×2, red) → DNC (full width, red) → dashed divider → Skip reason input + Skip button.
- Every disposition except Skip opens an "Add a note" modal (optional textarea) before committing — modal shows the chosen label, Cancel / "Save & Disposition" buttons.
- Secondary panel: tab bar (Scoreboard/Leaderboard/Callbacks/Lookup), one panel visible at a time. Scoreboard = 2×2 stat grid. Leaderboard = ranked list. Callbacks = list with red "Overdue" flag for past-due. Lookup = search input + results list + read-only record preview on select.

## Interactions & behavior
- Click phone number → copies to clipboard, shows "Copied!" for 2s.
- Edit button toggles inline editing on Email, City/State/Zip, Address, and the 5 demographics fields (dropdowns for those 5); everything else stays read-only text. Cancel discards, Save persists.
- Extra fields and (optionally) call history are collapsed by default behind a disclosure arrow.
- Selecting any disposition (except Skip) opens the note modal; confirming applies the disposition, marks the lead read-only, and shows the "Get Next Lead" bar.
- Skip requires a non-empty reason; shows inline "Reason required" error if empty.
- Callback picker appears inline under the Callback button once selected; "Continue" opens the same note modal.
- Light/dark toggle in the header persists to `localStorage` and toggles a `dark` class on `<html>`; every color in the design is theme-aware (see tokens below).
- Responsive: `grid-cols-1 md:grid-cols-3` — below `md` the right column drops beneath the lead card. Global `box-sizing: border-box` prevents horizontal overflow.

## State management (Livewire — expected on the parent component)
- `$lead` — current active lead (null when queue empty).
- `$editMode` (bool) + `$editable` (array snapshot of editable fields) + methods `startEdit()`, `cancelEdit()`, `saveLeadEdits()`.
- `$dispositionNote` (new property) — bound to the note-modal textarea, passed along with `applyDisposition($key)`.
- `$callbackAt`, `$skipReason` — existing per NOTES.md.
- `$lookupQuery`, `$lookupResults`, `$lookupLead` / `$lookupReadOnly` — Lookup tab.
- Alpine (client-only, no Livewire round-trip) drives: edit-mode toggle UI, extra-fields disclosure, callback-picker reveal, note-modal open/close, sidebar tab switching, disposition button "selected" highlight before commit.

## Design tokens

**Colors (light / dark):**
- Page bg: `#f1f5f9` / `#0b1220`
- Card bg: `#ffffff` / `#1e293b`
- Border: `#e2e8f0` / `#334155`, border-light: `#f1f5f9` / `#2d3b4e`
- Text primary: `#0f172a` / `#f1f5f9`, secondary: `#334155` / `#cbd5e1`, muted: `#64748b` / `#94a3b8`, faint: `#94a3b8` / `#64748b`
- Brand blue: `#2563eb` (links, primary buttons, active tab)
- Success/booked: `#059669` (idle bg `#ecfdf5`/border `#a7f3d0`/text `#065f46` light; dark equivalents use `rgba(16,185,129,*)`)
- Danger/negative dispositions: `#dc2626` (idle bg `#fff5f5`/border `#fecaca`/text `#b91c1c` light; dark `rgba(220,38,38,*)`)
- Warning/manual-dial/callback-overdue: `#92400e` on `#fef3c7` / `#fffbeb`

**Typography:** Instrument Sans, weights 400/500/600/700/800. Phone number `clamp(34px,8vw,56px)` weight 800. Section labels `12px` weight 700 uppercase, `0.05em` letter-spacing. Body text `13–14px`. Stat numbers `22px` weight 800.

**Radius:** cards `12px`, buttons/inputs `6–8px`, pills `999px`.

**Shadows:** disposition card only — `0 2px 12px rgba(15,23,42,0.06)`.

## Assets
No images/icons beyond inline SVGs (sun/moon theme toggle icons, "OP" logo mark placeholder — replace with the real OnPoint Call logo).

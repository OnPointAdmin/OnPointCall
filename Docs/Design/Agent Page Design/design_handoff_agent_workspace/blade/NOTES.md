# Agent Workspace Redesign — Section Grouping & Disposition Notes

## Section grouping

Fields are split into five scannable groups instead of one flat grid, matching how an agent actually reads a lead during a call:

1. **Call target (hero):** phone number is the largest, most prominent element on the page — the one thing an agent needs before dialing. Name sits directly under it. Manual-dial leads get an amber badge in the same block so it's seen before dialing, not buried in a field list.
2. **Where / when:** location + local time — needed for small talk and timezone awareness, secondary to the phone.
3. **Opportunity context:** venue, event, partners, source file, lead ID — the "why this lead exists" facts, grouped together since they're usually referenced together during the pitch.
4. **Demographics / profile** and **Tour / TNB:** each section only renders if at least one of its fields is populated (`filled()` check), so leads without that data don't show empty rows.
5. **Extra fields:** whatever dynamic fields remain are collapsed behind a disclosure toggle — available on demand, not competing for attention by default.

All PII besides the phone number stays `select-none` with `oncopy="return false"` to keep copy-friction, per the existing pattern.

## Disposition layout

The disposition zone is pinned to the bottom of the active-lead card (`sticky bottom-3`) so it's always reachable without scrolling past history or context — the fastest path from "call ended" to "next lead."

Buttons are grouped and weighted to match how quickly an agent should choose them:
- **Booked** stands alone, largest and boldest (solid emerald) — the outcome everything else exists to support.
- **Callback / No Answer / Voicemail** sit together as the "try again later" group. Callback reveals its datetime picker inline, directly under the button that triggered it, instead of a separate always-visible field.
- **Not Interested / Wrong Number / Bad Lead / DNC** are visually separated (a vertical divider) and styled in red as terminal negative outcomes — distinct at a glance from the "continue" group.
- **Skip** sits below a dashed divider with its required reason field next to it, so it reads as a queue-management action rather than a disposition outcome.

Booked / terminal / DNC leads render a colored banner at the top of the card and hide the disposition zone entirely (`@unless ($isReadOnly)`), so a closed lead can't be accidentally re-dispositioned.

## Secondary panels

Scoreboard, leaderboard, callbacks, and lookup moved into a single tabbed sidebar card (chosen layout: right sidebar) so only one secondary view is visible at a time — none of them compete visually with the active lead, which now occupies the full main column.

## Files

- `blade/layouts/agent.blade.php`
- `blade/livewire/agent/workspace.blade.php`
- `blade/livewire/agent/partials/lead-panel.blade.php`
- `blade/livewire/agent/partials/phone-display.blade.php`
- `blade/livewire/agent/partials/lead-readonly.blade.php`
- `blade/resources/app.css`

Copy these into their matching `resources/views/...` and `resources/css/app.css` paths. All existing `wire:click` / `wire:model` / `wire:poll` bindings and enum/method names from the original files were preserved; only markup, classes, and structure changed. The lead-panel adds light Alpine.js (`x-data`/`x-show`) for the extra-fields disclosure, history disclosure, and callback-picker reveal — Alpine ships with the Livewire/Alpine stack already if not already in use; the sidebar tabs and skip/disposition selection state are pure Alpine as well, with no new JS framework.

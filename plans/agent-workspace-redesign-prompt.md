# Agent workspace redesign — Claude Design prompt

Copy everything below the line into Claude Design.

---

## Prompt

Redesign the **agent calling workspace** for OnPoint Call, a call-center app. Optimize for agents who spend hours on this screen: dial → talk → disposition → next lead, as fast and low-friction as possible.

### Stack constraints (do not change)

- Laravel Blade + Livewire + Tailwind CSS v4
- No Filament / no React / no new UI framework
- Keep existing Livewire actions/bindings (`wire:click`, `wire:model`, `wire:poll`)
- Deliver redesigned Blade markup + Tailwind classes (and light CSS tokens in `app.css` if needed)
- Desktop-first (agents on desktops), but usable on tablet/mobile

### Files to redesign

1. `resources/views/layouts/agent.blade.php`
2. `resources/views/livewire/agent/workspace.blade.php`
3. `resources/views/livewire/agent/partials/lead-panel.blade.php`
4. `resources/views/livewire/agent/partials/phone-display.blade.php`
5. `resources/views/livewire/agent/partials/lead-readonly.blade.php`
6. Optionally `resources/css/app.css` for theme tokens

### Product context

Agents pull a lead, call the phone number, review lead context, optionally run Soft Score / open booking form, then disposition the call. Side panels show today’s scoreboard, leaderboard, callbacks, and lead lookup.

### Hard UX rules

1. **Phone is the hero.** Largest, most prominent element. Click copies phone (desktop). `tel:` on mobile only when not manual-dial-only. Only phone may be easily copyable; other PII stays `select-none` / copy-friction.
2. **Group lead fields into clear sections** (not one flat grid). Suggested grouping:
   - **Call target:** Phone (hero), Name, Manual-dial warning if applicable
   - **Where / when:** City, State, Zip, Address/Address 2, Lead local time
   - **Opportunity context:** Venue, Event, Partners, Source file, Attempts, Lead ID
   - **Demographics / profile** (show only if present): Age range, Annual income, Marital status, Gender, Homeowner, Email, Phone 2
   - **Tour / TNB** (show only if present): Tour location, Tour date(s), Premiums, Tour result, Tour / no show, Original submit date, Booking ID
   - **Extra fields:** remaining dynamic fields, collapsed or lower priority
3. **Disposition is the primary action zone** after the call. Keep it always visible while a lead is open—sticky footer or dedicated right/bottom panel. Don’t bury it under history.
4. **Visual hierarchy for dispositions** (group + weight):
   - Success: Booked (primary/strong)
   - Continue: Callback, No Answer, Left VM
   - Negative/terminal: Not Interested, Not Qualified, Bad Number, Wrong Number, DNC
   - Utility: Skip (with required reason), separate from main dispositions
   - Callback shows datetime picker only when relevant (or clearly associated with Callback)
5. **Soft Score + Open Booking Form** should sit near call context / after talking points—not mixed into disposition buttons.
6. **Empty / terminal states** must be obvious: booked / terminal / DNC read-only banners; overdue callbacks visually urgent.
7. **Secondary panels** (scoreboard, leaderboard, callbacks, lookup) must not compete with the active lead. De-emphasize until needed.
8. Preserve all functionality and copy behavior; this is a layout/visual/usability redesign only.

### Page structure goals

- Active lead workspace dominates the viewport
- Scoreboard / leaderboard / callbacks / lookup are secondary (sidebar, tabs, or below-the-fold)
- Clear section headers, scannable labels, tight spacing, large hit targets for disposition buttons
- Reduce visual noise: fewer equal-weight white cards, clearer primary vs secondary surfaces
- Professional call-center tool aesthetic (calm, high contrast, fast)—not marketing landing page, not purple AI-default theme

### Brand

- App name: OnPoint Call
- Existing brand mark component: `<x-brand-mark />`
- Prefer a restrained blue + slate system; Instrument Sans is already the sans font

### Deliverables

1. Redesigned Blade templates with Tailwind
2. A short note explaining section grouping and why the disposition layout improves call speed
3. Keep Livewire bindings intact so this can be dropped into the app

### Out of scope

- Don’t change backend PHP logic, enums, routes, or Livewire methods
- Don’t redesign Filament manager/admin
- Don’t add websockets or new JS frameworks

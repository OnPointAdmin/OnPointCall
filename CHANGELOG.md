# Changelog

Notable changes to On Point Contact Assist. Newest first.

Work that has landed on `master` but is not yet dated sits under **Unreleased**. On each production deploy, move those bullets into a `## YYYY-MM-DD` heading and start a fresh Unreleased section.

## Unreleased

### Added

## 2026-09-15

### Fixed

- Let reps expand Results by Rep to calling-list detail even when they only worked one list.

## 2026-09-14

### Added

- Credit Card Type on leads, backfilled from Salesforce.

### Changed

- Cascade Assign and Qualify filter options from the matching holding pool and clear stale selections when filters change.
- Sort import batches by Imported At, newest first.
- Rename the application to On Point Contact Assist.
- Move Assign and Qualify filters to page chrome so the table toolbar keeps search and the column chooser.

### Fixed

- Show check errors when clicking Error on import and qualify batches.
- Format check-error modals so JSON and HTML API failures are readable.

## 2026-09-11

### Changed

- Show lead table filters in a vertical dropdown on every screen.

### Added

- Let admins change a lead next day part without recycling.

## 2026-09-09

### Added

- Unify lead tables behind LeadsTable presets with per-user column layouts.
- Admin Help About page for OnPoint Marketing's Lead Booking Application.
- Salesforce booking check on import and Qualify so matching tours mark leads Booked and stay off Assign.

### Changed

- Group import and qualify batch views into sections and break DNC hits down by list.

### Fixed

- Make the post-login chooser work on iPhone over HTTP.
- Count Soft Score Q vs NQ from the actual code so batch summaries and filters match the leads.

## 2026-09-08

### Added

- Qualify Leads and Qualify Batches so managers can re-run checks on filtered lead pools with import-style batch progress.
- Let Call Detail pick any columns and drag them into custom order.
- Let Assign Leads match qualified partners in-list or as just those partners.

### Changed

- Let Assign and Qualify filter qualified partners by None for leads with no booking partners.

## 2026-09-07

### Added

- Restore the Reports menu and add a Call Detail CSV of leads called.
- Let managers add and remove Call Detail columns including partners, demographics, and Soft Score.
- Let managers see and filter qualified partners when assigning leads.
- Let managers click Totals counts to see the matching leads.
- Report Scheduler for dashboard and report emails.
- Expand Agent Dashboard totals by disposition and reason.
- Cloud Agent dev environment install/start scripts.

### Changed

- Redesign lead view with a sectioned infolist and clearer history.
- Qualify leads from booking partners, not the lead-only Salesforce list.
- Expand Other and Wrong/DNC dashboard totals into the actual dispositions.
- Lay Totals across the same metric grid as Results by Rep.
- Dashboard calling-list breakdown and UI improvements.
- Update README and COMMANDS for local deployment; native select on Performance by Lead Source.

### Fixed

- Qualified-partner filtering on Postgres json columns.
- Windows scp in deploy.ps1 so prod deploys copy with a relative path.
- Open Totals modal lead links in a new tab.
- Remove Overdue Call Backs from Agent Dashboard tables.
- Show evening windows on dashboard queue status.

### Removed

- Navigation parent item from Performance by Lead Source and Report Schedules.

## 2026-09-06

### Added

- State-scoped blackout dates with automatic area-code matching (description required).
- Performance by Lead Source report with configurable grouping.
- Fresh column on the calling lists table.

### Changed

- Show venue, event, and list assignment time on calling list leads.
- Show Added to list by default and backfill remaining list dates.
- Reorganize dashboard navigation.

## 2026-09-04

### Added

- Import batch list filters with consistent Start Date and End Date labels.

### Fixed

- TNB imports to use caller_id as the primary phone.

## 2026-09-03

### Changed

- Dashboard calling list breakdown and totals header to reflect total leads.

## 2026-09-02

### Changed

- Unify sign-in at the homepage with a post-login chooser for admins.
- Assign Leads clear-filters and default filter data handling.

## 2026-09-01

### Added

- Cloud Agent install and start scripts for the remote dev environment.

## 2026-08-31

### Added

- Agent Dashboard queue status and Assign Leads slide-over view.

### Changed

- Restyle queue status as a horizontal table at the bottom of the Agent Dashboard.

### Fixed

- Queue status cadence timing cell overflow and wrapping headers.

## 2026-08-29

### Added

- Lead assignment table with max-count filtering and selected-leads description.
- Start and end create-date filters on Assign Leads.

### Removed

- Allowed Email resource; invites no longer depend on an allowlist.

## 2026-08-28

### Added

- Configurable dispositions with admin CRUD and dynamic agent workspace.
- Venue and event filters on the admin leads table.
- Calling list filter on the Agent Dashboard; shrink totals cards to fit without scrolling.
- Full DNC registry details on leads and history.
- Calling list on the agent lead Source Information panel.

### Changed

- Ignore national and state DNC for opted-in imports while still flagging litigator, state (when no consent), and internal lists.

## 2026-08-27

### Changed

- Merge cadence wait breakdown into a single queue status table.

## 2026-08-26

### Changed

- Dialable inventory and related dashboard/queue components.
- Workspace lead lookup.

### Fixed

- Agent Dashboard Today to use company-local dates.

## 2026-08-25

### Added

- Source, Last Disp, and attempt filters on Assign Leads.
- Last Disp and Holding filters on the Leads table.
- User and calling-list filters plus header sorting on List Assignments.
- Password-change requirement on first sign-in.

### Changed

- Never-dialed leads skip cadence waits.
- Default List Assignments to sort by calling list.
- LeadMaster migration command and user profile management.

### Fixed

- Assign Leads default qualification filter hiding production holding leads.

## 2026-08-24

### Added

- Disposition reason options on forms.

### Changed

- Booking URL and parameter mapping in app settings.
- Workspace callback put-back and UI updates.

### Fixed

- Filament creates missing company_id; show times in company timezone.
- Callback pickers; keep skipped leads off the next-lead queue.

## 2026-08-21

### Changed

- Dashboard and calling-list usability.
- Map all LeadMaster CSV columns and persist Standard cadence wait rules.

### Fixed

- Skip wait-after columns when provisioning cadences before that migration.

## 2026-08-20

### Added

- Default NQ and NI disposition reasons from the reference spreadsheet.

### Changed

- Lead import DNC compliance and batch handling.
- LeadMaster migration date handling.

## 2026-08-17

### Changed

- Lead import DNC compliance and batch handling.

## 2026-08-14

### Added

- Salesforce ID on user invite and management.
- DNC import checks.
- Last disposition and last call date on the admin lead list.

### Changed

- Qualification and Soft Score processing in import workflows.
- Preserve original CSV filename on lead import batches.

## 2026-08-13

### Added

- Qualification in lead management.

### Changed

- Lead demographic selection on Assign Leads.

## 2026-08-12

### Added

- Lead type definitions, Standard/TNB columns, and Fresh Leads filter dropdowns.
- RND checks and additional import fields.

## 2026-08-11

### Added

- Resend user invites, blue agent/admin theme, and safer local Docker tests.

### Changed

- Environment configuration and documentation; remove outdated specs.
- Invite emails include the admin login link only for manager and admin roles.
- Default import mappings (including SSIS); remove Holding Release page.

## 2026-08-10

### Added

- Initial repo, call center architecture, Docker Compose scaffold, and Laravel 13 / Filament 4 / Socialite stack.

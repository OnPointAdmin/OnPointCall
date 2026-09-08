# Call Detail report

Add a **Call Detail** report under **Dashboards and Reports → Reports**: one row per call (disposition or skip) in the date range, with the Agent Dashboard filters plus dispositions, a CSV export, and optional scheduled email of that CSV.

## Why

Results by Rep is a rollup. Managers also need the underlying leads so they can export to Excel. The **Reports** parent nav item was removed; restore it so this page, Performance by Lead Source, and Report Scheduler nest under it again.

## Filters

Same as Agent Dashboard (Rep, Lead type, Calling list, Start/End Date, date presets), plus a multi-select **Dispositions** (company definitions, including Skip). Empty = all. Skip matches `lead_history.event_type = skip`.

One row per matching history event (a lead called twice in the range appears twice).

## CSV columns

Called At, Rep, Disposition, Reason, Note, Callback At, Calling List, Lead Type, First Name, Last Name, Phone, Phone 2, Email, City, State, Zip, Venue, Event, Partner List, Lead ID, Booking ID, Current Status, Attempt Count.

UTF-8 with BOM for Excel.

## Scheduling

New `report_schedules.report_type` value `call_detail`. Periods match other date-range reports. Persist optional filters JSON (`agent_id`, `lead_type`, `calling_list_id`, `dispositions`). Email a short HTML summary plus the CSV attached. Send now / cron reuse the existing digest command.

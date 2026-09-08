# Call Detail report

Add a **Call Detail** report under **Dashboards and Reports → Reports**: one row per call (disposition or skip) in the date range, with the Agent Dashboard filters plus dispositions, a CSV export, and optional scheduled email of that CSV.

## Why

Results by Rep is a rollup. Managers also need the underlying leads so they can export to Excel. The **Reports** parent nav item was removed; restore it so this page, Performance by Lead Source, and Report Scheduler nest under it again.

## Filters

Same as Agent Dashboard (Rep, Lead type, Calling list, Start/End Date, date presets), plus a multi-select **Dispositions** (company definitions, including Skip). Empty = all. Skip matches `lead_history.event_type = skip`.

One row per matching history event (a lead called twice in the range appears twice).

## Columns

A **Columns** multi-select sits with the other Call Detail filters. Choose any lead fields, including qualified partners, demographics, Soft Score, DNC, RND, and tour fields. Drag the selected chips to reorder the table and CSV. Default table columns stay Called At, Rep, Name, Phone, Disposition, Reason, Calling List, Venue, Event. **All columns** / **Default columns** shortcuts sit under the picker. Scheduled emails with no columns selected keep the original standard CSV.

UTF-8 with BOM for Excel.

## Scheduling

New `report_schedules.report_type` value `call_detail`. Periods match other date-range reports. Persist optional filters JSON (`agent_id`, `lead_type`, `calling_list_id`, `dispositions`, `columns`). Email a short HTML summary plus the CSV attached. Send now / cron reuse the existing digest command.

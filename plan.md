# Palm Aire Tour No Buys — API plan

**Status:** plan only. No code, no SQL Agent change, no SSIS change, no Salesforce change, no OPC production change until Jason says approved.

**Branch:** `onpointcall/palmaire-tnb-api`

**Sources read 2026-10-16:**

- `D:\sf\OnPointSQL\docs\integrations\Export Tour No Buys Palm Aire - current process.md`
- `D:\sf\OnPoint SSIS\Lead Processing\Export Tour No Buys Palm Aire.dtsx`
- `D:\sf\OnPointSQL\docs\database\Leads\procedures\dbo.Get_Leads_Bookings_1.0.sql` (body matches `sys.sql_modules` on ONPOINTSQL1 after ignoring `CREATE OR ALTER` vs `CREATE` and whitespace; server `modify_date` 2026-07-28)
- `dbo.Log_Lead_Export2`, `dbo.Check_Lead_Export2`, `dbo.CampaignMember_Upsert`, `dbo.Mark_Sold_Leads`
- `SalesforceInterface.dbo.Lead_Assign_Call_Center_Update` (live definition excerpt)
- OPC: `ImportMappingSeeder` TNB map, `LeadImportService`, `CallingListSeeder`, `bootstrap/app.php`, `config/services.php`, `plans/booking-callbacks-dialer.md`
- Live `Leads.dbo.Get_Leads_Log` / `Get_Leads_Results` for ExportName `OPMA Travel Tour No Buys`

---

## Goal

Stop the hand-run SSIS package that writes a Palm Aire Tour No Buy CSV to OneDrive and then gets imported into OnPoint Call. Select the same bookings and land them in OPC as `lead_type = tnb`, and keep the Salesforce / `Log_Exports` write-backs that follow a successful export. The CSV and the manual Filament upload go away for this feed. The SSIS package stays deployed until an explicit cutover.

---

## Recommendation

**A scheduled OPC command that calls the existing procedure `Leads.dbo.Get_Leads_Bookings_1.0` with the Palm Aire parameter set, then inserts the kept rows through the same mapping the TNB CSV import uses.**

Do not reimplement the selection in SOQL or Apex. Most of the discard rules are not Salesforce fields. They live in `Leads` (`Log_Exports`, `All_Leads` scrub flags) and in `SalesforceBackup.dbo.DNC__c`.

| Option | What it would do | Why it is not the recommendation |
|---|---|---|
| OPC pulls Salesforce REST / SOQL | New command queries `Booking__c` the way `salesforce:sync-booking-callbacks` queries callbacks | Callback sync is a short status/type filter (`config/services.php` `booking_callbacks`). Palm Aire selection is the procedure below. A SOQL port would drift from `Log_Exports`, DNC, and the lead scrub. |
| Salesforce pushes to a new OPC endpoint | Flow or Apex posts each matching booking | Salesforce cannot see `Log_Exports` or `All_Leads`. OPC has no `routes/api.php`. Routing is web + console + `/up` only (`bootstrap/app.php`). A public ingest API would be new auth surface for a 17–26 row feed. |
| Scheduled OPC job that reuses the procedure | Artisan command, same shape as `salesforce:sync-booking-callbacks`, runs the proc, then creates TNB leads | Keeps one selection implementation. Write-backs stay the procs that already run after the CSV. |

The callback job is the pattern for **scheduling and Salesforce auth already in OPC**. It is not the pattern for **which rows qualify**.

This feature is Palm Aire only. Midtown, Syndicate Travel, and generic Tour No Buys stay on SSIS.

---

## How selection works today

Package **Export OPMA - Tour No Buys Palm Aire** truncates `Temp_Get_Leads` and `Temp_Export_Leads`, then executes `Get_Leads_Bookings_1.0`. There is no SQL Agent job for this package (confirmed in the process doc, 2026-10-16). Recent evidence is `Get_Leads_Log`.

### Palm Aire arguments (from the package SQL)

SSIS binds only `Client`, `pCountRequested`, `TourLocations`, `TourDateStart`, `TourDateEndMonthsBack`, and `TourOrNoShow`. Everything else is a literal in the Execute SQL task (`scripts/Counts/Execute Get Leads Tour No Buys Palm Aire SSIS.sql` matches that SQL).

| Parameter | Value this package sends | What the procedure does with it |
|---|---|---|
| `@ClientName` | `On Point Marketing Agency` | Campaign must exist: `SalesforceBackup.dbo.Campaign.Partner_Name__c = @ClientName`. Also the `Log_Exports.Client` value for the sold check. |
| `@ExportName` | `OPMA Travel Tour No Buys` | Stamped on temp rows and `Get_Leads_Log`. Not a filter. |
| `@Vertical` | `Travel` | Passed in, so the proc does **not** look up `Campaign.Vertical__c`. |
| `@LeadSource` | `On Point, On Point Marketing Agency` | `Account.Name` of `Booking__c.Booked_By_Company_2__c`, comma list, trim, exact match. |
| `@Venue` | `''` | **Not a filter.** The parameter is only copied into the criteria string when non-blank. There is no venue `UPDATE`/`WHERE` in the procedure. |
| `@TourLocation` | `Club Wyndham Palm Aire` | `Tour_Location__c.Name`. Blank would mean all locations. Comma list is supported. This package passes one name. |
| `@CountRequested` | `0` | After discards, `0` is replaced with the number of distinct `BookingId` still kept. No random trim. |
| `@MinIncome`, `@MaxIncome`, `@MinAge`, `@MaxAge` | `''` (become integer 0) | The discard `IF ISNULL(@MinIncome,'') <> ''` is false for 0. **Age and income discards do not run.** The log line `Min Age >= 0, Min Income >= 0` is the criteria text, not a filter that removed rows. |
| `@MaritalStatus` | `''` | Marital discard does not run (`''` and `'All'` are skipped). |
| `@TourAgeStart` | `2025-10-01` (package default) | Lower bound of `Booking__c.Tour_Date_Formula__c`. Fixed date, not “6 months ago”. |
| `@TourAgeEnd` | `DATEADD(DAY, 2, DATEADD(MONTH, -TourDateEndMonthsBack, GETDATE()))` with months back = **6** | Upper bound. Package `TourDateEnd` default `02/28/2026` is **not** what the SQL uses. |
| `@CheckPartnerList` | `false` | Partners-list date is still looked up and written into the criteria string. It does **not** abort the run and does **not** discard rows. |
| `@CheckClientZips` | `''` → false | No include/exclude zip discard. |
| `@CheckSoldLeads` | `true` | Discard when `Log_Exports` has the same `Client` and `Phone`. |
| `@RecycleSameClientDate` | `''` | Sold check is **any date**, not “sold since”. |
| `@ExcludeSoldSameVertical` | `false` | Same-vertical sold discard does not run. |
| `@RecycledOnly` | `false` | Does not require a prior export. |
| `@CheckPartnerListField` | not assigned in the package SQL (NULL) | “Not in Partner List field” discard does not run. |
| `@TourOrNoShow` | `Tour` | Chooses the tour-result list below. |
| `@ZipFileName`, `@IncludeOrExcludeZip`, `@ZipFileSegment`, `@States` | blank / unset | No zip or state discard. |
| `@SessionId` | not assigned before the proc | Proc does `ISNULL(@SessionId, NEWID())` and returns it as `@SessionIdOut`. |

Declared in the SSIS batch and **not passed** into this procedure (no effect): `@CheckClientSuppression`, `@ExcludeBookings`.

### Pre-checks (can abort before any booking is read)

1. Zip file exists — only when `@CheckClientZips` is true. **Off for Palm Aire.**
2. `SF_Mirror` of `Campaign`, `CampaignMember`, and `Partners_List__c`. Does **not** mirror `Booking__c`. Bookings come from whatever `SalesforceBackup` already has (the replicate job). Freshness of `Booking__c` is **UNKNOWN** relative to these manual runs.
3. `Clean_Client_Zips`.
4. Campaign row for `@ClientName`. Missing → error `Campaign Does Not Exists`, zero rows.
5. Vertical. Already `Travel`, so the campaign lookup is skipped.
6. Oldest `Partners_List__c.Date_Added__c` for partner name. Client `On Point Marketing Agency` does not match `%Call Center%` or `%OPMA%`, so partner name is the client name itself. Live logs show **Partners List Date = 2023-07-28**. Because `@CheckPartnerList` is false, a missing date would not stop the run.
7. If `@TourAgeEnd` is null, it becomes `GETDATE()`. Palm Aire always passes a computed end, so this default does not apply.

### Core booking query

From `SalesforceBackup.dbo.Booking__c` `b`:

- `LEFT JOIN Leads.dbo.All_Leads` `l` on `b.Lead__c = l.Id` (a booking with no lead row is allowed)
- `LEFT JOIN Tour_Location__c` `tl` on `b.Tour_Location__c`
- `LEFT JOIN Venue__c` `v` on `b.Venue__c`
- `LEFT JOIN Event__c` `e` on `b.Event__c`
- `LEFT JOIN Account` `a` on `b.Booked_By_Company_2__c`
- `LEFT JOIN Employee__c` `e2` on `b.Representative__c` (joined, not used by the active SELECT or WHERE)

Rows are inserted into `Temp_Get_Leads` with `Keep = 1`. `Id` stored for export is `ISNULL(l.Id, b.Id)`: the Salesforce **Lead** id when `All_Leads` has the booking’s lead, otherwise the **Booking** id. `BookingId` is always `b.Id`.

**Active WHERE (all of these):**

1. `ISNULL(b.Tour_Result__c,'')` is in the `@TourOrNoShow = 'Tour'` list, split and trimmed:
   - `No Sale`
   - `No Tour`
   - `NQ - Brought Kids`
   - `NQ - Late`
   - `NQ - No CC`
   - `NQ - No Spouse`
   - `NQ - Own Travel Club`
   - `NQ - Age`
2. `ISNULL(b.Status__c,'')` is not `Cancelled`, `Not Qualified`, or `Not Interested`.
3. `b.Phone__c` is not null/blank.
4. `ISNULL(a.Name,'')` is `On Point` or `On Point Marketing Agency`.
5. `b.Tour_Date_Formula__c` is between `@TourAgeStart` and `@TourAgeEnd` (inclusive `BETWEEN`).
6. `tl.Name` is `Club Wyndham Palm Aire`.
7. Lead scrub, only when a lead row exists (`l.Id IS NULL` passes). When the lead exists, all of these must be true:
   - `Check_DNC__c` false
   - `Consent__c` true
   - `Suppress_IDNC__c`, `Suppress_IDNC_2__c`, `Suppress_IDNC_3__c` false
   - `Suppress_Litigator__c`, `Suppress_Litigator_2__c`, `Suppress_Litigator_3__c` false
   - `Invalid_Area_Code__c` false
   - `DNC_Country__c` = `US`

`SELECT DISTINCT` plus `ORDER BY NEWID()`. Which row wins a later phone/email tie is whichever insert got the lower `Temp_Id`. That order is random.

**Commented out in the procedure. Not applied. Do not turn these on as part of this feature unless Jason asks:**

- Partner list contains `Wyndham Destinations`
- `Qualification_Code__c` in `Q`, `PC1` (lead or booking)
- Booking age-range allow-list (`28 - 29` through `75+`, and similar bands)
- Booking income in `$60k - $74k`, `$75k - $99k`, `$100k +`, `$100K +`
- Credit card in American Express / Visa / Mastercard / Discover Card
- Married, or female and single
- State in NY / NJ / CT
- `Qualified__c` or `Valid_UnVerified__c`, and age range not under 18
- Exclude representative `Bob Behrens`

`@TourOrNoShow` other than `Tour` would use `No Tour`, `No Show`, and `''`. Palm Aire passes `Tour`, so no-shows are not selected.

### Columns copied onto the temp row (sources)

These are the procedure’s sources. The CSV step adds two transforms, listed under the OPC map.

| Temp column | Source |
|---|---|
| `Id` | `ISNULL(All_Leads.Id, Booking__c.Id)` |
| `BookingId` | `Booking__c.Id` |
| `Phone` | `Booking__c.Phone__c`, then `StripCountryCode` |
| `Phone_2` | `Booking__c.Phone_2__c`, then `StripCountryCode` when non-blank |
| `FirstName` / `LastName` | `First_Name__c` / `Last_Name__c` |
| `FirstName2` / `LastName2` | `First_Name_2__c` / `Last_Name_2__c` |
| `Email` | `Email__c` |
| `Address` | `fn_ProperCase(fn_RemoveNonAscii(Street__c))` |
| `Address2` | `Unit_Number__c` |
| `City` / `State` / `Zip_Code` | `City__c` / `State__c` / `Postal_Code__c` |
| `Gender` | `Gender__c` |
| `Age_Range` | `Age_Range__c` |
| `Income` | `Income__c` |
| `Marital_Status` | `Marital__c` (booking). `MaritalStatus` on the temp row is `All_Leads.MaritalStatus` and is what the marital discard would use. That discard is off. |
| `Home_Owner` | `HomeOwner_or_Renter__c` |
| `Credit_Card` | `ISNULL(Have_Major_Credit_Card__c, All_Leads.Type_of_Credit_Card__c)` |
| `JornayaLeadId` | `All_Leads.UniversalLeadiD__c` |
| `Trusted_Form` | `All_Leads.Trusted_Form__c` |
| `Entry_Created_Date` | `Entry_Submitted_Date__c` |
| `Venue` / `Event` | `Venue__c.Name` / `Event__c.Name` |
| `Partners_List` | `CONCAT(All_Leads.Partners_List__c, ',', Booking__c.Partners_List__c)` |
| `TourLocation` | `Tour_Location__c.Name` |
| `TourDate` | `CAST(Tour_Date_Formula__c AS date)` |
| `Premiums` | `Premium_Combined_2__c` |
| `Status` | `Status__c` |
| `Tour_Result` | `Tour_Result__c` |
| `TourOrNoShow` | the parameter (`Tour`) |
| `ExportDate` | proc start time |
| Min/max age and income, sold-to fields, qualification flags | from `All_Leads` when the lead exists. Not used by Palm Aire’s active discards. |

Address fields are the custom booking fields (`Street__c`, `Unit_Number__c`, `City__c`, `State__c`, `Postal_Code__c`), not the standard address compound.

### Discard pipeline (in order)

Each step sets `Keep = 0` and a reason. Steps that are conditional do not increment `@DiscardOrder` when skipped. Palm Aire’s live “Already Sold” rows are **Discard_Order 3**, which matches: (1) already in `Temp_Export_Leads`, (2) `DNC__c`, (3) sold to this client.

| Order for Palm Aire | Reason | On? | Rule |
|---|---|---|---|
| 1 | `Lead Already Selected in Export File` | Always | Phone already in `Temp_Export_Leads`. SSIS truncates that table first, so this is empty unless another session inserted into it during the run. |
| 2 | `Phone in DNC__c` | Always | `SalesforceBackup.dbo.DNC__c.Phone_Cleaned__c` equals `Phone` or `Phone_2`. |
| — | Before partner was added | **Off** (`@CheckPartnerList` false) | Would drop `Entry_Created_Date <= @Date_Added` (2023-07-28). |
| — | Zip include / exclude | **Off** | |
| — | Not in state list | **Off** | |
| — | Income vs `All_Leads.MinIncome` / `MaxIncome` | **Off** | Would use lead min/max, not `Booking__c.Income__c`. |
| — | Age vs `All_Leads.Min_Age` / `Max_Age` | **Off** | Max age uses `>= @MaxAge`. Would use lead ages, not `Age_Range__c`. |
| — | Marital status | **Off** | Would use `All_Leads.MaritalStatus`, not `Booking__c.Marital__c`. |
| 3 | `Already Sold To On Point Marketing Agency` | **On** | `Log_Exports.Client = @ClientName` and `Log_Exports.Phone = temp.Phone`. No date limit. This is “already exported to this client”, despite the reason text saying sold. |
| — | Already sold same vertical `Travel` | **Off** | |
| — | Recycled only | **Off** | Would drop phones with no prior `Log_Exports` row in this vertical. |
| — | Not in partner-list field | **Off** | |
| next | `Dupes Phone` | Always, only if duplicates remain | Keeps the lowest `Temp_Id` per phone. |
| next | `Dupes Email` | Always, only if duplicates remain | Same for email. SQL `NULL = NULL` is unknown, so blank emails are not collapsed together. |
| next | `Over Count Requested` | **Off** when count requested is 0 | Would randomly drop extras. |

Kept rows (`Keep = 1`) are copied into `Temp_Export_Leads`. `@CountAvailable` and `@CountReturned` are `COUNT(DISTINCT BookingId)`, not phone counts. The SSIS file name uses `COUNT(DISTINCT phone)` from `Temp_Export_Leads` (`User::RecordCount`). Those two counts can differ. **UNKNOWN** whether they differed on recent runs; the log’s returned count is distinct bookings (26 and 17).

The proc then writes `Get_Leads_Log` and `Get_Leads_Results` (one group per keep/reason, plus a `Total Leads` row).

### What the last runs actually did

`Get_Leads_Log`, ExportName `OPMA Travel Tour No Buys`, criteria contains `Club Wyndham Palm Aire`. Queried 2026-10-16.

| Start (server time) | Session | Start date in criteria | End date in criteria | Available = requested = returned |
|---|---|---|---|---|
| 2026-10-13 19:08 | `1daf20b5-cd09-469f-81c1-834ee8243086` | 2025-10-01 | 2026-04-15 | 26 |
| 2026-09-30 20:30 | `3fefdb7f-ccd4-478a-a74e-8230ac9257fd` | 2025-10-01 | 2026-04-01 | 17 |
| 2026-09-14 15:23 | `dfa2e366-f65e-470d-b7bc-1bb4c82c259d` | 2025-10-01 | 2026-03-16 | 15 |
| 2026-09-04 18:43 and 17:55 | two sessions | 2025-10-01 | 2026-03-06 | 3 and 3 |

2026-04-15 is 13 Oct 2026 minus 6 months plus 2 days. 2026-04-01 matches 30 Sep 2026 the same way.

**2026-10-13 discard breakdown** (`Get_Leads_Results` for that session):

| Keep | Reason | Count |
|---|---|---|
| no | Already Sold To On Point Marketing Agency (order 3) | 93 |
| yes | Good Leads | 26 |
| yes | Total Leads | 119 |

119 = 93 + 26. No DNC row and no phone/email dupe row were stored, so those steps removed nobody on that run. The 93 were already in `Log_Exports` for client `On Point Marketing Agency` on the same phone.

Criteria string on that run (full):

`Client = On Point Marketing Agency, Partners List Date = 2023-07-28, Vertical = Travel, Marital = , Min Age >= 0, Min Income >= 0, Lead Age Start 2025-10-01, Lead Age END 2026-04-15, Check Zips = No, Lead Source = On Point, On Point Marketing Agency, Tour Location = Club Wyndham Palm Aire`

“Lead Age Start/END” in that string is the **tour date** window (`Tour_Date_Formula__c`), not the person’s age.

---

## Field mapping (current CSV → OPC TNB map)

OPC import mapping **TNB** (`database/seeders/ImportMappingSeeder.php`) sets `lead_type` to `tnb`. `LeadImportService::mapRow` copies only headers named in that map onto known lead columns. Unmapped CSV columns are dropped. Keys prefixed `extra.` would go to `extra_fields`; the TNB seed has none.

CSV address is `RTRIM(CONCAT(Address, ' ', Address2))` in the data flow. CSV `original_lead_submit_date` and `ExportDate` are the date (not datetime). `File Name` is the SSIS variable.

| CSV header | SQL source | OPC lead column (TNB map) |
|---|---|---|
| `caller_id` | `Phone` after `StripCountryCode` | `phone` (OPC `PhoneNormalizer` runs again) |
| `first_name` | `First_Name__c` | `first_name` |
| `last_name` | `Last_Name__c` | `last_name` |
| `address` | proper-cased `Street__c`, then CSV concatenates unit | `address` |
| `city` | `City__c` | `city` |
| `state` | `State__c` | `state` (OPC uppercases, first 2 characters) |
| `zip` | `Postal_Code__c` | `zip` |
| `email` | `Email__c` | `email` |
| `AgeRange` | `Age_Range__c` | `age_range` |
| `annual_income` | `Income__c` | `annual_income` |
| `Marital Status` | `Marital__c` | `marital_status` |
| `Gender` | `Gender__c` | `gender` |
| `HomeOwner` | `HomeOwner_or_Renter__c` | `home_owner` |
| `OP_Id` | `ISNULL(Lead Id, Booking Id)` | `external_lead_id` |
| `jornayaleadid` | `UniversalLeadiD__c` | **not in the TNB map — dropped** |
| `trustedform` | `Trusted_Form__c` | **not in the TNB map — dropped** |
| `Venue` | `Venue__c.Name` | `venue` |
| `Event` | `Event__c.Name` | `event` |
| `original_lead_submit_date` | `Entry_Submitted_Date__c` as date | `original_lead_submit_date` |
| `PartnerList` | concat of lead and booking partner lists | `partner_list` |
| `BookingId` | `Booking__c.Id` | `booking_id` |
| `Phone_2` | `Phone_2__c` | `phone_2` |
| `FirstName2` | `First_Name_2__c` | `first_name_2` |
| `LastName2` | `Last_Name_2__c` | `last_name_2` |
| `Address2` | `Unit_Number__c` | `address_2` |
| `TourLocation` | location name | `tour_location` |
| `TourDate` | tour date | `tour_date` |
| `Premiums` | `Premium_Combined_2__c` | `premiums` |
| `Tour_Result` | `Tour_Result__c` | `tour_result` |
| `TourOrNoShow` | parameter `Tour` | `tour_or_no_show` |
| `ExportDate` | proc start, as date | **not in the TNB map — dropped** |
| `Type_of_Credit_Card__c` | credit card expression above | `credit_card_type` |
| `File Name` | SSIS file name | `file_name` |

TNB map also has `tour_date_start` ← header `TourDateStart`. This package’s CSV header is `TourDate`, not `TourDateStart`, so `tour_date_start` stays empty on current imports.

`Lead.salesforce_booking_id` exists on the model (callback sync writes `Booking__c.Id` there). The TNB map writes `BookingId` to `booking_id` only. Whether this feed should also set `salesforce_booking_id` is **UNKNOWN**.

New OPC rows from `LeadImportService` are inserted with `status = holding`. They are **not** placed on the seeded calling list named `TNB`. A person releases holding rows onto a calling list later. Whether this job should release them onto that list is **UNKNOWN**.

Seed data (not proof of production ids): company name `OnPoint Call Center` (`CompanySeeder`), calling list name `TNB` with `lead_type` `tnb` (`CallingListSeeder`). Production company id and list id are **UNKNOWN**.

---

## Upsert / already sent

Two different dedupe systems exist today. They do not use the same key.

**SQL, before the file is written**

- Skip if this client already has the phone in `Log_Exports` (any date).
- Then one row per phone, then one row per email, inside the batch.
- Not keyed by Booking Id. Two Palm Aire bookings for one phone become one export row (random survivor).

**OPC, when the CSV is imported** (`LeadImportService::resolveDedupe`)

- Match `external_lead_id` or `phone` inside the same `company_id`.
- Same person on both, different lead rows → `conflict`, row skipped.
- Any match that is not a holding lead stuck on a failed check (`soft_score`, `rnd`, `qualification`, `dnc`, or `booking_check` status `error`) → `duplicate`, row skipped. **No update of tour date, tour result, or booking id.**
- Blank phone is counted as a skipped invalid phone (`caller_id` failed normalize).
- `booking_id` / `salesforce_booking_id` are not dedupe keys for CSV import. Callback sync is the path that upserts on `salesforce_booking_id`. That is a different feature.

**Recommended behavior for this job, pending Jason (open question 5 and 6):** keep both gates. The procedure still drops phones already in `Log_Exports`. OPC still skips an existing phone or `external_lead_id` instead of updating. Do not invent a booking-id upsert in this feature.

Write `Log_Exports` only after OPC has accepted the batch. Today SSIS logs when the CSV row count is non-zero, even if nobody imports the file. That is why a failed OPC step must not call `Log_Lead_Export2`: the next run would treat those phones as already sold.

---

## Auth and secrets

| System | What exists in code | This plan |
|---|---|---|
| Salesforce | OPC `config/services.php` reads `SALESFORCE_CLIENT_ID`, `SALESFORCE_CLIENT_SECRET`, `SALESFORCE_INSTANCE_URL`, `SALESFORCE_API_VERSION` (default `v64.0`). `.env.example` default URL is `https://onpointmrg--staging.sandbox.my.salesforce.com`. Callback sync already uses that connected app. | No new connected app if selection stays in SQL. DBAmp linked server `SALESFORCE` is what `SF_Mirror` uses. Production OPC instance URL is **UNKNOWN** (do not copy secrets out of `.env`). |
| SQL Server | `Invoke-SqlQuery.ps1` uses the SSMS credential for `Jason.Paine` on `10.147.18.110`. | The job needs its own SQL login. Name, rights, and where the password is stored are **UNKNOWN**. Not `Jason.Paine`. Not committed. |
| OPC inbound API | No API routes. Session login is the web guard. | Not used, if the job runs inside OPC and calls SQL. |
| RND | `RND_REFRESH_TOKEN`, `RND_COMPANY_ID` in config. The reassigned file hardcodes company id `C466957229`. | Out of scope until question 7 is answered. |

---

## Write-backs that run after a non-empty CSV

SSIS runs these only when the flat-file row count is not zero. There is no Booking “mark sent” field in this package.

### 1. `Leads.dbo.Log_Lead_Export2`

The package SQL sets `@Client` only. `@Sessionid` and `@Partner` are declared and not set. The proc replaces a null session with `NEWID()`, so the session on `Log_Exports` is **not** the `Get_Leads_Bookings_1.0` session.

Then it:

- Inserts `Log_Exports` (`Lead_Id`, timestamp, client, name, phone, email, zip, select string, vertical, session) from `Temp_Export_Leads` for that client, skipping a row that already has the same lead id, client, and export **date**.
- Calls `CampaignMember_Upsert`.
- Calls `Mark_Sold_Leads`.

`CampaignMember_Upsert` truncates `CampaignMember_Upsert` and fills it from **all** `Log_Exports` since `2022-01-01` that do not already have a campaign member for that lead and campaign within one day. It is not limited to this session or this client. It mirrors `CampaignMember` and `Campaign`, then bulk-upserts on `Mojo_Composite_Key__c`. Lead id must match `SalesforceBackup.dbo.Lead`. A temp `Id` that is a Booking id (no `All_Leads` row) does not match that join. A second insert path matches `Survey_Response__c` the same way. There is no booking-id path.

`Mark_Sold_Leads` rewrites `All_Leads` sold-to / last-sold fields for every `Id` present in `Temp_Export_Leads` (not filtered by session in those updates).

### 2. `SalesforceInterface.dbo.Lead_Assign_Call_Center_Update`

SSIS declares `@Sessionid` and does not set it. The proc does not use the session to limit rows.

It mirrors, truncates `Lead_Assign_Call_Center_Update`, and inserts Lead ids whose campaign member `CreatedDate >= 2025-01-01`, whose campaign name is an Account with `Call_Center_Assign_Leads__c = '1'`, and whose `Assigned_Call_Center__c` is not already that account id. Campaign names `LIKE '%Beacon%'`, `Global Prospects Inc`, and `Wyndham Destinations` are mapped to Account **On Point Marketing Agency**. Other campaigns use `Campaign.Name` as the account name. Then `SF_BulkOps` updates Salesforce.

This is a global catch-up, not “the 26 Palm Aire leads”.

### 3. `Leads.dbo.Check_Lead_Export2`

This task **does** pass client, output count, and the get-leads session. It compares counts, looks for same-day errors in `LogErrorsSQL` where the procedure name is `dbo.Get_Leads`, `dbo.Log_Lead_Export`, or `dbo.CampaignMember_Upsert` (not `Get_Leads_Bookings_1.0` or `Log_Lead_Export2`), and checks campaign-member upsert errors. Result goes to package variable `Issue`. The package has no mail task. `EmailRecipients` (`jason.paine@onpointmrg.com`) is unused. Package `MaxErrorCount` is 0. No event handlers.

**Recommendation:** call the same three steps after OPC accepts the rows, and pass the get-leads `@SessionId` into `Log_Lead_Export2`. Do not narrow `Lead_Assign_Call_Center_Update` or `CampaignMember_Upsert` in this feature; they are already global. Whether to keep all three is open question 8.

---

## Schedule, errors, alerting, rollback

- **Schedule:** **UNKNOWN.** Recent cadence is manual, a few times a month, 15–26 leads. The command ships **disabled** (not registered in `bootstrap/app.php` `withSchedule`) until Jason picks a time and says to enable it.
- **Errors:** one run, one session id. If the proc returns an error (`Campaign Does Not Exists`, and the other abort strings), insert nothing and do not write `Log_Exports`. If OPC insert throws, do not write `Log_Exports`. Leave the temp-table rows for diagnosis. Do not truncate those tables from a second concurrent export; the proc and SSIS both treat them as global. Overlap behavior is **UNKNOWN** and the job must be `withoutOverlapping`, same as the callback command.
- **Alerting:** **UNKNOWN** who gets notified. The package parameter is unused. Do not send mail until Jason names the recipient. Persist the proc `Issue` / error text on the run record in OPC.
- **Rollback / cutover:** SSIS package stays in SSISDB and in git. No Agent job is created or edited. Cutover means Jason stops running the package by hand. Re-enable is: run the package again. Rows already in `Log_Exports` will not export a second time. OPC duplicates will skip. There is no automatic delete of leads inserted by a bad run; cleanup steps are **UNKNOWN**.

---

## Later reuse (not this PR)

Same procedure and the same CSV shape:

| Package | Location filter verified |
|---|---|
| `Export Tour No Buys Midtown.dtsx` | `Wyndham Midtown 45 At New York City` (package parameter) |
| `Export Tour No Buys Palm Aire.dtsx` | `Club Wyndham Palm Aire` |
| SSISDB `Leads\Export Tour No Buys.dtsx` and `Export Syndicate Travel Tour No Buys.dtsx` | **UNKNOWN** (listed in the process doc; parameters not re-read for this plan) |

A later change can pass a different `@TourLocation` (and client / lead source if those packages differ) into the same command. Do not add those locations, and do not change their packages, in the implementation PR.

---

## Definition of done

1. Jason has approved this plan in writing (reply “approved”).
2. A non-production OPC run, using the Palm Aire parameter set above, inserts only rows the procedure kept, with `lead_type = tnb` and the column map in the table above.
3. Phones already in `Log_Exports` for `On Point Marketing Agency` are not inserted.
4. An OPC duplicate (same company, same phone or same `external_lead_id`) is skipped, not updated.
5. `Log_Lead_Export2` runs only after the OPC insert commits, and it receives the same `SessionId` the selection used.
6. `CampaignMember_Upsert`, `Mark_Sold_Leads`, `Lead_Assign_Call_Center_Update`, and `Check_Lead_Export2` still run after that, unless Jason answers question 8 by dropping some.
7. No CSV is written and no Filament upload is required for this feed.
8. The schedule stays off until question 2 is answered.
9. SSIS package still deployed. No production cutover in the implementation PR.
10. Midtown, Syndicate, and generic Tour No Buys are unchanged.

## Test plan

Do this on non-production OPC and a SQL session that is allowed to call the proc. A full proc call mirrors Salesforce tables and writes `Get_Leads_Log`. Confirm that target before the first test run. A non-prod SQL copy is **UNKNOWN**.

1. **Parameter lock.** The command’s literals match the table in “Palm Aire arguments”, including tour end = today minus 6 months plus 2 days, and start date `2025-10-01`.
2. **Funnel shape.** After a run, `Get_Leads_Results` for that session shows Total Leads, an “Already Sold To On Point Marketing Agency” group when `Log_Exports` has those phones, and Good Leads equal to `Count_Returned`. Use the 2026-10-13 session as a description of the rules (119 / 93 / 26), not as a frozen row list. Today’s window end is later than 2026-04-15, so counts will not match.
3. **Mapping.** One kept row: `phone` from `Phone__c` (country code stripped), `external_lead_id` = lead id when present else booking id, `booking_id` = booking id, `tour_location` = `Club Wyndham Palm Aire`, `tour_or_no_show` = `Tour`, `lead_type` = `tnb`, `status` = `holding`.
4. **Dropped columns.** Jornaya and Trusted Form are not required on the lead (they are not in the TNB map). Do not fail the row because they are empty.
5. **Dedupe.** Second run with no new bookings inserts 0 leads and does not add a second `Log_Exports` row for the same phone and client. A phone that already exists in OPC is skipped.
6. **Failure.** Force the OPC insert to fail. `Log_Exports` row count for that session stays 0, and a later successful run can still select those phones.
7. **Write-back session.** `Log_Exports.SessionId` equals the `Get_Leads_Log` session. This is deliberately different from today’s SSIS call, which does not pass the session.
8. **No production side effects in CI.** Tests fake SQL and do not call `SF_Mirror`, `SF_BulkOps`, or production OPC.

---

## Out of scope

- Building the command, the tests, or the schedule entry (next PR, after approval).
- Editing the SSIS package, SQL Agent, Salesforce, or OPC production.
- Changing `Get_Leads_Bookings_1.0` filters, including the commented Wyndham / age / income / credit block.
- The `_Reassigned.csv` file (`Company_Id` `C466957229`) until question 7 is answered.
- Auto-release from holding onto the TNB calling list until question 4 is answered.
- Midtown, Syndicate Travel, generic Tour No Buys.

---

## Open questions

Anything below marked **UNKNOWN** is not decided. Do not implement a guess.

1. **What actually starts Palm Aire today?** No Agent job and no SSISDB execution rows were found. Manual SSDT is the leading explanation and is still **UNKNOWN**.
2. **Desired schedule in Pacific Time**, and should the command stay manual-only at first? **UNKNOWN.**
3. **Is the CSV still uploaded by hand** in Filament Import Leads with mapping TNB? Code has no folder watcher. Automation from OneDrive is **UNKNOWN**.
4. **Production OPC company id and calling list.** Seed names are `OnPoint Call Center` and `TNB`. Production ids are **UNKNOWN**. Should inserted rows stay in `holding`, or be released onto the TNB list immediately? **UNKNOWN.**
5. **OPC dedupe key to keep.** Code today is phone or `external_lead_id`, skip on match. Booking id is not the CSV key. Desired key is **UNKNOWN**.
6. **If a booking was already exported, skip, update, or insert another lead?** SQL skips the phone. OPC skips the phone or external id and does not refresh tour fields. Desired behavior is **UNKNOWN**. Recommendation above is skip/skip.
7. **Reassigned file `C466957229`.** Still required for RND or anyone else? **UNKNOWN.**
8. **Write-back scope.** Keep `Log_Exports` + `CampaignMember_Upsert` + `Mark_Sold_Leads` + `Lead_Assign_Call_Center_Update` + `Check_Lead_Export2`, or drop some? **UNKNOWN.** Recommendation is keep all, and pass `SessionId` into `Log_Lead_Export2`.
9. **Pass `SessionId` into `Log_Lead_Export2`?** Today the package does not. This plan recommends yes. Confirm. **UNKNOWN** until Jason says yes.
10. **Tour window.** Keep fixed start `2025-10-01` and rolling end (6 months back, plus 2 days)? Or should start move too? **UNKNOWN.**
11. **Read path.** Reuse `Get_Leads_Bookings_1.0` against `SalesforceBackup` (recommended) or replace it with live Salesforce REST? **UNKNOWN** until approved.
12. **Inbound OPC API and callback-style SOQL.** No ingest API exists. Callback SOQL is a different filter. Whether to add an endpoint anyway is **UNKNOWN**. Recommendation is not to.
13. **SQL login for the job, and where the password lives.** **UNKNOWN.**
14. **Production Salesforce instance URL** the OPC app should keep using if any call needs the connected app. `.env.example` points at the staging sandbox. **UNKNOWN.**
15. **Who is alerted on failure.** Package `EmailRecipients` is unwired. **UNKNOWN.**
16. **Booking mirror freshness.** The proc does not mirror `Booking__c`. How stale `SalesforceBackup.dbo.Booking__c` may be at run time is **UNKNOWN**.
17. **Set `salesforce_booking_id` from `Booking__c.Id`** in addition to `booking_id`? **UNKNOWN.**
18. **Non-prod SQL database** allowed to execute this proc for the test plan. **UNKNOWN.**
19. **How to undo a bad OPC insert** (holding leads already released, `Log_Exports` already written). **UNKNOWN.**

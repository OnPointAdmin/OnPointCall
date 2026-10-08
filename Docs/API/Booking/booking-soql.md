# Booking SOQL (Call Center / On Point Call)

Salesforce **Booking__c** queries for callback import, booking checks, and ops debugging. Uses the same OAuth client as [qualification-api.md](../QualificationService/qualification-api.md) — **not** Soft Score keys.

## Instance URLs

| Org | `SALESFORCE_INSTANCE_URL` |
|-----|---------------------------|
| **PROD** | `https://onpointmrg.my.salesforce.com` |
| **STAGE** | `https://onpointmrg--staging.sandbox.my.salesforce.com` |

Booking Ids are org-specific. A PROD Id (e.g. `a1EVr000003fcsPMAQ` / B-43337) will not exist in STAGE.

## Authentication

| Item | Detail |
|------|--------|
| App | External Client App **On Point Call** (`On_Point_Call`) |
| Flow | OAuth 2.0 **client_credentials** |
| Env | `SALESFORCE_CLIENT_ID`, `SALESFORCE_CLIENT_SECRET`, `SALESFORCE_INSTANCE_URL` |
| PROD run-as | `onpoint.call.api@onpointmrg.com` |
| STAGE run-as | `onpoint.call.api@onpointmrg.com.staging` |
| Permission sets | `API_On_Point_Call` (objects + field-level security) + `On_Point_Call_Qualification_API` (Apex, if calling qualification) |

Token:

```
POST {instance}/services/oauth2/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id=...&client_secret=...
```

Use a **new** token after permission-set or FLS changes (OPC caches tokens ~58 minutes in `qualification_sf_token:*`).

Verify identity:

```
GET {instance}/services/oauth2/userinfo
Authorization: Bearer <access_token>
```

## Callback sync fields (Booking__c, Type = Callback)

| OPC lead column | Booking field | SOQL notes |
|-----------------|---------------|------------|
| `venue` | `Venue__c` | Use `Venue__r.Name` for display text |
| `event` | `Event__c` | Lookup to custom **Event__c**; use `Event__r.Name` |
| `tour_location` | `Tour_Location_Name__c` | Text |
| `salesforce_booking_id` | `Id` | Upsert key |

Example (PROD booking B-43337):

```
GET /services/data/v64.0/query?q=SELECT+Id,Name,Venue__c,Venue__r.Name,Event__c,Event__r.Name+FROM+Booking__c+WHERE+Id='a1EVr000003fcsPMAQ'
Authorization: Bearer <access_token>
```

OPC builds this query in `App\Services\Salesforce\SalesforceBookingCallbackClient`.

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| `INVALID_FIELD` on `Event__c` | Often **missing Read on the referenced custom object** (`Event__c`), not just field FLS on `Booking__c.Event__c`. Lookup columns stay hidden in describe/SOQL until the integration user can read the target object. |
| `Event__c` missing from `Booking__c` describe | Same as above — describe omits lookup fields when the referenced object is not readable |
| `.../Booking__c/describe/fields/Event__c` → 404 | Can occur even when the field appears on parent describe once object access is fixed; trust parent describe + SOQL |
| `invalid_grant` / no client credentials | STAGE External Client App run-as not enabled, or wrong instance URL |
| Row not found | Wrong org (PROD Id queried against STAGE) |
| Works in Postman, fails in OPC | Wrong `SALESFORCE_INSTANCE_URL`, stale cached token, or different client id/secret in that environment |

When reporting failures, include: instance URL, `userinfo.preferred_username`, full query API JSON (`errorCode`, `message`), and API version.

## Backfill existing callbacks

After FLS / object access fixes, run:

```bash
php artisan salesforce:backfill-callback-source
php artisan salesforce:backfill-callback-source --dry-run
php artisan salesforce:backfill-callback-source --company=1
```

Updates `venue` and `event` on leads in status **callback** with a `salesforce_booking_id`. Scheduled callback import also refreshes those fields when a booking is already in OPC (without changing owner, notes, tour location, or attempt history).

## OPC code paths

- Callback import: `SalesforceBookingCallbackClient`, `BookingCallbackSyncService`
- Future/past booking checks: `SalesforceBookingClient`
- Config field map: `config/services.php` → `salesforce.booking_callbacks.fields`

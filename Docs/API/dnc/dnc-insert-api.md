# DNC Insert API (Call Center)

HTTP contract for apps (Call Center, etc.) to create **Do Not Contact** records in Salesforce (`DNC__c`).

This is **not** a custom Apex REST endpoint. Inserts use Salesforce’s standard sObject REST API. Auth is the same **On Point Call** External Client App used for [Customer Qualification](../QualificationService/qualification-api.md).

| Piece | Detail |
|-------|--------|
| Object | `DNC__c` (label: DNC) |
| Name field | Auto-number `DNC-{0000}` — do **not** send `Name` |
| API | Standard REST `POST /services/data/v64.0/sobjects/DNC__c/` |
| Required field | `DNC_Reason__c` only |
| Recommended fields | `Phone__c`, `First_Name__c`, `Last_Name__c` (see [After insert](#after-insert)) |

---

## Base URLs

| Org | Instance |
|-----|----------|
| **PROD** | `https://onpointmrg.my.salesforce.com` |
| **STAGE** | `https://onpointmrg--staging.sandbox.my.salesforce.com` |

**Create one record:**

```
POST {instance}/services/data/v64.0/sobjects/DNC__c/
```

Examples:

```
https://onpointmrg.my.salesforce.com/services/data/v64.0/sobjects/DNC__c/
https://onpointmrg--staging.sandbox.my.salesforce.com/services/data/v64.0/sobjects/DNC__c/
```

**Create up to 200 records:**

```
POST {instance}/services/data/v64.0/composite/sobjects
```

---

## Authentication

Salesforce OAuth — **not** Soft Score client id/secret.

### On Point Call (External Client App)

Use **External Client App** `On_Point_Call` with OAuth 2.0 **client credentials**.

| Item | STAGE | PROD |
|------|--------|------|
| Token / API host | `https://onpointmrg--staging.sandbox.my.salesforce.com` | `https://onpointmrg.my.salesforce.com` |
| Run-as user | `onpoint.call.api@onpointmrg.com.staging` | `onpoint.call.api@onpointmrg.com` |
| Consumer Key | `3MVG9saTlUaBnpQmcNywcwu.X3bUtyHIEoxH5KGBOazzSBqx6WFHI5rAsYdmPi3zORY04Xg8xD8CjC__n8tNd` | `3MVG9KsVczVNcM8zGM5bdMrLzZUvLKh3cRuvO5XKYn_Ywnj41bmmqHJD0HIcawW8OpeJV1E4R_HGo.gxl2Opt` |
| Consumer Secret | Setup → External Client App Manager → **On Point Call** → OAuth Settings → Reveal | Same path in that org |
| Permission sets | `API_On_Point_Call` (+ `On_Point_Call_Qualification_API` for qualification) | Same |

Token:

```
POST {instance}/services/oauth2/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=<Consumer Key for that org>
&client_secret=<CONSUMER_SECRET_FROM_SETUP>
```

Then call the insert with:

| Header | Value |
|--------|--------|
| `Authorization` | `Bearer <access_token>` |
| `Content-Type` | `application/json` |

> Soft Score keys (`OP_Soft_Score_Credential__c`) do **not** authorize this endpoint.

### Permissions required

The integration user must have:

- Object: **Create** (and typically Read) on `DNC__c`
- Field-level security: **Edit** on every field sent in the JSON body

As of this writing, `API_On_Point_Call` does **not** include `DNC__c` create. Inserts will fail with `INSUFFICIENT_ACCESS` until that is granted in Salesforce.

---

## Request — single insert

```json
{
  "DNC_Reason__c": "Customer Requested",
  "Phone__c": "6025550100",
  "First_Name__c": "Jane",
  "Last_Name__c": "Doe",
  "Email__c": "jane@example.com",
  "Request_Source__c": "Phone",
  "Request_Notes__c": "Call Center DNC request",
  "Requested_Date__c": "2026-08-20"
}
```

Recommended Call Center payload: **reason + phone + first name + last name**. Include `Email__c` when you have it.

### Success (HTTP 201)

```json
{
  "id": "a0Nxxxxxxxxxxxx",
  "success": true,
  "errors": []
}
```

`id` is the new `DNC__c` record Id.

### Common errors

| HTTP | Salesforce status | Typical cause |
|------|-------------------|---------------|
| 401 | `INVALID_SESSION_ID` | Missing/expired Bearer token |
| 400 | `REQUIRED_FIELD_MISSING` | `DNC_Reason__c` omitted |
| 400 | `INVALID_OR_NULL_FOR_RESTRICTED_PICKLIST` | Reason or source value not in the lists below |
| 403 | `INSUFFICIENT_ACCESS` | API user cannot create `DNC__c` or lacks FLS |

---

## Request — bulk insert

```json
{
  "allOrNone": false,
  "records": [
    {
      "attributes": { "type": "DNC__c" },
      "DNC_Reason__c": "Customer Requested",
      "Phone__c": "6025550100",
      "First_Name__c": "Jane",
      "Last_Name__c": "Doe",
      "Request_Source__c": "Phone"
    }
  ]
}
```

`allOrNone: false` inserts valid rows even if others fail. Max **200** records per request.

---

## Fields to send

| API name | Type | Required | Notes |
|----------|------|----------|-------|
| `DNC_Reason__c` | Restricted picklist | **Yes** | Exact values below |
| `Phone__c` | Phone | Strongly recommended | Needed for internal DNC push and Lead match |
| `First_Name__c` | Text (40) | Recommended | Automation needs Phone **and** First or Last |
| `Last_Name__c` | Text (80) | Recommended | Same |
| `Email__c` | Email | Optional | Used to match a Lead if phone is not unique |
| `Request_Source__c` | Restricted picklist | Optional | Closest existing value for Call Center: `Phone` |
| `Request_Notes__c` | Text (255) | Optional | Short note from the agent |
| `Requested_Date__c` | Date (`YYYY-MM-DD`) | Optional | Date of the request |
| `Notes__c` | Long text (1000) | Optional | Longer notes |
| `Employer__c` | Text (30) | Optional | |
| `Title__c` | Text (100) | Optional | |
| `Lead__c` | Lead Id | Optional | Leave blank; Salesforce will try to match |
| `Company__c` | Account Id | Optional | Skip unless you have the Account Id |
| `Requested_By__c` | Employee__c Id | Optional | Skip unless you have that Id |
| `Event__c` | Event__c Id | Optional | Skip unless you have that Id |
| `Venue__c` | Venue__c Id | Optional | Skip unless you have that Id |

### `DNC_Reason__c` (restricted — exact API values)

- `Customer Requested`
- `Management Requested`
- `Reassigned Number`
- `Litigator`
- `NQ Tour`
- `Partner Employee`
- `SMS STOP Message`

### `Request_Source__c` (restricted — exact API values)

- `Email`
- `Phone`
- `Website`
- `List`

There is no `Call Center` value. Use `Phone`, or ask Salesforce to add `Call Center` to the picklist.

### Do not send (internal process checkboxes)

Salesforce owns these after insert:

- `Lead_Marked_as_Internal_DNC__c`
- `Number_added_to_Dialer_DNC_List__c`
- `Number_added_to_DNC_com__c`
- `Lead_Info_Anonymized__c`
- `Survey_Response_Info_Anonymized__c`
- `Response_Sent_To_Client__c`

---

## After insert

If **Phone is set** and **First Name or Last Name is set**, flow **DNC - Send to Internal DNC** runs:

1. Tries to match a Lead (phone, then email, then first + last).
2. If a Lead is found, sets `Lead.Suppress_IDNC__c = true`.
3. Queues a push of the number to internal DNC (dncscrub.com EBR).

If you only send reason + phone and skip both names, the row still inserts, but that automation **does not run**.

---

## curl sketch

```bash
# 1) Salesforce token
TOKEN=$(curl -s -X POST \
  'https://onpointmrg.my.salesforce.com/services/oauth2/token' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=client_credentials&client_id=SF_CONNECTED_APP_ID&client_secret=SF_CONNECTED_APP_SECRET' \
  | jq -r .access_token)

# 2) Insert DNC
curl -s -X POST \
  'https://onpointmrg.my.salesforce.com/services/data/v64.0/sobjects/DNC__c/' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "DNC_Reason__c": "Customer Requested",
    "Phone__c": "6025550100",
    "First_Name__c": "Jane",
    "Last_Name__c": "Doe",
    "Email__c": "jane@example.com",
    "Request_Source__c": "Phone",
    "Request_Notes__c": "Call Center DNC request",
    "Requested_Date__c": "2026-08-20"
  }'
```

Use the STAGE host and consumer key when testing against sandbox.

---

## Related metadata

| Item | Path |
|------|------|
| Object | `force-app/main/default/objects/DNC__c/` |
| Internal DNC flow | `force-app/main/default/flows/DNC_Send_To_EBR.flow-meta.xml` |
| Lead match | `force-app/main/default/classes/DNCLeadResolver.cls` |
| Qualification API (same auth) | `docs/api/QualificationService/qualification-api.md` |

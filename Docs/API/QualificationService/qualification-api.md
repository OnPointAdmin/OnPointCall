# Customer Qualification API (Call Center)

HTTP contract for apps (Call Center, Formyoula, etc.) to run OnPoint **partner qualification**.

This is **not** an AWS API. Soft Score lives at `prod.onpointapi.com`; qualification runs **inside Salesforce** against Qualifiable Companies / Combinations / Criteria.

| Piece | Detail |
|-------|--------|
| Apex REST | `CustomerQualificationService` |
| URL mapping | `/CustomerQualification` |
| Engine | `OPQualificationService.evaluate` (no DML, no outbound HTTP) |
| Soft Score | Separate call (`docs/api/SoftScore/soft-score-api.md`). Pass resulting `qualificationCode` into `customerData` when criteria need it. |

---

## Base URLs

| Org | Instance (Apex REST) |
|-----|----------------------|
| **PROD** | `https://onpointmrg.my.salesforce.com` |
| **STAGE** | `https://onpointmrg--staging.sandbox.my.salesforce.com` |

**Endpoint:**

```
POST {instance}/services/apexrest/CustomerQualification
```

Examples:

```
https://onpointmrg.my.salesforce.com/services/apexrest/CustomerQualification
https://onpointmrg--staging.sandbox.my.salesforce.com/services/apexrest/CustomerQualification
```

---

## Authentication

Salesforce OAuth — **not** Soft Score client id/secret.

### STAGE — On Point Call (ready)

| Item | Value |
|------|--------|
| App type | **External Client App** `On_Point_Call` (legacy Connected App creation is blocked in this org) |
| Flow | OAuth 2.0 **client credentials** |
| Run-as user | `onpoint.call.api@onpointmrg.com.staging` (Salesforce Integration license) |
| Consumer Key (client_id) | From Setup (do not commit) — **Setup → Apps → External Client Apps → External Client App Manager → On Point Call → OAuth Settings → Consumer Key** |
| Consumer Secret | Setup only — not returned by Metadata API. **Setup → Apps → External Client Apps → External Client App Manager → On Point Call → OAuth Settings → Consumer Secret** (Reveal / Copy). Store in env, never in git. |
| Permission sets | `API_On_Point_Call` (objects) + `On_Point_Call_Qualification_API` (Apex classes) |

Token:

```
POST https://onpointmrg--staging.sandbox.my.salesforce.com/services/oauth2/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=<CONSUMER_KEY>
&client_secret=<CONSUMER_SECRET>
```

Then call qualification with:

| Header | Value |
|--------|--------|
| `Authorization` | `Bearer <access_token>` |
| `Content-Type` | `application/json` |

> Soft Score keys (`OP_Soft_Score_Credential__c`) do **not** authorize this endpoint.

### PROD

Not provisioned yet. Mirror STAGE: create External Client App `On_Point_Call`, Integration user, assign both permission sets, enable client credentials run-as that user.

---

## Request

**POST** JSON body:

```json
{
  "surveyCompanyId": "001xxxxxxxxxxxx",
  "venueId": "a0Xxxxxxxxxxxxx",
  "customerData": {
    "lastName": "Doe",
    "gender": "Female",
    "age": "35-44",
    "marital": "Married",
    "income": "$75,000 - $99,999",
    "card": "Good",
    "country": "United States",
    "employment": "Employed",
    "zipCode": "30303",
    "homeOwner": "Own",
    "stayType": "Hotel",
    "scheduled": "Yes",
    "qualificationCode": "A"
  }
}
```

### Top-level fields

| Field | Required | Description |
|-------|----------|-------------|
| `surveyCompanyId` | **Yes** | Salesforce Account Id of the **survey company** (seller / host). OPS uses `Survey__c.Account__c`. |
| `venueId` | No | Venue Id. When present: use junctions where `Venue__c` is blank **or** matches, preferring venue-specific rows over generic for the same buyer. When omitted: only generic (blank Venue) junctions. |
| `customerData` | **Yes** | Map of criterion field names → guest answers. Missing/`null` values are treated as `""`. If `country` is missing/blank, the service defaults it to `"United States"`. |

### Common `customerData` keys (OPS / Formyoula parity)

These are the keys qualification **criteria** `Field_Name__c` values typically use. Exact keys/values depend on the Company Qualification config in the org.

| Survey answer field | `customerData` key |
|---------------------|--------------------|
| `Last_Name__c` | `lastName` |
| `Gender__c` | `gender` |
| `Age_Range__c` | `age` |
| `Marital_Status__c` | `marital` |
| `Income__c` | `income` |
| `Credit_Range__c` | `card` |
| `Country__c` | `country` |
| `Employment_Status__c` | `employment` |
| `Zip_Code__c` | `zipCode` |
| `HomeOwner_or_Renter__c` | `homeOwner` |
| `Stay_Type__c` | `stayType` |
| `Scheduled_a_Presentation__c` | `scheduled` |
| Soft Score result | `qualificationCode` |

Include any other key that appears as `Qualification_Criteria__c.Field_Name__c` for the companies under that survey company (e.g. custom Formyoula fields).

### Soft Score + qualification

1. Call Soft Score (`POST …/marketing/v1/leads/softscore`) if needed.
2. Put `lead.creditScore[0].creditBand.qualificationCode` into `customerData.qualificationCode`.
3. Call this endpoint.

If Soft Score is off / skipped, omit `qualificationCode` or send `""` — criteria that require a code will fail as configured.

---

## Response

```json
{
  "qualifiedCompaniesLead": [
    {
      "companyId": "001xxxxxxxxxxxx",
      "companyName": "Partner Display Name",
      "vertical": "Travel",
      "priority": "1",
      "qualificationCombination": "Combination Name"
    }
  ],
  "qualifiedCompaniesBooking": [],
  "failedCriteria": {
    "Other Partner Name": {
      "combinationName": "Closest Combination",
      "failedCriteria": ["Age Criteria", "Income Criteria"]
    }
  },
  "errorMessage": null
}
```

| Field | Meaning |
|-------|---------|
| `qualifiedCompaniesLead` | Partners that passed, routed as Lead (or Both). Sorted by `priority` ascending. |
| `qualifiedCompaniesBooking` | Partners that passed, routed as Booking (or Both). Sorted by `priority` ascending. |
| `failedCriteria` | Per **Account Name** of companies that did not qualify: closest combination + failed criterion names. |
| `errorMessage` | Set on hard failures (missing params, no active qualifiable companies, unexpected exception). `null` / omitted on success. |

`companyName` prefers Account `Opt_In_Display_Name__c`, else `Name`.

### Error examples

| Situation | Typical `errorMessage` |
|-----------|-------------------------|
| Missing `surveyCompanyId` or `customerData` | `Missing required parameters: surveyCompanyId or customerData.` |
| No active junctions for that survey company (after venue filter) | `No active qualifiable companies found for the given survey company.` |
| Thrown exception | `An error occurred: …` |

Empty `qualifiedCompaniesLead` / `qualifiedCompaniesBooking` with null `errorMessage` means evaluation ran but no partner matched (see `failedCriteria`).

---

## How evaluation works (summary)

1. Load active `Qualifiable_Companies__c` for `surveyCompanyId` (and venue rules above).
2. For each buyer company, test active `Qualification_Combination__c` rows until one passes all criteria.
3. Criteria: `in list` / `not in list` against comma lists, or Static Resource name lists (case-insensitive).
4. Route winners to Lead and/or Booking lists from `Company_Qualification__c.Lead_or_Booking__c` (`Lead`, `Booking`, `Both`).

Admin-owned config — Call Center does not hardcode partner rules; it only supplies `surveyCompanyId`, optional `venueId`, and `customerData`.

---

## curl sketch

```bash
# 1) Salesforce token (Connected App — replace credentials)
TOKEN=$(curl -s -X POST \
  'https://onpointmrg.my.salesforce.com/services/oauth2/token' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=client_credentials&client_id=SF_CONNECTED_APP_ID&client_secret=SF_CONNECTED_APP_SECRET' \
  | jq -r .access_token)

# 2) Qualify
curl -s -X POST \
  'https://onpointmrg.my.salesforce.com/services/apexrest/CustomerQualification' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "surveyCompanyId": "001xxxxxxxxxxxx",
    "venueId": "a0Xxxxxxxxxxxxx",
    "customerData": {
      "age": "35-44",
      "income": "$75,000 - $99,999",
      "country": "United States",
      "zipCode": "30303",
      "homeOwner": "Own",
      "qualificationCode": "A"
    }
  }'
```

---

## Recommended Call Center flow

```
Guest answers
    │
    ├─(optional) Soft Score API  → qualificationCode
    │
    └─ POST /services/apexrest/CustomerQualification
           surveyCompanyId + venueId? + customerData (+ qualificationCode)
               │
               ├─ qualifiedCompaniesBooking / Lead  → partners / auto-tour
               └─ failedCriteria / empty lists      → not qualified path
```

Do **not** call Soft Score with Salesforce Connected App credentials, and do **not** call Qualification with Soft Score OAuth credentials.

---

## Salesforce reference

| Piece | Location |
|-------|----------|
| REST wrapper | `force-app/main/default/classes/CustomerQualificationService.cls` |
| Engine | `force-app/main/default/classes/OPQualificationService.cls` |
| Contract tests | `force-app/main/default/classes/CustomerQualificationServiceTest.cls` |
| OPS guest path (same engine, not this URL) | `OPSurveyController.runQualify` |
| Soft Score HTTP | `docs/api/SoftScore/soft-score-api.md` |

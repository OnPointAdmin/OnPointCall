# Soft Score API

How external apps (and Salesforce OPS) call the OnPoint Soft Score API at `prod.onpointapi.com`.

Salesforce implementation: `OPSoftScoreService` → Named Credential `OP_Soft_Score`.

---

## Base URL

```
https://prod.onpointapi.com
```

| Salesforce artifact | Value |
|---------------------|--------|
| Named Credential | `OP_Soft_Score` |
| Remote Site | `OnPoint_API` |
| Timeout (Apex) | 15 seconds |

---

## Authentication

OAuth 2.0 **client credentials**.

### Token request

**POST** `/oauth/v2/accesstoken?grant_type=client_credentials`

| Header | Value |
|--------|--------|
| `Content-Type` | `application/x-www-form-urlencoded` |

**Body:**

```
client_id=<CLIENT_ID>&client_secret=<CLIENT_SECRET>
```

**Success response (shape):**

```json
{
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### Credentials

| Item | Value / location |
|------|------------------|
| **Client Id** | `fvs54jb1QO6NTkTMhREYE5Fa1mNNnvoBPvDKkMUdAP5lKuse` (same on STAGE and PROD) |
| **Client Secret** | Salesforce Setup → Custom Settings → **OP Soft Score Credential** (`OP_Soft_Score_Credential__c` org defaults). Not stored in git. |

---

## Soft score endpoint

**POST** `/marketing/v1/leads/softscore`

| Header | Value |
|--------|--------|
| `Authorization` | `Bearer <access_token>` |
| `Content-Type` | `application/json` |
| `X-ORIGINATOR-APPLICATION` | `KALEO` |

### Request body

```json
{
  "leadRequest": {
    "firstName": "Jane",
    "lastName": "Doe",
    "homePhone": "4045551212",
    "primaryEmail": "jane@example.com",
    "addressLine1": "100 Main St",
    "city": "Atlanta",
    "state": "GA",
    "postalCode": "30303",
    "address": {
      "addressLine1": "100 Main St",
      "city": "Atlanta",
      "state": "GA",
      "country": "USA",
      "postalCode": "30303"
    },
    "ownerFlag": "N",
    "creditScore": [
      {
        "softScoreLetterPrintInd": "N",
        "softScoreLetterSendInd": "N"
      }
    ]
  }
}
```

### Field preparation (OPS conventions)

| Field | Notes |
|-------|--------|
| `homePhone` | Digits only. If 11 digits starting with `1`, drop the leading `1` (10-digit US). |
| `postalCode` | Digits only; use first 5. |
| `state` | USPS 2-letter code. |
| `country` | Always `USA` in this payload. |
| `ownerFlag` | `"N"` |
| Letter indicators | Both `"N"` |

---

## Response

HTTP **2xx** on success. Qualification code path used by OPS:

```
lead.creditScore[0].creditBand.qualificationCode
```

**Example:**

```json
{
  "lead": {
    "creditScore": [
      {
        "creditBand": {
          "qualificationCode": "A"
        }
      }
    ]
  }
}
```

OPS treats a successful call **and** a non-blank `qualificationCode` as soft-score qualified. Missing code or call failure → not-qualified / error path.

---

## curl examples

```bash
# 1) Token
TOKEN=$(curl -s -X POST \
  'https://prod.onpointapi.com/oauth/v2/accesstoken?grant_type=client_credentials' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'client_id=fvs54jb1QO6NTkTMhREYE5Fa1mNNnvoBPvDKkMUdAP5lKuse&client_secret=YOUR_CLIENT_SECRET' \
  | jq -r .access_token)

# 2) Soft score
curl -s -X POST 'https://prod.onpointapi.com/marketing/v1/leads/softscore' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'X-ORIGINATOR-APPLICATION: KALEO' \
  -d '{
    "leadRequest": {
      "firstName": "Jane",
      "lastName": "Doe",
      "homePhone": "4045551212",
      "primaryEmail": "jane@example.com",
      "addressLine1": "100 Main St",
      "city": "Atlanta",
      "state": "GA",
      "postalCode": "30303",
      "address": {
        "addressLine1": "100 Main St",
        "city": "Atlanta",
        "state": "GA",
        "country": "USA",
        "postalCode": "30303"
      },
      "ownerFlag": "N",
      "creditScore": [
        {
          "softScoreLetterPrintInd": "N",
          "softScoreLetterSendInd": "N"
        }
      ]
    }
  }'
```

---

## Salesforce reference

| Piece | Location |
|-------|----------|
| Callout service | `force-app/main/default/classes/OPSoftScoreService.cls` |
| Named Credential | `force-app/main/default/namedCredentials/OP_Soft_Score.namedCredential-meta.xml` |
| Credential setting | `OP_Soft_Score_Credential__c` (`Client_Id__c`, `Client_Secret__c`) |
| OPS guest entry | `OPSurveyController.runSoftScore` / submit pipeline |

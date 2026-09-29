---
name: Callback report fields
overview: Pull all Callback Report columns into first-class lead columns and show them in the same Booking section used for TNB leads (agent workspace + admin lead view).
todos:
  - id: validate-sf-fields
    content: Describe Booking__c and confirm API names + FLS for Tour_Location__c, Premium_Combined_2__c, Deposit_Amount__c, Deposit_Type__c, CreatedDate
    status: pending
  - id: migration-new-columns
    content: Add leads.booking_number, deposit_amount, deposit_type; backfill booking_number from booking_id
    status: pending
  - id: extend-soql-config
    content: Add new SF fields to config/services.php booking_callbacks.fields and SalesforceBookingCallbackClient SOQL/parse
    status: pending
  - id: map-to-lead
    content: Map report fields in BookingCallbackSyncService; set created_at from SF CreatedDate on create only
    status: pending
  - id: unified-booking-ui
    content: Refactor agent lead-panel and admin LeadInfolist to one shared Booking section (TNB + callback/SF leads)
    status: pending
  - id: tests
    content: BookingCallbackSyncTest for new columns, created_at, SOQL; agent/infolist visibility tests if needed
    status: pending
  - id: docs-changelog
    content: Update plans/booking-callbacks-dialer.md and CHANGELOG when committing
    status: pending
isProject: false
---

# Callback Report field parity

## Goal

Callback-imported leads should show the **same Booking section** on the lead layout as TNB leads — not a one-off extra block. One shared set of booking columns and one shared UI section for any lead with booking data.

## Report → database mapping

| Report column | SF source | Lead column | Notes |
|---|---|---|---|
| Booking Number | `Booking__c.Name` | **`booking_number`** (new) | e.g. `B-43314` |
| Booking Id | `Booking__c.Id` | **`salesforce_booking_id`** (exists) | 18-char `a1E…`; upsert key |
| Booking Notes | `Booking_Notes__c` | `notes` | already mapped |
| Entry Created Date | `CreatedDate` | **`created_at`** on create only | same as lead created date in OPC |
| First / Last Name | `First_Name__c`, `Last_Name__c` | `first_name`, `last_name` | already mapped |
| First / Last Name 2 | `First_Name_2__c`, `Last_Name_2__c` | **`first_name_2`, `last_name_2`** (exist) | already mapped |
| Phone / Email | phone + email fields | `phone`, `email` | already mapped |
| Call Back Date / Time | callback fields | `callback_at` | already mapped (+ AM/PM fix) |
| Tour Location | `Tour_Location__c` | `tour_location` | column exists; wire sync |
| Premium Combined 2 | `Premium_Combined_2__c` | `premiums` | column exists; wire sync |
| Deposit Amount | `Deposit_Amount__c` | **`deposit_amount`** (new) | decimal |
| Deposit Type | `Deposit_Type__c` | **`deposit_type`** (new) | string |
| Representative Name | `Representative__r.Name` | — | assignment only (`callback_owner_id`); not stored on lead |
| Representative Email | — | — | **skip** |

**No `extra_fields` for this work.**

### `booking_id` legacy column

Today `leads.booking_id` holds the human `B-…` number (mislabeled “Booking ID” in the Tour section). Plan:

- **`booking_number`** becomes the canonical display field for `B-…`.
- **`salesforce_booking_id`** is the canonical field for `Booking__c.Id`.
- Keep writing **`booking_id`** with the same `B-…` value on callback sync for backward compatibility (CSV import, booking URL builder, call detail report) until a later cleanup.
- Backfill `booking_number` from `booking_id` for existing rows.

## Unified Booking section (UI)

Today TNB leads get a **Tour / TNB** block in [`lead-panel.blade.php`](resources/views/livewire/agent/partials/lead-panel.blade.php) and a **Tour** section in [`LeadInfolist.php`](app/Filament/Resources/Leads/Schemas/LeadInfolist.php). Callback leads only see it if tour/booking columns happen to be filled.

**Refactor to one shared “Booking” section** for both `lead_type = tnb` and callback/SF-booking leads:

| Label | Column |
|---|---|
| Booking Number | `booking_number` (fallback `booking_id` for older TNB rows until backfill) |
| Booking Id | `salesforce_booking_id` |
| Tour location | `tour_location` |
| Tour date start | `tour_date_start` |
| Tour date | `tour_date` |
| Premiums | `premiums` |
| Tour result | `tour_result` |
| Tour / no show | `tour_or_no_show` |
| Deposit amount | `deposit_amount` |
| Deposit type | `deposit_type` |

**Visibility:** show section when any booking column is filled, **or** `lead_type === 'tnb'` (TNB always shows section, same as today), **or** `salesforce_booking_id` is set (SF callback import).

Rename section heading from **Tour / TNB** → **Booking** in agent workspace; admin **Tour** section → **Booking** (same fields).

TNB-only `extra_fields` merged into this section can stay as today for TNB rows only.

## Salesforce SOQL additions

Validate + FLS, then add to [`config/services.php`](config/services.php) and [`SalesforceBookingCallbackClient`](app/Services/Salesforce/SalesforceBookingCallbackClient.php):

- `CreatedDate`
- `Tour_Location__c`
- `Premium_Combined_2__c`
- `Deposit_Amount__c`
- `Deposit_Type__c`

## Migration

```php
$table->string('booking_number')->nullable()->after('booking_id');
$table->decimal('deposit_amount', 10, 2)->nullable();
$table->string('deposit_type')->nullable();
```

Backfill: `booking_number = booking_id` where null.

## Sync ([`BookingCallbackSyncService`](app/Services/Leads/BookingCallbackSyncService.php))

On each upsert:

- `booking_number` ← SF `Name`
- `salesforce_booking_id` ← SF `Id`
- `booking_id` ← SF `Name` (compat)
- `tour_location`, `premiums`, `deposit_amount`, `deposit_type` ← new SF fields
- On **create only**: `created_at` ← SF `CreatedDate` (UTC)

## Scope

Keep broader callback import (New / Rescheduled / Agent Callback; overdue + dateless).

## Tests

- Callback sync populates `booking_number`, `salesforce_booking_id`, deposits, tour/premium, `created_at` on create
- Re-sync does not overwrite `created_at`
- Agent panel / admin infolist show **Booking** section when `salesforce_booking_id` present

## After deploy

Re-run **Import Agent Callbacks** to backfill new columns on existing callback leads.

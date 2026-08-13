---
name: Failed Lead Retry
overview: Add batch-level Soft Score/RND retry (A) and conditional reimport that updates/requeues only check-failed Holding leads (B), including correct batch counter adjustments on retry.
todos:
  - id: fix-retry-counters
    content: Fix Soft Score/RND batch counters when retrying from Error (and other completed statuses)
    status: completed
  - id: batch-retry-actions
    content: Add Retry failed Soft Score / RND actions on import batch view
    status: completed
  - id: conditional-reimport
    content: Upsert recoverable failed Holding matches on reimport; track updated_count
    status: completed
  - id: leads-rnd-rerun
    content: Add Leads bulk Re-run RND alongside Soft Score
    status: completed
  - id: tests
    content: Cover counter fix, batch retry, and conditional reimport cases
    status: completed
isProject: false
---

# Failed lead recovery (A + B) — implemented

## What shipped

### A — Batch retry
- Import batch view header actions: **Retry Soft Score errors** / **Retry RND errors**
- Leads bulk action: **Re-run RND** (alongside Soft Score)
- Soft Score / RND services move completed counters → pending on retry so health dots stay accurate

### B — Conditional reimport
- Holding leads with Soft Score or RND **Error** are updated (mapped fields), moved to the new batch, and failed checks re-queued when those toggles are on
- Healthy duplicates and Terminal/RND-reassigned leads stay ignored
- `import_batches.updated_count` tracks recoveries

## Key files
- [`app/Services/Import/LeadImportService.php`](../app/Services/Import/LeadImportService.php)
- [`app/Services/Import/ImportBatchCheckRetryService.php`](../app/Services/Import/ImportBatchCheckRetryService.php)
- [`app/Services/SoftScore/SoftScoreService.php`](../app/Services/SoftScore/SoftScoreService.php)
- [`app/Services/Rnd/RndService.php`](../app/Services/Rnd/RndService.php)
- [`tests/Feature/FailedLeadRetryTest.php`](../tests/Feature/FailedLeadRetryTest.php)

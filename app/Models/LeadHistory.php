<?php

namespace App\Models;

use App\Enums\Disposition;
use App\Enums\LeadHistoryType;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\LeadDisplayFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class LeadHistory extends Model
{
    use BelongsToCompany;

    protected $table = 'lead_history';

    protected $fillable = [
        'company_id',
        'lead_id',
        'actor_id',
        'event_type',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => LeadHistoryType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @param  Builder<LeadHistory>  $query
     * @return Builder<LeadHistory>
     */
    public function scopeVisibleInCallHistory(Builder $query): Builder
    {
        return $query->whereNotIn('event_type', [
            LeadHistoryType::Claim->value,
            LeadHistoryType::ClaimExpire->value,
            LeadHistoryType::Release->value,
        ]);
    }

    public function detailLabel(): string
    {
        $payload = $this->payload ?? [];

        return match ($this->event_type) {
            LeadHistoryType::Disposition, LeadHistoryType::Skip => $this->formatDispositionDetails($payload),
            LeadHistoryType::StatusChange => $this->formatStatusChangeDetails($payload),
            LeadHistoryType::Release => isset($payload['calling_list_name'])
                ? 'Released to '.$payload['calling_list_name']
                : (isset($payload['calling_list_id'])
                    ? 'Released to list #'.$payload['calling_list_id']
                    : '—'),
            LeadHistoryType::Merge => isset($payload['merged_phone'])
                ? 'Merged '.$payload['merged_phone'].' (#'.$payload['merged_lead_id'].')'
                : '—',
            LeadHistoryType::SoftScore => $this->formatSoftScoreDetails($payload),
            LeadHistoryType::RndCheck => $this->formatRndDetails($payload),
            LeadHistoryType::Qualification => $this->formatQualificationDetails($payload),
            LeadHistoryType::FieldEdit => $this->formatFieldEditDetails($payload),
            LeadHistoryType::DncPush => $this->formatDncPushDetails($payload),
            LeadHistoryType::Claim => isset($payload['source'])
                ? 'Source: '.$payload['source']
                : '—',
            LeadHistoryType::ClaimExpire => ($payload['released'] ?? false)
                ? 'Released'
                : (isset($payload['user_id'])
                    ? 'Expired (user #'.$payload['user_id'].')'
                    : 'Expired'),
            LeadHistoryType::Recycle => 'Attempt count reset',
            default => '—',
        };
    }

    public function noteLabel(): ?string
    {
        $payload = $this->payload ?? [];
        $parts = [];

        foreach (['reason', 'skip_reason', 'note'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return $parts !== [] ? implode(' — ', array_unique($parts)) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function mergeDncPushPayload(Lead $lead, array $payload, ?int $actorId = null): self
    {
        return DB::transaction(function () use ($lead, $payload, $actorId): self {
            $existing = self::withoutGlobalScopes()
                ->where('lead_id', $lead->id)
                ->where('event_type', LeadHistoryType::DncPush)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $merged = array_merge($existing?->payload ?? [], $payload);

            foreach ($payload as $key => $value) {
                if ($value === null) {
                    unset($merged[$key]);
                }
            }

            if ($existing) {
                $existing->update([
                    'payload' => $merged,
                ]);

                return $existing->fresh() ?? $existing;
            }

            return self::withoutGlobalScopes()->create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'actor_id' => $actorId,
                'event_type' => LeadHistoryType::DncPush,
                'occurred_at' => now(),
                'payload' => $merged,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatDispositionDetails(array $payload): string
    {
        $parts = [];

        if (isset($payload['disposition']) && is_string($payload['disposition'])) {
            $parts[] = Disposition::tryFrom($payload['disposition'])?->label() ?? $payload['disposition'];
        }

        if (isset($payload['reason']) && is_string($payload['reason']) && $payload['reason'] !== '') {
            $parts[] = $payload['reason'];
        }

        if (isset($payload['callback_at']) && is_string($payload['callback_at'])) {
            $parts[] = 'Callback: '.$payload['callback_at'];
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatStatusChangeDetails(array $payload): string
    {
        $from = isset($payload['from']) && is_string($payload['from'])
            ? (LeadStatus::tryFrom($payload['from'])?->label() ?? $payload['from'])
            : null;
        $to = isset($payload['to']) && is_string($payload['to'])
            ? (LeadStatus::tryFrom($payload['to'])?->label() ?? $payload['to'])
            : null;

        $change = match (true) {
            $from !== null && $to !== null => "{$from} → {$to}",
            $to !== null => "→ {$to}",
            $from !== null => "{$from} →",
            default => null,
        };

        if ($change === null) {
            return '—';
        }

        if (isset($payload['reason']) && is_string($payload['reason'])) {
            return $change.' ('.$payload['reason'].')';
        }

        return $change;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatSoftScoreDetails(array $payload): string
    {
        $parts = [];

        if (isset($payload['status']) && is_string($payload['status'])) {
            $parts[] = SoftScoreStatus::tryFrom($payload['status'])?->label() ?? $payload['status'];
        }

        if (isset($payload['qualification_code']) && is_string($payload['qualification_code']) && $payload['qualification_code'] !== '') {
            $parts[] = 'Code: '.$payload['qualification_code'];
        }

        if (isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== '') {
            $parts[] = 'Error: '.$payload['error'];
        }

        if (($payload['skipped'] ?? false) && isset($payload['reason']) && is_string($payload['reason'])) {
            $parts[] = 'Skipped: '.$payload['reason'];
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatRndDetails(array $payload): string
    {
        $parts = [];

        if (isset($payload['status']) && is_string($payload['status'])) {
            $parts[] = RndStatus::tryFrom($payload['status'])?->label() ?? $payload['status'];
        }

        if (isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== '') {
            $parts[] = 'Error: '.$payload['error'];
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatQualificationDetails(array $payload): string
    {
        $parts = [];

        if (isset($payload['status']) && is_string($payload['status'])) {
            $parts[] = QualificationStatus::tryFrom($payload['status'])?->label() ?? $payload['status'];
        }

        if (isset($payload['qualified_partners']) && is_array($payload['qualified_partners']) && $payload['qualified_partners'] !== []) {
            $parts[] = 'Partners: '.implode(', ', $payload['qualified_partners']);
        }

        if (isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== '') {
            $parts[] = 'Error: '.$payload['error'];
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatFieldEditDetails(array $payload): string
    {
        $changes = $payload['changes'] ?? null;

        if (! is_array($changes) || $changes === []) {
            return '—';
        }

        $parts = [];

        foreach ($changes as $field => $diff) {
            if (! is_array($diff)) {
                continue;
            }

            $label = LeadDisplayFields::labelFor((string) $field);
            $from = $this->formatEditValue($diff['from'] ?? null);
            $to = $this->formatEditValue($diff['to'] ?? null);
            $parts[] = $label.': '.$from.' → '.$to;
        }

        return $parts !== [] ? implode('; ', $parts) : '—';
    }

    private function formatEditValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatDncPushDetails(array $payload): string
    {
        $parts = [];

        if (isset($payload['phones']) && is_array($payload['phones']) && $payload['phones'] !== []) {
            $parts[] = 'DNC.com: '.implode(', ', array_map(strval(...), $payload['phones']));
        }

        if (isset($payload['salesforce_id']) && is_string($payload['salesforce_id']) && $payload['salesforce_id'] !== '') {
            $parts[] = 'Salesforce: '.$payload['salesforce_id'];
        }

        if (isset($payload['salesforce_error']) && is_string($payload['salesforce_error']) && $payload['salesforce_error'] !== '') {
            $parts[] = 'Salesforce error: '.$payload['salesforce_error'];
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use App\Enums\ListAssignmentAction;
use App\Models\Concerns\BelongsToCompany;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListAssignmentHistory extends Model
{
    use BelongsToCompany;

    protected $table = 'list_assignment_history';

    protected $fillable = [
        'company_id',
        'calling_list_id',
        'user_id',
        'user_name',
        'action',
        'actor_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ListAssignmentAction::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function callingList(): BelongsTo
    {
        return $this->belongsTo(CallingList::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function recordEvent(
        ListAssignment $assignment,
        ListAssignmentAction $action,
        ?string $userName = null,
        ?DateTimeInterface $occurredAt = null,
    ): self {
        $userName ??= User::withoutGlobalScopes()
            ->whereKey($assignment->user_id)
            ->value('name') ?? 'Unknown';

        return static::withoutGlobalScopes()->create([
            'company_id' => $assignment->company_id,
            'calling_list_id' => $assignment->calling_list_id,
            'user_id' => $assignment->user_id,
            'user_name' => $userName,
            'action' => $action,
            'actor_id' => auth()->id(),
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}

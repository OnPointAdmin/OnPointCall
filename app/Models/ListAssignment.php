<?php

namespace App\Models;

use App\Enums\ListAssignmentAction;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListAssignment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'calling_list_id',
    ];

    protected static function booted(): void
    {
        static::created(function (ListAssignment $assignment): void {
            ListAssignmentHistory::recordEvent($assignment, ListAssignmentAction::Assigned);
        });

        static::deleted(function (ListAssignment $assignment): void {
            ListAssignmentHistory::recordEvent($assignment, ListAssignmentAction::Unassigned);
        });

        static::updated(function (ListAssignment $assignment): void {
            if (! $assignment->wasChanged(['user_id', 'calling_list_id'])) {
                return;
            }

            $previous = $assignment->newInstance([
                'company_id' => $assignment->company_id,
                'user_id' => $assignment->getOriginal('user_id'),
                'calling_list_id' => $assignment->getOriginal('calling_list_id'),
            ], true);

            ListAssignmentHistory::recordEvent($previous, ListAssignmentAction::Unassigned);
            ListAssignmentHistory::recordEvent($assignment, ListAssignmentAction::Assigned);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callingList(): BelongsTo
    {
        return $this->belongsTo(CallingList::class);
    }
}

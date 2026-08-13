<?php

namespace App\Enums;

enum LeadHistoryType: string
{
    case Attempt = 'attempt';
    case Disposition = 'disposition';
    case Skip = 'skip';
    case Assign = 'assign';
    case Release = 'release';
    case Recycle = 'recycle';
    case Merge = 'merge';
    case Claim = 'claim';
    case ClaimExpire = 'claim_expire';
    case StatusChange = 'status_change';
    case SoftScore = 'soft_score';
    case RndCheck = 'rnd_check';

    public function label(): string
    {
        return match ($this) {
            self::Attempt => 'Attempt',
            self::Disposition => 'Disposition',
            self::Skip => 'Skip',
            self::Assign => 'Assign',
            self::Release => 'Release',
            self::Recycle => 'Recycle',
            self::Merge => 'Merge',
            self::Claim => 'Claim',
            self::ClaimExpire => 'Claim Expire',
            self::StatusChange => 'Status Change',
            self::SoftScore => 'Soft Score',
            self::RndCheck => 'RND Check',
        };
    }
}

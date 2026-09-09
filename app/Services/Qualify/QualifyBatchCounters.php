<?php

namespace App\Services\Qualify;

use App\Enums\BookingCheckStatus;
use App\Enums\DncStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Models\QualifyBatch;
use App\Support\SoftScoreCounters;

class QualifyBatchCounters
{
    public function completeSoftScore(?int $qualifyBatchId, SoftScoreStatus $status, ?string $code = null): void
    {
        $batch = $this->lock($qualifyBatchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'soft_score_pending' => max(0, $batch->soft_score_pending - 1),
        ];

        $column = SoftScoreCounters::completedColumn($status, $code);
        if ($column !== null) {
            $updates[$column] = $batch->{$column} + 1;
        }

        $batch->update($updates);
        $batch->syncStatusFromCounters();
    }

    public function completeQualification(?int $qualifyBatchId, QualificationStatus $status): void
    {
        $batch = $this->lock($qualifyBatchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'qualification_pending' => max(0, $batch->qualification_pending - 1),
        ];

        match ($status) {
            QualificationStatus::Qualified => $updates['qualification_qualified'] = $batch->qualification_qualified + 1,
            QualificationStatus::NotQualified => $updates['qualification_not_qualified'] = $batch->qualification_not_qualified + 1,
            QualificationStatus::Error => $updates['qualification_error'] = $batch->qualification_error + 1,
            QualificationStatus::Pending => null,
        };

        $batch->update($updates);
        $batch->syncStatusFromCounters();
    }

    public function completeRnd(?int $qualifyBatchId, RndStatus $status): void
    {
        $batch = $this->lock($qualifyBatchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'rnd_pending' => max(0, $batch->rnd_pending - 1),
        ];

        match ($status) {
            RndStatus::Clear => $updates['rnd_clear'] = $batch->rnd_clear + 1,
            RndStatus::Reassigned => $updates['rnd_reassigned'] = $batch->rnd_reassigned + 1,
            RndStatus::NoData => $updates['rnd_no_data'] = $batch->rnd_no_data + 1,
            RndStatus::Error => $updates['rnd_error'] = $batch->rnd_error + 1,
            RndStatus::Pending => null,
        };

        $batch->update($updates);
        $batch->syncStatusFromCounters();
    }

    public function completeDnc(?int $qualifyBatchId, DncStatus $status): void
    {
        $batch = $this->lock($qualifyBatchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'dnc_pending' => max(0, $batch->dnc_pending - 1),
        ];

        match ($status) {
            DncStatus::Clear => $updates['dnc_clear'] = $batch->dnc_clear + 1,
            DncStatus::Hit => $updates['dnc_hit'] = $batch->dnc_hit + 1,
            DncStatus::Invalid => $updates['dnc_invalid'] = $batch->dnc_invalid + 1,
            DncStatus::Error => $updates['dnc_error'] = $batch->dnc_error + 1,
            DncStatus::Pending => null,
        };

        $batch->update($updates);
        $batch->syncStatusFromCounters();
    }

    public function completeBooking(?int $qualifyBatchId, BookingCheckStatus $status): void
    {
        $batch = $this->lock($qualifyBatchId);

        if (! $batch) {
            return;
        }

        $updates = [
            'booking_check_pending' => max(0, $batch->booking_check_pending - 1),
        ];

        match ($status) {
            BookingCheckStatus::Clear => $updates['booking_check_clear'] = $batch->booking_check_clear + 1,
            BookingCheckStatus::FutureHit => $updates['booking_future_hit'] = $batch->booking_future_hit + 1,
            BookingCheckStatus::PastHit => $updates['booking_past_hit'] = $batch->booking_past_hit + 1,
            BookingCheckStatus::Error => $updates['booking_check_error'] = $batch->booking_check_error + 1,
            BookingCheckStatus::Pending => null,
        };

        $batch->update($updates);
        $batch->syncStatusFromCounters();
    }

    private function lock(?int $qualifyBatchId): ?QualifyBatch
    {
        if (! $qualifyBatchId) {
            return null;
        }

        return QualifyBatch::withoutGlobalScopes()->lockForUpdate()->find($qualifyBatchId);
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasCheckBatchHealth
{
    /**
     * Overall batch health for list indicators: ok | pending | error.
     */
    public function healthStatus(): string
    {
        if ($this->batchStatusValue() === 'failed' || filled($this->error_message)) {
            return 'error';
        }

        if (in_array($this->batchStatusValue(), ['pending', 'processing'], true)) {
            return 'pending';
        }

        if (
            ($this->run_soft_score && (int) $this->soft_score_pending > 0)
            || ($this->run_rnd_check && (int) $this->rnd_pending > 0)
            || ($this->run_qualification && (int) $this->qualification_pending > 0)
            || ($this->run_dnc_check && (int) $this->dnc_pending > 0)
        ) {
            return 'pending';
        }

        if (
            (int) $this->soft_score_error > 0
            || (int) $this->rnd_error > 0
            || (int) $this->qualification_error > 0
            || (int) $this->dnc_error > 0
        ) {
            return 'error';
        }

        return 'ok';
    }

    public function healthLabel(): string
    {
        return match ($this->healthStatus()) {
            'pending' => 'In progress',
            'error' => 'Errors',
            default => 'Healthy',
        };
    }

    /**
     * Filter batches by the same health as healthStatus(): ok | pending | error.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeHealth(Builder $query, string $health): Builder
    {
        return match ($health) {
            'error' => $query->where(fn (Builder $q) => self::constrainHealthError($q)),
            'pending' => $query->where(fn (Builder $q) => self::constrainHealthPending($q)),
            'ok' => $query->where(fn (Builder $q) => self::constrainHealthOk($q)),
            default => $query,
        };
    }

    private function batchStatusValue(): string
    {
        $status = $this->status;

        return $status instanceof \BackedEnum ? $status->value : (string) $status;
    }

    /**
     * @param  Builder<self>  $query
     */
    private static function constrainHealthError(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            self::constrainImportFailed($q);
            $q->orWhere(function (Builder $completedWithErrors): void {
                $completedWithErrors
                    ->whereNotIn('status', ['pending', 'processing'])
                    ->whereNot(fn (Builder $inner) => self::constrainChecksPending($inner))
                    ->where(fn (Builder $inner) => self::constrainCheckErrors($inner));
            });
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    private static function constrainHealthPending(Builder $query): void
    {
        $query
            ->whereNot(fn (Builder $q) => self::constrainImportFailed($q))
            ->where(function (Builder $q): void {
                $q->whereIn('status', ['pending', 'processing'])
                    ->orWhere(fn (Builder $inner) => self::constrainChecksPending($inner));
            });
    }

    /**
     * @param  Builder<self>  $query
     */
    private static function constrainHealthOk(Builder $query): void
    {
        $query
            ->whereNot(fn (Builder $q) => self::constrainImportFailed($q))
            ->whereNotIn('status', ['pending', 'processing'])
            ->whereNot(fn (Builder $q) => self::constrainChecksPending($q))
            ->whereNot(fn (Builder $q) => self::constrainCheckErrors($q));
    }

    /**
     * @param  Builder<self>  $query
     */
    private static function constrainImportFailed(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where('status', 'failed')
                ->orWhere(function (Builder $inner): void {
                    $inner->whereNotNull('error_message')
                        ->where('error_message', '!=', '');
                });
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    private static function constrainChecksPending(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where(function (Builder $inner): void {
                $inner->where('run_soft_score', true)->where('soft_score_pending', '>', 0);
            })->orWhere(function (Builder $inner): void {
                $inner->where('run_rnd_check', true)->where('rnd_pending', '>', 0);
            })->orWhere(function (Builder $inner): void {
                $inner->where('run_qualification', true)->where('qualification_pending', '>', 0);
            })->orWhere(function (Builder $inner): void {
                $inner->where('run_dnc_check', true)->where('dnc_pending', '>', 0);
            });
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    private static function constrainCheckErrors(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where('soft_score_error', '>', 0)
                ->orWhere('rnd_error', '>', 0)
                ->orWhere('qualification_error', '>', 0)
                ->orWhere('dnc_error', '>', 0);
        });
    }
}

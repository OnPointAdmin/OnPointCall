<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Enums\ImportBatchStatus;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Models\ImportBatch;
use App\Services\Import\ImportBatchCheckRetryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewImportBatch extends ViewRecord
{
    protected static string $resource = ImportBatchResource::class;

    protected string $view = 'filament.resources.import-batches.view-import-batch';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    $this->refreshRecord();
                    $this->dispatch('import-batch-refreshed');
                }),
            Action::make('runSoftScore')
                ->label('Run Soft Score')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run Soft Score on this batch?')
                ->modalDescription('This will queue Soft Score for every lead in this batch that has not been scored yet.')
                ->visible(fn (): bool => ! $this->getRecord()->run_soft_score
                    && $this->getRecord()->status === ImportBatchStatus::Completed)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->runSoftScore($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No unscored leads to Soft Score' : "Queued {$queued} Soft Score job(s)")
                        ->success()
                        ->send();
                }),
            Action::make('retrySoftScoreErrors')
                ->label('Retry Soft Score errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->soft_score_error > 0)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retrySoftScoreErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No Soft Score errors to retry' : "Queued {$queued} Soft Score retry job(s)")
                        ->success()
                        ->send();
                }),
            Action::make('retryRndErrors')
                ->label('Retry RND errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->rnd_error > 0)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryRndErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No RND errors to retry' : "Queued {$queued} RND retry job(s)")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function refreshRecord(): void
    {
        $this->record = $this->getRecord()->refresh();
        $this->fillForm();
    }

    public function isProcessing(): bool
    {
        return $this->getRecord()->healthStatus() === 'pending';
    }
}

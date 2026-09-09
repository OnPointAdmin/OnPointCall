<?php

namespace App\Filament\Resources\QualifyBatches\Pages;

use App\Filament\Resources\QualifyBatches\QualifyBatchResource;
use App\Models\QualifyBatch;
use App\Services\Qualify\QualifyBatchCheckRetryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewQualifyBatch extends ViewRecord
{
    protected static string $resource = QualifyBatchResource::class;

    protected string $view = 'filament.resources.qualify-batches.view-qualify-batch';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    $this->refreshRecord();
                    $this->dispatch('qualify-batch-refreshed');
                }),
            Action::make('retrySoftScoreErrors')
                ->label('Retry Soft Score errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->soft_score_error > 0)
                ->action(function (QualifyBatchCheckRetryService $retryService): void {
                    /** @var QualifyBatch $batch */
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
                ->action(function (QualifyBatchCheckRetryService $retryService): void {
                    /** @var QualifyBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryRndErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No RND errors to retry' : "Queued {$queued} RND retry job(s)")
                        ->success()
                        ->send();
                }),
            Action::make('retryQualificationErrors')
                ->label('Retry Qualification errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->qualification_error > 0)
                ->action(function (QualifyBatchCheckRetryService $retryService): void {
                    /** @var QualifyBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryQualificationErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No Qualification errors to retry' : "Queued {$queued} Qualification retry job(s)")
                        ->success()
                        ->send();
                }),
            Action::make('retryDncErrors')
                ->label('Retry DNC errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->dnc_error > 0)
                ->action(function (QualifyBatchCheckRetryService $retryService): void {
                    /** @var QualifyBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryDncErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No DNC errors to retry' : "Queued {$queued} lead(s) for DNC retry")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing('user');
    }

    public function refreshRecord(): void
    {
        $this->record = $this->getRecord()->refresh()->loadMissing('user');
        $this->fillForm();
    }

    public function isProcessing(): bool
    {
        return $this->getRecord()->healthStatus() === 'pending';
    }
}

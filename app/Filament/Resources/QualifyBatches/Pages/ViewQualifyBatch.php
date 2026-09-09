<?php

namespace App\Filament\Resources\QualifyBatches\Pages;

use App\Filament\Resources\QualifyBatches\QualifyBatchResource;
use App\Filament\Resources\QualifyBatches\RelationManagers\LeadsRelationManager;
use App\Models\QualifyBatch;
use App\Services\Qualify\QualifyBatchCheckRetryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
            Action::make('retryBookingErrors')
                ->label('Retry booking errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->booking_check_error > 0)
                ->action(function (QualifyBatchCheckRetryService $retryService): void {
                    /** @var QualifyBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryBookingErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No booking errors to retry' : "Queued {$queued} lead(s) for booking retry")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    /**
     * @return array<class-string<LeadsRelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            LeadsRelationManager::class,
        ];
    }

    public function getRelationManagersContentComponent(): Component
    {
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        return Group::make([
            Section::make('Leads')
                ->columnSpanFull()
                ->schema([
                    Livewire::make(
                        LeadsRelationManager::class,
                        [...$managerLivewireData, ...LeadsRelationManager::getDefaultProperties()],
                    )->key(LeadsRelationManager::class),
                ]),
        ]);
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

<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Enums\ImportBatchStatus;
use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Resources\ImportBatches\RelationManagers\DuplicatesRelationManager;
use App\Filament\Resources\ImportBatches\RelationManagers\LeadsRelationManager;
use App\Models\ImportBatch;
use App\Services\Import\ImportBatchCheckRetryService;
use App\Services\Import\LeadImportService;
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
            Action::make('runQualification')
                ->label('Run Qualification')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run Qualification on this batch?')
                ->modalDescription('This will queue partner qualification for every lead in this batch that has not been qualified yet.')
                ->visible(fn (): bool => ! $this->getRecord()->run_qualification
                    && $this->getRecord()->status === ImportBatchStatus::Completed)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->runQualification($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No unchecked leads to qualify' : "Queued {$queued} Qualification job(s)")
                        ->success()
                        ->send();
                }),
            Action::make('retryQualificationErrors')
                ->label('Retry Qualification errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->qualification_error > 0)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryQualificationErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No Qualification errors to retry' : "Queued {$queued} Qualification retry job(s)")
                        ->success()
                        ->send();
                }),
            Action::make('runDncCheck')
                ->label('Run DNC check')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run DNC.com check on this batch?')
                ->modalDescription(fn (): string => $this->getRecord()->ignore_national_dnc
                    ? 'This will scrub every unchecked lead. National and state DNC are recorded but will not block (this batch has TCPA consent). Litigator and internal DNC still flag.'
                    : 'This will scrub every unchecked lead. National DNC, state DNC, internal DNC, and litigators will all flag.')
                ->visible(fn (): bool => ! $this->getRecord()->run_dnc_check
                    && $this->getRecord()->status === ImportBatchStatus::Completed)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->runDncCheck($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No unchecked leads to scrub' : "Queued {$queued} lead(s) for DNC check")
                        ->success()
                        ->send();
                }),
            Action::make('retryDncErrors')
                ->label('Retry DNC errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->dnc_error > 0)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryDncErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No DNC errors to retry' : "Queued {$queued} lead(s) for DNC retry")
                        ->success()
                        ->send();
                }),
            Action::make('runBookingCheck')
                ->label('Run booking check')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run Salesforce booking check on this batch?')
                ->modalDescription('This will query Booking__c for every unchecked lead. Matching future or past bookings mark the lead Booked based on this batch’s toggles.')
                ->visible(fn (): bool => ! $this->getRecord()->run_booking_check
                    && $this->getRecord()->status === ImportBatchStatus::Completed)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->runBookingCheck($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No unchecked leads for booking check' : "Queued {$queued} lead(s) for booking check")
                        ->success()
                        ->send();
                }),
            Action::make('retryBookingErrors')
                ->label('Retry booking errors')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => (int) $this->getRecord()->booking_check_error > 0)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $queued = $retryService->retryBookingErrors($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title($queued === 0 ? 'No booking errors to retry' : "Queued {$queued} lead(s) for booking retry")
                        ->success()
                        ->send();
                }),
            Action::make('reapplyDncConsent')
                ->label('Re-apply DNC consent policy')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Apply TCPA consent to this batch?')
                ->modalDescription('Uses stored DNC.com results (no new scrub). National and state DNC hits are released. Litigator and internal DNC stay flagged. Does not change leads an agent already marked DNC.')
                ->visible(fn (): bool => $this->getRecord()->run_dnc_check
                    && $this->getRecord()->status === ImportBatchStatus::Completed
                    && (int) $this->getRecord()->dnc_hit > 0)
                ->action(function (ImportBatchCheckRetryService $retryService): void {
                    /** @var ImportBatch $batch */
                    $batch = $this->getRecord();
                    $result = $retryService->reapplyDncConsentPolicy($batch, Auth::id());

                    $this->refreshRecord();

                    Notification::make()
                        ->title("Released {$result['released']} consented DNC lead(s)")
                        ->body("{$result['remaining_hits']} remain DNC (litigator or internal). {$result['skipped']} skipped.")
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
     * @return array<class-string<DuplicatesRelationManager|LeadsRelationManager>>
     */
    protected function getAllRelationManagers(): array
    {
        return [
            LeadsRelationManager::class,
            DuplicatesRelationManager::class,
        ];
    }

    public function getRelationManagersContentComponent(): Component
    {
        $ownerRecord = $this->getRecord();
        $managerLivewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        $section = function (string $heading, string $manager) use ($managerLivewireData): Section {
            return Section::make($heading)
                ->columnSpanFull()
                ->schema([
                    Livewire::make(
                        $manager,
                        [...$managerLivewireData, ...$manager::getDefaultProperties()],
                    )->key($manager),
                ]);
        };

        return Group::make([
            $section('Leads', LeadsRelationManager::class),
            Section::make('Duplicates')
                ->columnSpanFull()
                ->visible(fn (): bool => (int) $this->getRecord()->duplicate_count + (int) $this->getRecord()->conflict_count > 0)
                ->schema([
                    Livewire::make(
                        DuplicatesRelationManager::class,
                        [...$managerLivewireData, ...DuplicatesRelationManager::getDefaultProperties()],
                    )->key(DuplicatesRelationManager::class),
                ]),
        ]);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->ensureSkippedRows();
    }

    public function refreshRecord(): void
    {
        $this->ensureSkippedRows();
        $this->record = $this->getRecord()->refresh();
        $this->fillForm();
    }

    public function isProcessing(): bool
    {
        return $this->getRecord()->healthStatus() === 'pending';
    }

    private function ensureSkippedRows(): void
    {
        $batch = $this->getRecord();

        if ((int) $batch->duplicate_count + (int) $batch->conflict_count === 0) {
            return;
        }

        if ($batch->skippedRows()->exists()) {
            return;
        }

        app(LeadImportService::class)->backfillSkippedRows($batch);
    }
}

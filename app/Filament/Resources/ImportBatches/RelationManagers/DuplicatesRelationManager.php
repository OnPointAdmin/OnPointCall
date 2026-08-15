<?php

namespace App\Filament\Resources\ImportBatches\RelationManagers;

use App\Enums\ImportSkipReason;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\ImportBatchSkippedRow;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class DuplicatesRelationManager extends RelationManager
{
    protected static string $relationship = 'skippedRows';

    protected static ?string $inverseRelationship = 'importBatch';

    protected static ?string $title = 'Duplicates';

    public function isReadOnly(): bool
    {
        return true;
    }

    #[On('import-batch-refreshed')]
    public function refreshDuplicatesTable(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('phone')
            ->defaultSort('id')
            ->columns([
                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('first_name')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('last_name')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('external_lead_id')
                    ->label('External ID')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ImportSkipReason $state): string => match ($state) {
                        ImportSkipReason::Duplicate => 'warning',
                        ImportSkipReason::Conflict => 'danger',
                        ImportSkipReason::InvalidPhone => 'gray',
                    })
                    ->formatStateUsing(fn (ImportSkipReason $state): string => $state->label()),
                TextColumn::make('matched_on')
                    ->label('Matched on')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'phone' => 'Phone',
                        'external_lead_id' => 'External ID',
                        'phone_and_id' => 'Phone + External ID',
                        default => '—',
                    }),
                TextColumn::make('existing_lead_id')
                    ->label('Existing lead')
                    ->placeholder('—')
                    ->url(fn (ImportBatchSkippedRow $record): ?string => $record->existing_lead_id
                        ? LeadResource::getUrl('view', ['record' => $record->existing_lead_id])
                        : null),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('viewExistingLead')
                    ->label('View lead')
                    ->url(fn (ImportBatchSkippedRow $record): string => LeadResource::getUrl('view', [
                        'record' => $record->existing_lead_id,
                    ]))
                    ->visible(fn (ImportBatchSkippedRow $record): bool => $record->existing_lead_id !== null),
            ])
            ->toolbarActions([]);
    }
}

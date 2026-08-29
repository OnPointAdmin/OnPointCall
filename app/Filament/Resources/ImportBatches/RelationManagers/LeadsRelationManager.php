<?php

namespace App\Filament\Resources\ImportBatches\RelationManagers;

use App\Enums\DncStatus;
use App\Enums\LeadStatus;
use App\Enums\QualificationStatus;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Filament\Actions\ViewDncResultAction;
use App\Filament\Actions\ViewQualificationResultAction;
use App\Models\Lead;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';

    protected static ?string $title = 'Leads';

    public function isReadOnly(): bool
    {
        return true;
    }

    #[On('import-batch-refreshed')]
    public function refreshLeadsTable(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('phone')
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?LeadStatus $state): ?string => $state?->label())
                    ->sortable(),
                TextColumn::make('soft_score_code')
                    ->label('Soft score')
                    ->badge()
                    ->placeholder(fn (Lead $record): string => match ($record->soft_score_status) {
                        SoftScoreStatus::Pending => 'Pending',
                        SoftScoreStatus::Error => 'Error',
                        SoftScoreStatus::Complete => '—',
                        SoftScoreStatus::Recent => 'Recently checked',
                        default => '—',
                    })
                    ->sortable(),
                TextColumn::make('rnd_status')
                    ->label('RND')
                    ->badge()
                    ->formatStateUsing(fn (?RndStatus $state): ?string => $state?->label())
                    ->sortable(),
                TextColumn::make('qualification_status')
                    ->label('Qualification')
                    ->badge()
                    ->color(fn (?QualificationStatus $state): string => match ($state) {
                        QualificationStatus::Qualified => 'success',
                        QualificationStatus::NotQualified => 'warning',
                        QualificationStatus::Error => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?QualificationStatus $state): ?string => $state?->label())
                    ->tooltip(fn (Lead $record): ?string => $record->qualification_status
                        ? 'View qualification response'
                        : null)
                    ->action(ViewQualificationResultAction::make())
                    ->sortable(),
                TextColumn::make('dnc_status')
                    ->label('DNC')
                    ->badge()
                    ->color(fn (?DncStatus $state): string => match ($state) {
                        DncStatus::Clear => 'success',
                        DncStatus::Hit, DncStatus::Invalid, DncStatus::Error => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?DncStatus $state): ?string => $state?->label())
                    ->tooltip(fn (Lead $record): ?string => $record->dnc_status
                        ? ($record->dncDetailLabel() ?? 'View DNC scrub result')
                        : null)
                    ->action(ViewDncResultAction::make())
                    ->sortable(),
                TextColumn::make('error')
                    ->label('Error')
                    ->wrap()
                    ->limit(100)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->getStateUsing(function (Lead $record): ?string {
                        $parts = array_filter([
                            $record->soft_score_last_error,
                            $record->rnd_last_error,
                            $record->qualification_last_error,
                            $record->dnc_last_error,
                        ]);

                        return $parts === [] ? null : implode(' | ', $parts);
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(LeadStatus::cases())->mapWithKeys(
                        fn (LeadStatus $status): array => [$status->value => $status->label()]
                    )),
                SelectFilter::make('soft_score_status')
                    ->label('Soft score')
                    ->options(collect(SoftScoreStatus::cases())->mapWithKeys(
                        fn (SoftScoreStatus $status): array => [$status->value => $status->label()]
                    )),
                SelectFilter::make('rnd_status')
                    ->label('RND')
                    ->options(collect(RndStatus::cases())->mapWithKeys(
                        fn (RndStatus $status): array => [$status->value => $status->label()]
                    )),
                SelectFilter::make('qualification_status')
                    ->label('Qualification')
                    ->options(collect(QualificationStatus::cases())->mapWithKeys(
                        fn (QualificationStatus $status): array => [$status->value => $status->label()]
                    )),
                SelectFilter::make('dnc_status')
                    ->label('DNC')
                    ->options(collect(DncStatus::cases())->mapWithKeys(
                        fn (DncStatus $status): array => [$status->value => $status->label()]
                    )),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

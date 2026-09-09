<?php

namespace App\Filament\Support;

use App\Enums\QualificationStatus;
use App\Filament\Resources\Leads\Schemas\LeadForm;
use App\Models\DispositionDefinition;
use App\Models\Lead;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadPoolPreviewTable
{
    /**
     * @param  callable(): Builder<Lead>  $query
     */
    public static function configure(Table $table, callable $query): Table
    {
        return $table
            ->query($query)
            ->heading(null)
            ->columns(self::columns())
            ->defaultSort('imported_at', 'desc')
            ->recordAction('view')
            ->recordActionsAlignment('end')
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth(Width::Full)
                    ->schema(fn (Schema $schema): Schema => LeadForm::configure($schema, withHistory: true)->columns(2)),
            ], position: RecordActionsPosition::AfterCells)
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No matching leads')
            ->emptyStateDescription('Adjust the filters above to select leads.');
    }

    /**
     * @return list<TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('phone')
                ->searchable()
                ->sortable(),
            TextColumn::make('first_name')
                ->searchable()
                ->sortable(),
            TextColumn::make('last_name')
                ->searchable()
                ->sortable(),
            TextColumn::make('state')
                ->searchable()
                ->sortable(),
            TextColumn::make('venue')
                ->toggleable(),
            TextColumn::make('event')
                ->toggleable(),
            TextColumn::make('status')
                ->badge(),
            TextColumn::make('qualification_status')
                ->label('Qualified')
                ->badge()
                ->placeholder('—')
                ->color(fn (?QualificationStatus $state): string => match ($state) {
                    QualificationStatus::Qualified => 'success',
                    QualificationStatus::NotQualified => 'warning',
                    QualificationStatus::Error => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?QualificationStatus $state): ?string => $state?->label())
                ->toggleable(),
            TextColumn::make('partner_list')
                ->label('Partner List')
                ->wrap()
                ->limit(40)
                ->tooltip(fn (Lead $record): ?string => filled($record->partner_list) ? $record->partner_list : null)
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('qualified_partners')
                ->label('Qualified · Partners')
                ->wrap()
                ->placeholder('—')
                ->getStateUsing(function (Lead $record): ?string {
                    $names = $record->qualifiedPartnerNames();

                    return $names === [] ? null : implode(', ', $names);
                })
                ->toggleable(),
            TextColumn::make('last_disposition')
                ->label('Last Disp')
                ->badge()
                ->placeholder('—')
                ->getStateUsing(function (Lead $record): ?string {
                    $value = $record->latestDisposition?->payload['disposition'] ?? null;

                    if (! is_string($value) || $value === '') {
                        return null;
                    }

                    return DispositionDefinition::labelForSlug($record->company_id, $value) ?? $value;
                }),
            TextColumn::make('attempt_count')
                ->label('Attempts')
                ->numeric()
                ->sortable(),
            TextColumn::make('imported_at')
                ->dateTime()
                ->sortable(),
            TextColumn::make('file_name')
                ->label('Source file')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}

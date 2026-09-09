<?php

namespace App\Filament\Support;

use App\Enums\Disposition;
use App\Enums\QualificationStatus;
use App\Enums\QualifiedPartnersMatch;
use App\Filament\Pages\Concerns\InteractsWithLeadPoolFilter;
use App\Models\CallingList;
use App\Models\ImportBatch;
use App\Services\Import\HoldingReleaseService;
use App\Support\LeadDemographicOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class LeadPoolFilterForm
{
    /**
     * @param  object&InteractsWithLeadPoolFilter  $page
     * @return list<Section>
     */
    public static function sections(object $page): array
    {
        return [
            Section::make('Import')
                ->schema([
                    LeadTypeSelect::make(allowCreate: true)->live(),
                    Select::make('source_calling_list_id')
                        ->label('Source')
                        ->options(fn (): array => self::sourceOptions($page))
                        ->default('holding')
                        ->live(),
                    Select::make('import_batch_id')
                        ->label('Import batch')
                        ->options(fn () => ImportBatch::query()->orderByDesc('imported_at')->pluck('source_filename', 'id'))
                        ->searchable(),
                    TextInput::make('file_name')
                        ->label('Source file')
                        ->live(debounce: 500),
                    DatePicker::make('imported_from')
                        ->label('Import Start Date'),
                    DatePicker::make('imported_to')
                        ->label('Import End Date'),
                    DatePicker::make('created_from')
                        ->label('Create Start Date'),
                    DatePicker::make('created_to')
                        ->label('Create End Date'),
                ])
                ->columns(3),
            Section::make('Venue & event')
                ->schema([
                    self::holdingSelect($page, 'venue', 'Venue'),
                    self::holdingSelect($page, 'event', 'Event'),
                    Select::make('partner')
                        ->label('Partner')
                        ->options(fn () => app(HoldingReleaseService::class)->distinctHoldingPartners(
                            (int) auth()->user()->company_id,
                            $page->selectedLeadType(),
                            $page->selectedSourceCallingListId(),
                            $page->leadPoolAssignableOnly(),
                        ))
                        ->multiple()
                        ->searchable()
                        ->live(),
                ])
                ->columns(3),
            Section::make('Lead profile')
                ->schema([
                    self::demographicSelect('age_range', 'Age range'),
                    self::demographicSelect('annual_income', 'Income range'),
                    self::demographicSelect('marital_status', 'Marital status'),
                    self::demographicSelect('gender', 'Gender'),
                    self::demographicSelect('home_owner', 'Home owner'),
                    self::holdingSelect($page, 'state', 'State'),
                    TextInput::make('zip')
                        ->label('Zip')
                        ->maxLength(10)
                        ->live(debounce: 500),
                    self::holdingSelect($page, 'soft_score_code', 'Soft score code'),
                    Select::make('last_dispositions')
                        ->label('Last Disp')
                        ->options(self::lastDispositionOptions())
                        ->multiple()
                        ->searchable()
                        ->live(),
                    TextInput::make('attempt_count')
                        ->label('Attempts')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->nullable()
                        ->live(debounce: 500),
                    Select::make('qualification_status')
                        ->label('Qualification Status')
                        ->options([
                            QualificationStatus::Qualified->value => QualificationStatus::Qualified->label(),
                            QualificationStatus::NotQualified->value => QualificationStatus::NotQualified->label(),
                        ])
                        ->nullable()
                        ->placeholder('Any')
                        ->live(),
                    Select::make('qualified_partners')
                        ->label('Qualified · Partners')
                        ->options(fn (): array => app(HoldingReleaseService::class)->distinctQualifiedPartners(
                            (int) auth()->user()->company_id,
                            $page->selectedLeadType(),
                            $page->selectedSourceCallingListId(),
                            $page->leadPoolAssignableOnly(),
                        ))
                        ->multiple()
                        ->searchable()
                        ->live(),
                    Select::make('qualified_partners_match')
                        ->label('Qualified · Match')
                        ->options(QualifiedPartnersMatch::options())
                        ->default(QualifiedPartnersMatch::InList->value)
                        ->selectablePlaceholder(false)
                        ->live()
                        ->helperText('In the list includes leads that also qualify for other partners. Just this partner means that partner is the whole list.'),
                ])
                ->columns(3),
            Section::make('Tour Info')
                ->schema([
                    self::holdingSelect($page, 'tour_location', 'Tour Location'),
                    self::holdingSelect($page, 'tour_date_start', 'Tour Date Start'),
                    self::holdingSelect($page, 'tour_date', 'Tour Date'),
                    self::holdingSelect($page, 'tour_result', 'Tour Result'),
                ])
                ->columns(3)
                ->visible(fn (): bool => $page->selectedLeadType() === 'tnb'),
        ];
    }

    /**
     * @param  object&InteractsWithLeadPoolFilter  $page
     * @return array<string, string>
     */
    public static function sourceOptions(object $page): array
    {
        $options = ['holding' => 'Holding'];
        $leadType = $page->selectedLeadType();

        $query = CallingList::query()->where('active', true);

        if ($leadType) {
            $query->where('lead_type', $leadType);
        }

        foreach ($query->orderBy('name')->get() as $list) {
            $options[(string) $list->id] = $list->name;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function lastDispositionOptions(): array
    {
        $options = ['none' => 'None'];

        foreach (Disposition::cases() as $disposition) {
            $options[$disposition->value] = $disposition->label();
        }

        return $options;
    }

    /**
     * @param  object&InteractsWithLeadPoolFilter  $page
     */
    private static function holdingSelect(object $page, string $column, string $label): Select
    {
        return Select::make($column)
            ->label($label)
            ->options(fn () => app(HoldingReleaseService::class)->distinctHoldingColumn(
                (int) auth()->user()->company_id,
                $page->selectedLeadType(),
                $column,
                $page->selectedSourceCallingListId(),
                $page->leadPoolAssignableOnly(),
            ))
            ->multiple()
            ->searchable()
            ->live();
    }

    private static function demographicSelect(string $column, string $label): Select
    {
        return Select::make($column)
            ->label($label)
            ->options(function () use ($column): array {
                $values = LeadDemographicOptions::for($column, auth()->user()->company_id);

                return $values === [] ? [] : array_combine($values, $values);
            })
            ->multiple()
            ->searchable()
            ->live();
    }
}

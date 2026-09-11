<?php

namespace App\Filament\Resources\Leads\Tables;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\BookingCheckStatus;
use App\Enums\Disposition;
use App\Enums\DncStatus;
use App\Enums\LeadStatus;
use App\Enums\LeadTablePreset;
use App\Enums\QualificationStatus;
use App\Enums\QualifiedPartnersMatch;
use App\Enums\RndStatus;
use App\Enums\SoftScoreStatus;
use App\Filament\Support\LeadTableFilterMapper;
use App\Models\CallingList;
use App\Models\DispositionDefinition;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadTypeDefinition;
use App\Services\Import\HoldingReleaseService;
use App\Support\CompanyContext;
use App\Support\LeadDemographicOptions;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LeadsTableFilters
{
    /**
     * @return list<SelectFilter|Filter>
     */
    public static function make(LeadTablePreset $preset, Table $table): array
    {
        $filters = self::definitions($preset, $table);

        if ($preset->hidesCallingListFilter()) {
            unset($filters['calling_list_id']);
        }

        return array_values($filters);
    }

    /**
     * @return array<string, SelectFilter|Filter>
     */
    private static function definitions(LeadTablePreset $preset, Table $table): array
    {
        $leadType = $preset->usesPoolSourceScope()
            ? SelectFilter::make('lead_type')
                ->label('Lead type')
                ->options(fn (): array => LeadTypeDefinition::allOptions())
                ->default('standard')
            : SelectFilter::make('lead_type')
                ->options(fn (): array => LeadTypeDefinition::allOptions());

        return [
            'lead_type' => $leadType,
            'calling_list_id' => SelectFilter::make('calling_list_id')
                ->label('Calling list')
                ->options(fn (): array => ['holding' => 'Holding'] + CallingList::query()->orderBy('name')->pluck('name', 'id')->all())
                ->default($preset->usesPoolSourceScope() ? 'holding' : null)
                ->query(function (Builder $query, array $data) use ($preset): Builder {
                    $value = $data['value'] ?? null;

                    if ($value === null || $value === '') {
                        return $query;
                    }

                    if ($preset->usesPoolSourceScope()) {
                        $sourceId = $value === 'holding' ? null : (int) $value;
                        app(HoldingReleaseService::class)->applyPoolSourceToQuery($query, $sourceId);

                        return $query;
                    }

                    if ($value === 'holding') {
                        return $query->whereNull('calling_list_id');
                    }

                    return $query->where('calling_list_id', $value);
                }),
            'status' => SelectFilter::make('status')
                ->options(collect(LeadStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            'import_batch_id' => SelectFilter::make('import_batch_id')
                ->label('Import batch')
                ->options(fn (): array => ImportBatch::query()->orderByDesc('imported_at')->pluck('source_filename', 'id')->all())
                ->searchable(),
            'file_name' => Filter::make('file_name')
                ->label('Source file')
                ->schema([
                    TextInput::make('file_name')
                        ->label('Source file'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $value = $data['file_name'] ?? null;

                    if (! filled($value)) {
                        return $query;
                    }

                    return $query->where('file_name', 'ilike', '%'.$value.'%');
                }),
            'imported_at' => Filter::make('imported_at')
                ->label('Import date')
                ->schema([
                    DatePicker::make('start_date')->label('Import Start Date'),
                    DatePicker::make('end_date')->label('Import End Date'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (filled($data['start_date'] ?? null)) {
                        $query->where('imported_at', '>=', $data['start_date']);
                    }

                    if (filled($data['end_date'] ?? null)) {
                        $query->where('imported_at', '<=', Carbon::parse($data['end_date'])->endOfDay());
                    }

                    return $query;
                }),
            'created_at' => Filter::make('created_at')
                ->label('Create date')
                ->schema([
                    DatePicker::make('start_date')->label('Create Start Date'),
                    DatePicker::make('end_date')->label('Create End Date'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    if (filled($data['start_date'] ?? null)) {
                        $query->whereDate('original_lead_submit_date', '>=', $data['start_date']);
                    }

                    if (filled($data['end_date'] ?? null)) {
                        $query->whereDate('original_lead_submit_date', '<=', $data['end_date']);
                    }

                    return $query;
                }),
            'venue' => SelectFilter::make('venue')
                ->label('Venue')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('venue', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'venue', $data)),
            'event' => SelectFilter::make('event')
                ->label('Event')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('event', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'event', $data)),
            'state' => SelectFilter::make('state')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('state', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'state', $data, upper: true)),
            'zip' => Filter::make('zip')
                ->schema([
                    TextInput::make('zip')
                        ->label('Zip')
                        ->maxLength(10),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $zip = $data['zip'] ?? null;

                    if (! filled($zip)) {
                        return $query;
                    }

                    return $query->where('zip', 'like', substr((string) $zip, 0, 5).'%');
                }),
            'partner' => SelectFilter::make('partner')
                ->label('Partner')
                ->multiple()
                ->options(fn (): array => self::poolDistinct($table, 'partner'))
                ->searchable()
                ->query(function (Builder $query, array $data): Builder {
                    $partners = self::normalizedValues($data);

                    if ($partners === []) {
                        return $query;
                    }

                    return $query->where(function (Builder $partnerQuery) use ($partners): void {
                        foreach ($partners as $partner) {
                            $partnerQuery->orWhere('partner_list', 'ilike', '%'.$partner.'%');
                        }
                    });
                }),
            'age_range' => self::demographicFilter('age_range', 'Age range'),
            'annual_income' => self::demographicFilter('annual_income', 'Income range'),
            'marital_status' => self::demographicFilter('marital_status', 'Marital status'),
            'gender' => self::demographicFilter('gender', 'Gender'),
            'home_owner' => self::demographicFilter('home_owner', 'Home owner'),
            'soft_score_code' => SelectFilter::make('soft_score_code')
                ->label('Soft score code')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('soft_score_code', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'soft_score_code', $data)),
            'last_disposition' => SelectFilter::make('last_disposition')
                ->label('Last Disp')
                ->multiple()
                ->options(fn (): array => ['none' => 'None'] + collect(Disposition::cases())->mapWithKeys(
                    fn (Disposition $disposition): array => [$disposition->value => $disposition->label()],
                )->all() + DispositionDefinition::filterOptions(
                    (int) (CompanyContext::idOrAuthenticated() ?? Auth::user()?->company_id),
                ))
                ->searchable()
                ->query(function (Builder $query, array $data): Builder {
                    $values = self::normalizedValues($data);

                    if ($values === []) {
                        return $query;
                    }

                    app(HoldingReleaseService::class)->applyLastDispositionsToQuery($query, $values);

                    return $query;
                }),
            'attempt_count' => Filter::make('attempt_count')
                ->schema([
                    TextInput::make('attempt_count')
                        ->label('Attempts')
                        ->numeric()
                        ->integer()
                        ->minValue(0),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $value = $data['attempt_count'] ?? null;

                    if ($value === null || $value === '') {
                        return $query;
                    }

                    return $query->where('attempt_count', (int) $value);
                }),
            'soft_score_status' => SelectFilter::make('soft_score_status')
                ->label('Soft score status')
                ->options(collect(SoftScoreStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            'qualification_status' => SelectFilter::make('qualification_status')
                ->label('Qualification')
                ->options(collect(QualificationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            'dnc_status' => SelectFilter::make('dnc_status')
                ->label('DNC')
                ->options(collect(DncStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            'rnd_status' => SelectFilter::make('rnd_status')
                ->label('RND')
                ->options(collect(RndStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            'booking_check_status' => SelectFilter::make('booking_check_status')
                ->label('Booking')
                ->options(collect(BookingCheckStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            'qualified_partners' => SelectFilter::make('qualified_partners')
                ->label('Qualified · Partners')
                ->multiple()
                ->options(fn (): array => ['none' => 'None'] + self::poolQualifiedPartners($table))
                ->searchable()
                ->query(function (Builder $query, array $data) use ($table): Builder {
                    $values = self::normalizedValues($data);

                    if ($values === []) {
                        return $query;
                    }

                    $filter = LeadTableFilterMapper::toHoldingFilter($table->getLivewire()->tableFilters ?? []);
                    $filter = new HoldingFilter(
                        leadType: $filter->leadType,
                        sourceCallingListId: $filter->sourceCallingListId,
                        qualifiedPartners: $values,
                        qualifiedPartnersMatch: $filter->qualifiedPartnersMatch,
                    );

                    app(HoldingReleaseService::class)->applyQualifiedPartnersToQuery($query, $filter);

                    return $query;
                }),
            'qualified_partners_match' => SelectFilter::make('qualified_partners_match')
                ->label('Qualified · Match')
                ->options(QualifiedPartnersMatch::options())
                ->default(QualifiedPartnersMatch::InList->value)
                ->selectablePlaceholder(false)
                ->query(function (Builder $query, array $data) use ($table): Builder {
                    $partners = self::normalizedValues($table->getLivewire()->tableFilters['qualified_partners'] ?? []);

                    if ($partners === []) {
                        return $query;
                    }

                    $base = LeadTableFilterMapper::toHoldingFilter($table->getLivewire()->tableFilters ?? []);

                    app(HoldingReleaseService::class)->applyQualifiedPartnersToQuery($query, new HoldingFilter(
                        leadType: $base->leadType,
                        sourceCallingListId: $base->sourceCallingListId,
                        qualifiedPartners: $partners,
                        qualifiedPartnersMatch: $data['value'] ?? QualifiedPartnersMatch::InList->value,
                    ));

                    return $query;
                }),
            'tour_location' => SelectFilter::make('tour_location')
                ->label('Tour Location')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('tour_location', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'tour_location', $data)),
            'tour_date_start' => SelectFilter::make('tour_date_start')
                ->label('Tour Date Start')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('tour_date_start', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'tour_date_start', $data)),
            'tour_date' => SelectFilter::make('tour_date')
                ->label('Tour Date')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('tour_date', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'tour_date', $data)),
            'tour_result' => SelectFilter::make('tour_result')
                ->label('Tour Result')
                ->multiple()
                ->options(fn (): array => self::distinctLeadValues('tour_result', $table, $preset))
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, 'tour_result', $data)),
        ];
    }

    private static function demographicFilter(string $column, string $label): SelectFilter
    {
        return SelectFilter::make($column)
            ->label($label)
            ->multiple()
            ->options(function () use ($column): array {
                $values = LeadDemographicOptions::for($column, Auth::user()?->company_id);

                return $values === [] ? [] : array_combine($values, $values);
            })
            ->searchable()
            ->query(fn (Builder $query, array $data): Builder => self::applyInFilter($query, $column, $data));
    }

    /**
     * @return array<string, string>
     */
    private static function distinctLeadValues(string $column, Table $table, LeadTablePreset $preset): array
    {
        $query = Lead::query()
            ->whereNotNull($column)
            ->where($column, '!=', '');

        if ($preset === LeadTablePreset::CallingList) {
            $owner = $table->getLivewire()->getOwnerRecord();

            if ($owner instanceof CallingList) {
                $query->where('calling_list_id', $owner->id);
            }
        }

        if ($preset === LeadTablePreset::Batch) {
            $owner = $table->getLivewire()->getOwnerRecord();

            if ($owner instanceof Model && method_exists($owner, 'leads')) {
                $ids = $owner->leads()->pluck('leads.id');

                $query->whereIn('id', $ids);
            }
        }

        return $query
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function poolDistinct(Table $table, string $column): array
    {
        $context = self::poolContext($table);

        if ($column === 'partner') {
            return app(HoldingReleaseService::class)->distinctHoldingPartners(
                $context['companyId'],
                $context['leadType'],
                $context['sourceCallingListId'],
                $context['assignableOnly'],
            );
        }

        return app(HoldingReleaseService::class)->distinctHoldingColumn(
            $context['companyId'],
            $context['leadType'],
            $column,
            $context['sourceCallingListId'],
            $context['assignableOnly'],
        );
    }

    /**
     * @return array<string, string>
     */
    private static function poolQualifiedPartners(Table $table): array
    {
        $context = self::poolContext($table);

        return app(HoldingReleaseService::class)->distinctQualifiedPartners(
            $context['companyId'],
            $context['leadType'],
            $context['sourceCallingListId'],
            $context['assignableOnly'],
        );
    }

    /**
     * @return array{companyId: int, leadType: ?string, sourceCallingListId: ?int, assignableOnly: bool}
     */
    private static function poolContext(Table $table): array
    {
        $filter = LeadTableFilterMapper::toHoldingFilter($table->getLivewire()->tableFilters ?? []);
        $livewire = $table->getLivewire();
        $assignableOnly = method_exists($livewire, 'leadPoolAssignableOnly')
            ? $livewire->leadPoolAssignableOnly()
            : true;

        return [
            'companyId' => (int) (Auth::user()?->company_id ?? 0),
            'leadType' => $filter->leadType,
            'sourceCallingListId' => $filter->sourceCallingListId,
            'assignableOnly' => $assignableOnly,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function normalizedValues(array $data): array
    {
        $values = $data['values'] ?? null;

        if ($values === null && isset($data['value'])) {
            $values = [$data['value']];
        }

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $values),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function applyInFilter(Builder $query, string $column, array $data, bool $upper = false): Builder
    {
        $values = self::normalizedValues($data);

        if ($values === []) {
            return $query;
        }

        if ($upper) {
            $values = array_map(static fn (string $value): string => strtoupper($value), $values);
        }

        return $query->whereIn($column, $values);
    }
}

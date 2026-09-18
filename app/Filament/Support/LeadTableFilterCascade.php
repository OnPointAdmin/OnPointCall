<?php

namespace App\Filament\Support;

use App\DataTransferObjects\HoldingFilter;
use App\Enums\LeadTablePreset;
use App\Models\DispositionDefinition;
use App\Models\LeadTypeDefinition;
use App\Services\Import\HoldingReleaseService;
use App\Support\CompanyContext;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LeadTableFilterCascade
{
    /**
     * @var list<string>
     */
    private const CASCADING_FILTERS = [
        'lead_type',
        'calling_list_id',
        'import_batch_id',
        'venue',
        'event',
        'state',
        'partner',
        'age_range',
        'annual_income',
        'marital_status',
        'gender',
        'home_owner',
        'credit_card_type',
        'soft_score_code',
        'last_disposition',
        'attempt_count',
        'qualification_status',
        'qualified_partners',
        'tour_location',
        'tour_date_start',
        'tour_date',
        'tour_result',
    ];

    public static function usesCascade(LeadTablePreset $preset): bool
    {
        return $preset->usesPoolSourceScope();
    }

    /**
     * @param  array<string, mixed>|null  $tableFilters
     * @return array<string|int, string>
     */
    public static function optionsFor(string $filterName, Table $table, ?array $tableFilters = null): array
    {
        $tableFilters ??= $table->getLivewire()->tableFilters ?? [];
        $filter = LeadTableFilterMapper::toHoldingFilter($tableFilters);
        $context = self::poolContext($table);
        $service = app(HoldingReleaseService::class);

        return match ($filterName) {
            'lead_type' => LeadTypeDefinition::allOptions(),
            'calling_list_id' => $service->filteredCallingListOptions(
                $context['companyId'],
                $filter,
                $context['assignableOnly'],
            ),
            'import_batch_id' => $service->distinctFilteredImportBatchOptions(
                $context['companyId'],
                $filter,
                $context['assignableOnly'],
            ),
            'partner' => $service->distinctFilteredPartners(
                $context['companyId'],
                $filter,
                $context['assignableOnly'],
            ),
            'qualified_partners' => ['none' => 'None'] + $service->distinctFilteredQualifiedPartners(
                $context['companyId'],
                $filter,
                $context['assignableOnly'],
            ),
            'qualification_status' => $service->distinctFilteredQualificationStatuses(
                $context['companyId'],
                $filter,
                $context['assignableOnly'],
            ),
            'last_disposition' => self::lastDispositionOptions($filter, $context, $service),
            'attempt_count' => array_map(
                static fn (int $count): string => (string) $count,
                $service->distinctFilteredAttemptCounts(
                    $context['companyId'],
                    $filter,
                    $context['assignableOnly'],
                ),
            ),
            'venue', 'event', 'state', 'age_range', 'annual_income', 'marital_status', 'gender',
            'home_owner', 'credit_card_type', 'soft_score_code', 'tour_location', 'tour_date_start',
            'tour_date', 'tour_result' => $service->distinctFilteredColumn(
                $context['companyId'],
                $filter,
                $filterName,
                $context['assignableOnly'],
                $filterName,
            ),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $tableFilters
     * @param  ?string  $changedFilter  Table filter that triggered the update; left as-is while dependents are pruned.
     */
    public static function prune(array &$tableFilters, Table $table, LeadTablePreset $preset, ?string $changedFilter = null): bool
    {
        if (! self::usesCascade($preset)) {
            return false;
        }

        $changed = false;

        foreach (self::CASCADING_FILTERS as $filterName) {
            if ($filterName === $changedFilter) {
                continue;
            }

            if (! isset($tableFilters[$filterName])) {
                continue;
            }

            $validKeys = array_map(strval(...), array_keys(self::optionsFor($filterName, $table, $tableFilters)));

            if ($filterName === 'attempt_count') {
                $value = $tableFilters[$filterName]['attempt_count'] ?? null;

                if ($value !== null && $value !== '' && ! in_array((string) $value, $validKeys, true)) {
                    $tableFilters[$filterName]['attempt_count'] = null;
                    $changed = true;
                }

                continue;
            }

            $selected = self::selectedValues($tableFilters[$filterName]);

            if ($selected === []) {
                continue;
            }

            $validSelected = array_values(array_intersect($selected, $validKeys));

            if ($validSelected === $selected) {
                continue;
            }

            if (array_key_exists('values', $tableFilters[$filterName])) {
                $tableFilters[$filterName]['values'] = $validSelected;
            } elseif ($validSelected === []) {
                $tableFilters[$filterName]['value'] = null;
            } else {
                $tableFilters[$filterName]['value'] = $validSelected[0];
            }

            $changed = true;
        }

        return $changed;
    }

    /**
     * @return array<string, string>
     */
    private static function lastDispositionOptions(HoldingFilter $filter, array $context, HoldingReleaseService $service): array
    {
        $options = $service->distinctFilteredLastDispositions(
            $context['companyId'],
            $filter,
            $context['assignableOnly'],
        );

        $companyId = (int) (CompanyContext::idOrAuthenticated() ?? Auth::user()?->company_id);

        foreach (DispositionDefinition::filterOptions($companyId) as $value => $label) {
            if (array_key_exists($value, $options)) {
                $options[$value] = $label;
            }
        }

        return $options;
    }

    /**
     * @return array{companyId: int, assignableOnly: bool}
     */
    private static function poolContext(Table $table): array
    {
        $livewire = $table->getLivewire();
        $assignableOnly = method_exists($livewire, 'leadPoolAssignableOnly')
            ? $livewire->leadPoolAssignableOnly()
            : true;

        return [
            'companyId' => (int) (Auth::user()?->company_id ?? 0),
            'assignableOnly' => $assignableOnly,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private static function selectedValues(array $state): array
    {
        $values = $state['values'] ?? null;

        if ($values === null && isset($state['value'])) {
            $values = [$state['value']];
        }

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $values),
            static fn (string $item): bool => $item !== '',
        ));
    }
}

<?php

namespace Database\Seeders;

use App\Enums\Disposition;
use App\Models\Company;
use App\Models\DispositionReason;
use Illuminate\Database\Seeder;

class DispositionReasonSeeder extends Seeder
{
    /**
     * Default NQ/NI reasons sourced from Docs/NQ and NI Reasons.xlsx.
     *
     * @var list<array{disposition: Disposition, label: string, sort_order: int}>
     */
    private const REASONS = [
        ['disposition' => Disposition::NotQualified, 'label' => 'Age', 'sort_order' => 1],
        ['disposition' => Disposition::NotQualified, 'label' => 'Booked', 'sort_order' => 2],
        ['disposition' => Disposition::NotQualified, 'label' => 'Toured', 'sort_order' => 3],
        ['disposition' => Disposition::NotQualified, 'label' => 'Duplicate', 'sort_order' => 4],
        ['disposition' => Disposition::NotQualified, 'label' => 'Home Owner Less Then 3 Years', 'sort_order' => 5],
        ['disposition' => Disposition::NotQualified, 'label' => 'Income', 'sort_order' => 6],
        ['disposition' => Disposition::NotQualified, 'label' => 'Marital Status', 'sort_order' => 7],
        ['disposition' => Disposition::NotQualified, 'label' => 'Not Home Owner', 'sort_order' => 8],
        ['disposition' => Disposition::NotQualified, 'label' => 'NQ Credit', 'sort_order' => 9],
        ['disposition' => Disposition::NotQualified, 'label' => 'Other', 'sort_order' => 10],
        ['disposition' => Disposition::NotQualified, 'label' => 'Travel Club', 'sort_order' => 11],
        ['disposition' => Disposition::NotInterested, 'label' => 'Death in Family', 'sort_order' => 1],
        ['disposition' => Disposition::NotInterested, 'label' => 'Spouse Cannot Attend', 'sort_order' => 2],
        ['disposition' => Disposition::NotInterested, 'label' => 'Too Busy', 'sort_order' => 3],
        ['disposition' => Disposition::NotInterested, 'label' => 'Travel Distance', 'sort_order' => 4],
    ];

    public function run(?int $companyId = null): void
    {
        $companyIds = $companyId !== null
            ? [$companyId]
            : Company::query()->pluck('id')->all();

        foreach ($companyIds as $id) {
            $this->syncForCompany((int) $id);
        }
    }

    private function syncForCompany(int $companyId): void
    {
        $keepByDisposition = [];

        foreach (self::REASONS as $reason) {
            $disposition = $reason['disposition']->value;
            $label = $reason['label'];
            $keepByDisposition[$disposition][] = $label;

            $existing = $this->findExisting($companyId, $disposition, $label);

            if ($existing) {
                $existing->update([
                    'label' => $label,
                    'sort_order' => $reason['sort_order'],
                    'active' => true,
                ]);

                continue;
            }

            DispositionReason::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'disposition' => $disposition,
                'label' => $label,
                'sort_order' => $reason['sort_order'],
                'active' => true,
            ]);
        }

        foreach ($keepByDisposition as $disposition => $labels) {
            DispositionReason::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('disposition', $disposition)
                ->whereNotIn('label', $labels)
                ->update(['active' => false]);
        }
    }

    private function findExisting(int $companyId, string $disposition, string $label): ?DispositionReason
    {
        $query = DispositionReason::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('disposition', $disposition);

        return (clone $query)->where('label', $label)->first()
            ?? $query->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])->first();
    }
}

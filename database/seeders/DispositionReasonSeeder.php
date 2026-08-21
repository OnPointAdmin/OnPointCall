<?php

namespace Database\Seeders;

use App\Enums\Disposition;
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
        ['disposition' => Disposition::NotQualified, 'label' => 'Below Age', 'sort_order' => 1],
        ['disposition' => Disposition::NotQualified, 'label' => 'Above Age', 'sort_order' => 2],
        ['disposition' => Disposition::NotQualified, 'label' => 'Low Income', 'sort_order' => 3],
        ['disposition' => Disposition::NotQualified, 'label' => 'NQ Credit', 'sort_order' => 4],
        ['disposition' => Disposition::NotQualified, 'label' => 'Spouse Not Available', 'sort_order' => 5],
        ['disposition' => Disposition::NotQualified, 'label' => 'Single', 'sort_order' => 6],
        ['disposition' => Disposition::NotQualified, 'label' => 'Too Far Away', 'sort_order' => 7],
        ['disposition' => Disposition::NotQualified, 'label' => 'Timeshare Owner', 'sort_order' => 8],
        ['disposition' => Disposition::NotQualified, 'label' => 'Not Home Owner', 'sort_order' => 9],
        ['disposition' => Disposition::NotQualified, 'label' => 'Home Owner less then 3 Years', 'sort_order' => 10],
        ['disposition' => Disposition::NotQualified, 'label' => 'Already toured', 'sort_order' => 11],
        ['disposition' => Disposition::NotQualified, 'label' => 'Already Booked', 'sort_order' => 12],
        ['disposition' => Disposition::NotQualified, 'label' => 'Duplicate', 'sort_order' => 13],
        ['disposition' => Disposition::NotQualified, 'label' => 'Other', 'sort_order' => 14],
        ['disposition' => Disposition::NotQualified, 'label' => 'Single Male', 'sort_order' => 15],
        ['disposition' => Disposition::NotInterested, 'label' => 'Too Busy', 'sort_order' => 1],
        ['disposition' => Disposition::NotInterested, 'label' => 'Spouse cannot attend', 'sort_order' => 2],
        ['disposition' => Disposition::NotInterested, 'label' => 'Death in Family', 'sort_order' => 3],
    ];

    public function run(int $companyId): void
    {
        foreach (self::REASONS as $reason) {
            DispositionReason::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'disposition' => $reason['disposition']->value,
                    'label' => $reason['label'],
                ],
                [
                    'sort_order' => $reason['sort_order'],
                    'active' => true,
                ],
            );
        }
    }
}

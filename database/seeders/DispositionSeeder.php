<?php

namespace Database\Seeders;

use App\Enums\Disposition;
use App\Enums\DispositionButtonGroup;
use App\Enums\DispositionColor;
use App\Enums\DispositionOutcome;
use App\Enums\DispositionReportGroup;
use App\Models\Company;
use App\Models\DispositionDefinition;
use Illuminate\Database\Seeder;

class DispositionSeeder extends Seeder
{
    /**
     * @var list<array{
     *     slug: string,
     *     label: string,
     *     sort_order: int,
     *     outcome: DispositionOutcome,
     *     increments_attempt: bool,
     *     requires_reason: bool,
     *     button_group: DispositionButtonGroup,
     *     color: DispositionColor,
     *     report_group: DispositionReportGroup,
     * }>
     */
    private const SYSTEM_DISPOSITIONS = [
        [
            'slug' => Disposition::Booked->value,
            'label' => 'Booked',
            'sort_order' => 1,
            'outcome' => DispositionOutcome::Booked,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => DispositionButtonGroup::Primary,
            'color' => DispositionColor::Green,
            'report_group' => DispositionReportGroup::Booked,
        ],
        [
            'slug' => Disposition::Callback->value,
            'label' => 'Callback',
            'sort_order' => 2,
            'outcome' => DispositionOutcome::Callback,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => DispositionButtonGroup::Contact,
            'color' => DispositionColor::Blue,
            'report_group' => DispositionReportGroup::Callbacks,
        ],
        [
            'slug' => Disposition::NoAnswer->value,
            'label' => 'No Answer',
            'sort_order' => 3,
            'outcome' => DispositionOutcome::Callable,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => DispositionButtonGroup::Contact,
            'color' => DispositionColor::Slate,
            'report_group' => DispositionReportGroup::NoAnswerVm,
        ],
        [
            'slug' => Disposition::LeftVm->value,
            'label' => 'Left VM',
            'sort_order' => 4,
            'outcome' => DispositionOutcome::Callable,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => DispositionButtonGroup::Contact,
            'color' => DispositionColor::Slate,
            'report_group' => DispositionReportGroup::NoAnswerVm,
        ],
        [
            'slug' => Disposition::NotInterested->value,
            'label' => 'Not Interested',
            'sort_order' => 5,
            'outcome' => DispositionOutcome::Terminal,
            'increments_attempt' => true,
            'requires_reason' => true,
            'button_group' => DispositionButtonGroup::Negative,
            'color' => DispositionColor::Red,
            'report_group' => DispositionReportGroup::NotInterested,
        ],
        [
            'slug' => Disposition::NotQualified->value,
            'label' => 'Not Qualified',
            'sort_order' => 6,
            'outcome' => DispositionOutcome::Terminal,
            'increments_attempt' => true,
            'requires_reason' => true,
            'button_group' => DispositionButtonGroup::Negative,
            'color' => DispositionColor::Red,
            'report_group' => DispositionReportGroup::NotQualified,
        ],
        [
            'slug' => Disposition::WrongNumber->value,
            'label' => 'Wrong Number',
            'sort_order' => 7,
            'outcome' => DispositionOutcome::Terminal,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => DispositionButtonGroup::Negative,
            'color' => DispositionColor::Red,
            'report_group' => DispositionReportGroup::WrongDnc,
        ],
        [
            'slug' => Disposition::BadNumber->value,
            'label' => 'Bad Number',
            'sort_order' => 8,
            'outcome' => DispositionOutcome::Terminal,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => DispositionButtonGroup::Negative,
            'color' => DispositionColor::Red,
            'report_group' => DispositionReportGroup::WrongDnc,
        ],
        [
            'slug' => Disposition::Dnc->value,
            'label' => 'DNC',
            'sort_order' => 9,
            'outcome' => DispositionOutcome::Dnc,
            'increments_attempt' => true,
            'requires_reason' => false,
            'button_group' => DispositionButtonGroup::Compliance,
            'color' => DispositionColor::Red,
            'report_group' => DispositionReportGroup::WrongDnc,
        ],
        [
            'slug' => Disposition::Skip->value,
            'label' => 'Skip',
            'sort_order' => 10,
            'outcome' => DispositionOutcome::Skip,
            'increments_attempt' => false,
            'requires_reason' => true,
            'button_group' => DispositionButtonGroup::Utility,
            'color' => DispositionColor::Slate,
            'report_group' => DispositionReportGroup::Skipped,
        ],
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
        foreach (self::SYSTEM_DISPOSITIONS as $row) {
            DispositionDefinition::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'slug' => $row['slug'],
                ],
                [
                    'label' => $row['label'],
                    'sort_order' => $row['sort_order'],
                    'active' => true,
                    'is_system' => true,
                    'outcome' => $row['outcome']->value,
                    'increments_attempt' => $row['increments_attempt'],
                    'requires_reason' => $row['requires_reason'],
                    'button_group' => $row['button_group']->value,
                    'color' => $row['color']->value,
                    'report_group' => $row['report_group']->value,
                ],
            );
        }
    }
}

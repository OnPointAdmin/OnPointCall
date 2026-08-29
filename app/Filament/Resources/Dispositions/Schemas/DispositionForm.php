<?php

namespace App\Filament\Resources\Dispositions\Schemas;

use App\Enums\DispositionButtonGroup;
use App\Enums\DispositionColor;
use App\Enums\DispositionOutcome;
use App\Enums\DispositionReportGroup;
use App\Support\CompanyContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class DispositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                        if ($get('is_system') || filled($get('slug')) || blank($state)) {
                            return;
                        }

                        $set('slug', Str::slug($state));
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(100)
                    ->disabled(fn (callable $get): bool => (bool) $get('is_system'))
                    ->dehydrated()
                    ->helperText('Stored on lead history. Standard dispositions cannot change slug.')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule): Unique {
                            $companyId = CompanyContext::idOrAuthenticated();

                            return $companyId
                                ? $rule->where('company_id', $companyId)
                                : $rule;
                        },
                    ),
                Select::make('outcome')
                    ->options(fn (callable $get): array => $get('is_system')
                        ? collect(DispositionOutcome::cases())->mapWithKeys(
                            fn (DispositionOutcome $outcome): array => [$outcome->value => $outcome->label()],
                        )->all()
                        : DispositionOutcome::customOptions())
                    ->required()
                    ->disabled(fn (callable $get): bool => (bool) $get('is_system'))
                    ->dehydrated(),
                Toggle::make('increments_attempt')
                    ->default(true)
                    ->required(),
                Toggle::make('requires_reason')
                    ->default(false)
                    ->required(),
                Select::make('button_group')
                    ->options(collect(DispositionButtonGroup::cases())->mapWithKeys(
                        fn (DispositionButtonGroup $group): array => [$group->value => $group->label()],
                    )->all())
                    ->required(),
                Select::make('color')
                    ->options(collect(DispositionColor::cases())->mapWithKeys(
                        fn (DispositionColor $color): array => [$color->value => $color->label()],
                    )->all())
                    ->required(),
                Select::make('report_group')
                    ->options(collect(DispositionReportGroup::cases())->mapWithKeys(
                        fn (DispositionReportGroup $group): array => [$group->value => $group->label()],
                    )->all())
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\DispositionReasons\Schemas;

use App\Models\DispositionDefinition;
use App\Support\CompanyContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class DispositionReasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('disposition')
                    ->label('Disposition')
                    ->options(fn (): array => DispositionDefinition::withoutGlobalScopes()
                        ->where('company_id', CompanyContext::idOrAuthenticated())
                        ->where('requires_reason', true)
                        ->orderBy('sort_order')
                        ->orderBy('label')
                        ->pluck('label', 'slug')
                        ->all())
                    ->required(),
                TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule, callable $get): Unique {
                            $companyId = CompanyContext::idOrAuthenticated();
                            $disposition = $get('disposition');

                            $rule = $rule->where('disposition', $disposition);

                            return $companyId
                                ? $rule->where('company_id', $companyId)
                                : $rule;
                        },
                    ),
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

<?php

namespace App\Filament\Resources\DispositionReasons\Schemas;

use App\Enums\Disposition;
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
                    ->options([
                        Disposition::NotInterested->value => Disposition::NotInterested->label(),
                        Disposition::NotQualified->value => Disposition::NotQualified->label(),
                        Disposition::Skip->value => Disposition::Skip->label(),
                    ])
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

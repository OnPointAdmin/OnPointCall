<?php

namespace App\Filament\Resources\LeadTypes\Schemas;

use App\Support\CompanyContext;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class LeadTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                        if (filled($get('slug')) || blank($state)) {
                            return;
                        }

                        $set('slug', Str::slug($state));
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Stored on leads, lists, and import batches. Prefer lowercase kebab-case.')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule): Unique {
                            $companyId = CompanyContext::id();

                            return $companyId
                                ? $rule->where('company_id', $companyId)
                                : $rule;
                        },
                    ),
                Toggle::make('active')
                    ->default(true)
                    ->required(),
            ]);
    }
}

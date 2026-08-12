<?php

namespace App\Filament\Support;

use App\Models\LeadTypeDefinition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class LeadTypeSelect
{
    public static function make(string $name = 'lead_type', bool $allowCreate = true, bool $activeOnly = true): Select
    {
        $select = Select::make($name)
            ->label('Lead type')
            ->options(fn (): array => $activeOnly
                ? LeadTypeDefinition::activeOptions()
                : LeadTypeDefinition::allOptions())
            ->searchable()
            ->required();

        if ($allowCreate) {
            $select
                ->createOptionForm([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('slug')
                        ->helperText('Optional. Auto-generated from the name if left blank.')
                        ->maxLength(100),
                    Toggle::make('active')
                        ->default(true),
                ])
                ->createOptionUsing(function (array $data): string {
                    $type = LeadTypeDefinition::createFromName(
                        name: $data['name'],
                        slug: $data['slug'] ?? null,
                        active: (bool) ($data['active'] ?? true),
                    );

                    return $type->slug;
                });
        }

        return $select;
    }
}

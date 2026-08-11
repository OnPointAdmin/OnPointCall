<?php

namespace App\Filament\Resources\AllowedEmails\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AllowedEmailForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
            ]);
    }
}

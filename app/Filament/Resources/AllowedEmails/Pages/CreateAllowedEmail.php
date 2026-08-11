<?php

namespace App\Filament\Resources\AllowedEmails\Pages;

use App\Filament\Resources\AllowedEmails\AllowedEmailResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAllowedEmail extends CreateRecord
{
    protected static string $resource = AllowedEmailResource::class;
}

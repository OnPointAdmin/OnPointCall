<?php

namespace App\Filament\Pages;

use App\Support\Help\HelpDocuments;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class HelpAbout extends Page
{
    protected static ?string $slug = 'help/about';

    protected static string|\UnitEnum|null $navigationGroup = 'Help';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'About';

    protected static ?string $title = 'About OnPoint Marketing’s Lead Booking Application';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected string $view = 'filament.pages.help-about';

    public string $html = '';

    public function mount(): void
    {
        $this->html = HelpDocuments::html('about');
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\LeadTablePreset;
use App\Filament\Pages\Concerns\InteractsWithPersistedLeadTable;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class CallbacksBoard extends Page implements HasTable
{
    use InteractsWithPersistedLeadTable;

    protected static string|\UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Callbacks Board';

    protected static ?string $title = 'Callbacks Board';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected string $view = 'filament.pages.callbacks-board';

    protected function leadTablePreset(): LeadTablePreset
    {
        return LeadTablePreset::Callbacks;
    }

    public function table(Table $table): Table
    {
        return LeadsTable::configure($table, LeadTablePreset::Callbacks);
    }
}

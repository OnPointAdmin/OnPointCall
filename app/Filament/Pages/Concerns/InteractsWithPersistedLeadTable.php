<?php

namespace App\Filament\Pages\Concerns;

use Filament\Tables\Concerns\InteractsWithTable;

trait InteractsWithPersistedLeadTable
{
    use InteractsWithLeadTableLayout;
    use InteractsWithTable {
        InteractsWithLeadTableLayout::getTableColumnsSessionKey insteadof InteractsWithTable;
        InteractsWithLeadTableLayout::loadTableColumnsFromSession insteadof InteractsWithTable;
        InteractsWithLeadTableLayout::persistTableColumns insteadof InteractsWithTable;
        InteractsWithLeadTableLayout::resetTableColumnManager insteadof InteractsWithTable;
        mountInteractsWithTable as protected filamentMountInteractsWithTable;
        getFilteredSortedTableQuery as protected filamentGetFilteredSortedTableQuery;
    }
}

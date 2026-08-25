<?php

namespace App\Filament\Resources\CallingLists\Widgets;

use App\Models\CallingList;
use App\Services\CallingLists\CallingListDispositionCountService;
use Filament\Widgets\Widget;

class CallingListDispositionStats extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.resources.calling-lists.disposition-counts';

    public ?CallingList $record = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        if (! $this->record) {
            return ['items' => []];
        }

        return [
            'items' => app(CallingListDispositionCountService::class)->forList($this->record),
        ];
    }
}

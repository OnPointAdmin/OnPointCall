<?php

namespace App\Filament\Support;

use App\Enums\LeadTablePreset;
use App\Models\User;
use Filament\Tables\Concerns\HasColumnManager;
use Illuminate\Support\Facades\Auth;

class LeadTableLayoutSession
{
    public static function sessionKey(LeadTablePreset $preset): string
    {
        return 'leads_table_'.$preset->value.'_columns';
    }

    /**
     * @param  object&HasColumnManager  $livewire
     * @return array<int, array<string, mixed>>
     */
    public static function load(object $livewire, LeadTablePreset $preset): array
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $saved = $user->leadTableLayout($preset);

            if ($saved !== null) {
                return $saved;
            }
        }

        return session()->get(
            self::sessionKey($preset),
            $livewire->getDefaultTableColumnState(),
        );
    }

    /**
     * @param  object&HasColumnManager  $livewire
     * @param  array<int, array<string, mixed>>  $tableColumns
     */
    public static function persist(object $livewire, LeadTablePreset $preset, array $tableColumns): void
    {
        if ($livewire->getTable()->persistsColumnsInSession()) {
            session()->put(self::sessionKey($preset), $tableColumns);
        }

        $user = Auth::user();

        if ($user instanceof User) {
            $user->saveLeadTableLayout($preset, $tableColumns);
        }
    }

    /**
     * @param  object&HasColumnManager  $livewire
     */
    public static function reset(object $livewire, LeadTablePreset $preset): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $user->clearLeadTableLayout($preset);
        }

        session()->forget(self::sessionKey($preset));

        $livewire->setTableColumns($livewire->getDefaultTableColumnState());

        if ($livewire->hasReorderableTableColumns()) {
            $livewire->updateTableColumns();
            $livewire->persistHasReorderedTableColumns();
        }

        self::persist($livewire, $preset, $livewire->tableColumns);
    }
}

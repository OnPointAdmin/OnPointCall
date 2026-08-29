<?php

namespace App\Enums;

enum DispositionColor: string
{
    case Green = 'green';
    case Blue = 'blue';
    case Slate = 'slate';
    case Amber = 'amber';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Green',
            self::Blue => 'Blue',
            self::Slate => 'Slate',
            self::Amber => 'Amber',
            self::Red => 'Red',
        };
    }

    public function pillClasses(): string
    {
        return match ($this) {
            self::Green => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-400',
            self::Blue => 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-400',
            self::Slate => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Amber => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-400',
            self::Red => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-400',
        };
    }

    public function buttonClasses(): string
    {
        return match ($this) {
            self::Green => 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20',
            self::Blue => 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-500/10 dark:text-blue-400 dark:hover:bg-blue-500/20',
            self::Slate => 'border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300',
            self::Amber => 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-400 dark:hover:bg-amber-500/20',
            self::Red => 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20',
        };
    }

    public function isPrimarySize(): bool
    {
        return $this === self::Green;
    }
}

<?php

namespace App\Filament\Support;

use App\Models\ImportBatch;
use App\Models\QualifyBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class BatchCheckErrorField
{
    public static function make(
        string $countField,
        string $statusColumn,
        string $errorColumn,
        string $heading,
    ): TextInput {
        $actionName = 'view'.Str::studly($countField);

        return TextInput::make($countField)
            ->label('Errors')
            ->numeric()
            ->hintAction(
                Action::make($actionName)
                    ->label('View')
                    ->link()
                    ->icon(Heroicon::OutlinedExclamationCircle)
                    ->color('danger')
                    ->disabled(false)
                    ->visible(fn (ImportBatch|QualifyBatch|null $record): bool => $record !== null
                        && (int) $record->{$countField} > 0)
                    ->modalHeading($heading)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('2xl')
                    ->modalContent(fn (ImportBatch|QualifyBatch $record) => view('filament.batch-check-errors', [
                        'errors' => self::grouped($record, $statusColumn, $errorColumn),
                    ])),
            );
    }

    /**
     * @return list<array{message: string, total: int}>
     */
    public static function grouped(
        ImportBatch|QualifyBatch $batch,
        string $statusColumn,
        string $errorColumn,
    ): array {
        $messages = $batch->leads()
            ->where("leads.{$statusColumn}", 'error')
            ->pluck($errorColumn);

        return $messages
            ->map(fn (mixed $message): string => filled($message)
                ? (string) $message
                : 'No error message was stored.')
            ->countBy()
            ->sortDesc()
            ->map(fn (int $total, string $message): array => [
                'message' => $message,
                'total' => $total,
            ])
            ->values()
            ->all();
    }
}

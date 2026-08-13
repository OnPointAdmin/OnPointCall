<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Support\LeadTypeSelect;
use App\Jobs\ProcessLeadImportJob;
use App\Models\ImportMapping;
use App\Services\Import\LeadImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ImportLeads extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Imports';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Import CSV';

    protected static ?string $title = 'Import Leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected string $view = 'filament.pages.import-leads';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $defaultMapping = ImportMapping::query()->where('is_default', true)->first();

        $this->form->fill([
            'lead_type' => $defaultMapping?->lead_type ?? 'standard',
            'run_soft_score' => false,
            'run_rnd_check' => false,
            'import_mapping_id' => $defaultMapping?->id,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('csv_file')
                    ->label('CSV file')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->required()
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('imports/uploads')
                    ->columnSpanFull(),
                Select::make('import_mapping_id')
                    ->label('Column mapping')
                    ->options(fn () => ImportMapping::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if (! $state) {
                            return;
                        }

                        $mapping = ImportMapping::query()->find($state);

                        if ($mapping?->lead_type) {
                            $set('lead_type', $mapping->lead_type);
                        }
                    }),
                LeadTypeSelect::make()
                    ->helperText('Creates a type for filtering Assign Leads. Assign to a calling list with the same lead type.'),
                Toggle::make('run_soft_score')
                    ->label('Run Soft Score on import')
                    ->helperText('Queues a soft-score check per lead. Leads stay unassignable until scored.'),
                Toggle::make('run_rnd_check')
                    ->label('Run RND check on import')
                    ->helperText('Queues an FCC Reassigned Numbers Database check per lead. Leads stay unassignable until checked; reassigned numbers are rejected.'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Form
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('import')
            ->footer([
                Actions::make([
                    Action::make('import')
                        ->label('Start import')
                        ->submit('import')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }

    public function import(LeadImportService $importService): void
    {
        $data = $this->form->getState();

        $mapping = ImportMapping::query()->findOrFail($data['import_mapping_id']);
        $columnMap = $mapping->column_map;

        if (! is_array($columnMap) || $columnMap === []) {
            Notification::make()
                ->title('Selected mapping has no column map configured.')
                ->danger()
                ->send();

            return;
        }

        $uploaded = $data['csv_file'];

        if (is_array($uploaded)) {
            $uploaded = $uploaded[0] ?? null;
        }

        if (! is_string($uploaded) || $uploaded === '') {
            Notification::make()
                ->title('Please upload a CSV file.')
                ->danger()
                ->send();

            return;
        }

        $sourceFilename = basename($uploaded);
        $storedPath = Storage::disk('local')->path($uploaded);

        if (! is_readable($storedPath)) {
            Notification::make()
                ->title('Uploaded file could not be read.')
                ->danger()
                ->send();

            return;
        }

        $leadType = (string) $data['lead_type'];

        $batch = $importService->createBatch(
            companyId: auth()->user()->company_id,
            sourceFilename: $sourceFilename,
            leadType: $leadType,
            runSoftScore: (bool) ($data['run_soft_score'] ?? false),
            runRndCheck: (bool) ($data['run_rnd_check'] ?? false),
        );

        ProcessLeadImportJob::dispatch($batch->id, $storedPath, $columnMap);

        Notification::make()
            ->title('Import queued')
            ->body('The import is processing in the background. View the batch report for progress.')
            ->success()
            ->send();

        $this->redirect(ImportBatchResource::getUrl('view', ['record' => $batch]));
    }
}

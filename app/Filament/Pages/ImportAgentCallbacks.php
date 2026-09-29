<?php

namespace App\Filament\Pages;

use App\Filament\Support\BookingCallbackImportNotifier;
use App\Jobs\SyncBookingCallbacksJob;
use App\Models\BookingCallbackSchedule;
use App\Models\BookingCallbackSyncRun;
use App\Models\CallingList;
use App\Services\Leads\AgentCallbacksProvisioner;
use App\Support\CompanyTimezone;
use App\Support\Weekdays;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\HtmlString;

class ImportAgentCallbacks extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Imports';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Import Agent Callbacks';

    protected static ?string $title = 'Import Agent Callbacks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected string $view = 'filament.pages.import-agent-callbacks';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $companyId = (int) auth()->user()->company_id;
        $provisioner = app(AgentCallbacksProvisioner::class);
        $schedule = $provisioner->scheduleFor($companyId);
        $defaultListId = $schedule->calling_list_id ?? $provisioner->listFor($companyId)->id;

        $this->form->fill([
            'calling_list_id' => $defaultListId,
            'schedule_enabled' => $schedule->enabled,
            'days_of_week' => $schedule->normalizedDaysOfWeek(),
            'run_times' => $schedule->normalizedRunTimes(),
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
                Select::make('calling_list_id')
                    ->label('Calling list')
                    ->options(fn (): array => CallingList::query()
                        ->where('active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->searchable()
                    ->helperText('Open Salesforce Callback bookings land on this list as personal callbacks.')
                    ->columnSpanFull(),
                Section::make('Schedule')
                    ->description('Optional automatic import in the company timezone.')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('schedule_enabled')
                            ->label('Run on a schedule')
                            ->default(true),
                        Select::make('days_of_week')
                            ->label('Days')
                            ->multiple()
                            ->options(Weekdays::options())
                            ->default([1, 2, 3, 4, 5, 6, 7])
                            ->required(),
                        Repeater::make('run_times')
                            ->label('Times')
                            ->simple(
                                TimePicker::make('time')
                                    ->seconds(false)
                                    ->required(),
                            )
                            ->minItems(1)
                            ->default(['07:00'])
                            ->addActionLabel('Add time')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
                Section::make('Last import')
                    ->id('booking-callback-errors')
                    ->columnSpanFull()
                    ->schema([
                        Html::make(fn (): HtmlString => new HtmlString($this->lastImportHtml())),
                    ]),
            ]);
    }

    public function getFormContentComponent(): Form
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->footer([
                Actions::make([
                    Action::make('import')
                        ->label('Import now')
                        ->action('import')
                        ->keyBindings(['mod+s']),
                    Action::make('saveSchedule')
                        ->label('Save schedule')
                        ->color('gray')
                        ->action('saveSchedule'),
                ]),
            ]);
    }

    public function import(): void
    {
        $data = $this->form->getState();
        $companyId = (int) auth()->user()->company_id;
        $callingListId = (int) $data['calling_list_id'];
        $list = CallingList::query()->findOrFail($callingListId);

        try {
            $result = Bus::dispatchNow(new SyncBookingCallbacksJob(
                $companyId,
                false,
                'import_now',
                $callingListId,
            ));
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Import failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        BookingCallbackImportNotifier::notify($list, $result);
    }

    public function saveSchedule(): void
    {
        $data = $this->form->getState();
        $companyId = (int) auth()->user()->company_id;
        $schedule = app(AgentCallbacksProvisioner::class)->scheduleFor($companyId);

        $schedule->fill([
            'calling_list_id' => (int) $data['calling_list_id'],
            'enabled' => (bool) ($data['schedule_enabled'] ?? false),
            'days_of_week' => $data['days_of_week'] ?? [],
            'run_times' => $data['run_times'] ?? [],
        ])->save();

        Notification::make()
            ->title('Callback schedule saved')
            ->success()
            ->send();
    }

    private function lastImportHtml(): string
    {
        $companyId = (int) auth()->user()->company_id;
        $run = BookingCallbackSyncRun::latestForCompany($companyId);
        $schedule = BookingCallbackSchedule::query()->with('callingList')->first();

        return view('filament.resources.calling-lists.booking-callback-sync', [
            'run' => $run,
            'errors' => $run?->errors()->orderBy('id')->get() ?? collect(),
            'schedule' => $schedule,
            'timezone' => CompanyTimezone::for($companyId),
        ])->render();
    }
}

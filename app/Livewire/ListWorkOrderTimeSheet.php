<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WorkOrderTimeSheetExport;
use Livewire\Component;

class ListWorkOrderTimeSheet extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $workOrder;
    public $isOpen = false;
    public $periodTotal = '00:00';
    public $activePeriod = null;

    public function mount($workOrder)
    {
        $this->workOrder = $workOrder;
    }

    protected function calculateTotalHours($records): string
    {
        $totalMinutes = 0;

        foreach ($records as $record) {
            $start = Carbon::parse($record->start_time);
            $end = Carbon::parse($record->end_time);
            $break = Carbon::parse($record->break_duration);

            $totalMinutes += $end->diffInMinutes($start) - $break->diffInMinutes(Carbon::today());
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    protected function calculatePeriodTotal($query)
    {
        $records = $query->get();
        $this->periodTotal = $this->calculateTotalHours($records);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('date')
                    ->required()
                    ->format('Y-m-d')
                    ->label('Datum'),
                TimePicker::make('start_time')
                    ->required()
                    ->default('08:00')
                    ->format('H:i:s')
                    ->seconds(false)
                    ->label('Start Tijd'),
                TimePicker::make('end_time')
                    ->required()
                    ->format('H:i:s')
                    ->seconds(false)
                    ->label('Eind Tijd'),
                TimePicker::make('break_duration')
                    ->required()
                    ->default('01:00')
                    ->format('H:i:s')
                    ->seconds(false)
                    ->label('Pauze'),
                TimePicker::make('travel_time')  // Nieuw veld
                    ->format('H:i:s')
                    ->seconds(false)
                    ->default('00:00')
                    ->label('Reistijd'),
                Textarea::make('description')
                    ->rows(3)
                    ->label('Opmerkingen')
            ]);
    }

    public function table(Table $table): Table
    {
        $baseQuery = TimeEntry::query()->where('workorder_id', $this->workOrder->id);

        // Check if we have any active period filters
        $filters = $this->getTableFilters();
        $hasPeriodFilter = isset($filters['day']['selected_date']) ||
            isset($filters['week']['week_start']) ||
            isset($filters['month']['value']);

        if ($hasPeriodFilter) {
            // Use a subquery to get user totals
            $baseQuery = TimeEntry::query()
                ->where('workorder_id', $this->workOrder->id)
                ->select([
                    'user_id',
                    DB::raw('MIN(date) as date'),
                    DB::raw('SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time)) - TIME_TO_SEC(break_duration))) as total_time')
                ])
                ->groupBy('user_id');
        }
        return $table
            ->query($baseQuery)
            ->emptyStateHeading('Geen tijdregistraties gevonden')
            ->defaultSort('date', 'desc')
            ->headerActions([
                Action::make('register_time')
                    ->label('Tijd Registreren')
                    ->button()
                    ->modalWidth('lg')
                    ->form($this->form(...))
                    ->action(function (array $data): void {
                        $startTime = Carbon::parse($data['start_time'])->format('H:i:s');
                        $endTime = Carbon::parse($data['end_time'])->format('H:i:s');
                        $breakDuration = Carbon::parse($data['break_duration'])->format('H:i:s');
                        $travelTime = Carbon::parse($data['travel_time'])->format('H:i:s');

                        TimeEntry::create([
                            'workorder_id' => $this->workOrder->id,
                            'user_id' => auth()->id(),
                            'date' => $data['date'],
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'break_duration' => $breakDuration,
                            'travel_time' => $travelTime,
                            'description' => $data['description'],
                        ]);

                        $this->updatedTableFilters($this->getTableFilters());

                        Notification::make()
                            ->title('Tijd geregistreerd')
                            ->success()
                            ->send();
                    }),
                Action::make('export_excel')
                    ->label('Exporteer Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn() => auth()->user()->can('export', TimeEntry::class))
                    ->action(function () {
                        return Excel::download(
                            new WorkOrderTimeSheetExport(
                                $this->workOrder,
                                $this->getTableFilters()
                            ),
                            "werkorder-{$this->workOrder->id}-tijdregistraties.xlsx"
                        );
                    }),

                Action::make('export_pdf')
                    ->label('Exporteer PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->size('sm')
                    ->action(function () {
                        // Generate PDF using your preferred package
                        // For example, using barryvdh/laravel-dompdf
                        $pdf = PDF::loadView('exports.timesheet', [
                            'workOrder' => $this->workOrder,
                            'timeEntries' => (new WorkOrderTimeSheetExport(
                                $this->workOrder,
                                $this->getTableFilters()
                            ))->collection()
                        ]);

                        return response()->streamDownload(
                            fn() => print($pdf->output()),
                            "werkorder-{$this->workOrder->id}-tijdregistraties.pdf"
                        );
                    }),
            ])
            ->columns([
                TextColumn::make('user.name')
                    ->label('Gebruiker')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Datum')
                    ->date()
                    ->sortable()
                    ->visible(!$hasPeriodFilter),
                TextColumn::make('start_time')
                    ->label('Start Tijd')
                    ->time()
                    ->sortable()
                    ->visible(!$hasPeriodFilter),
                TextColumn::make('end_time')
                    ->label('Eind Tijd')
                    ->time()
                    ->sortable()
                    ->visible(!$hasPeriodFilter),
                TextColumn::make('break_duration')
                    ->label('Pauze')
                    ->time()
                    ->sortable()
                    ->visible(!$hasPeriodFilter),
                TextColumn::make('travel_time')  // Nieuwe kolom
                    ->label('Reistijd')
                    ->time()
                    ->sortable()
                    ->visible(!$hasPeriodFilter),
                TextColumn::make('description')
                    ->label('Beschrijving')
                    ->searchable()
                    ->visible(!$hasPeriodFilter),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label('Gebruiker')
                    ->options(function () {
                        return \App\Models\User::pluck('name', 'id')->toArray();
                    })
                    ->query(function ($query, $data): mixed {
                        return $query->when(
                            $data['value'],
                            fn($query, $userId) => $query->where('user_id', $userId)
                        );
                    }),

                Filter::make('day')
                    ->form([
                        DatePicker::make('selected_date')
                            ->label('Dag')
                            ->displayFormat('d M Y')
                            ->native(false)
                    ])
                    ->query(function ($query, array $data) {
                        if (!isset($data['selected_date'])) {
                            return $query;
                        }

                        $this->activePeriod = 'day';
                        $query = $query->whereDate('date', Carbon::parse($data['selected_date']));
                        $this->calculatePeriodTotal($query);
                        return $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!isset($data['selected_date'])) {
                            return null;
                        }

                        return 'Dag: ' . Carbon::parse($data['selected_date'])->format('d M Y');
                    }),

                Filter::make('week')
                    ->form([
                        DatePicker::make('week_start')
                            ->label('Week')
                            ->default(now()->startOfWeek())
                            ->displayFormat('d M Y')
                            ->firstDayOfWeek(1)
                            ->closeOnDateSelection()
                            ->native(false)
                            ->dehydrateStateUsing(fn($state) => $state ? Carbon::parse($state)->startOfWeek()->format('Y-m-d') : null)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $weekStart = Carbon::parse($state)->startOfWeek();
                                    $set('week_start', $weekStart->format('Y-m-d'));
                                }
                            })
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (!isset($data['week_start'])) {
                            return null;
                        }

                        $weekStart = Carbon::parse($data['week_start'])->startOfWeek();
                        $weekEnd = $weekStart->copy()->endOfWeek();

                        return 'Week ' . $weekStart->weekOfYear . ': ' .
                            $weekStart->format('d M') . ' - ' .
                            $weekEnd->format('d M Y');
                    })
                    ->query(function ($query, array $data) {
                        if (!isset($data['week_start'])) {
                            return $query;
                        }

                        $this->activePeriod = 'week';
                        $weekStart = Carbon::parse($data['week_start'])->startOfWeek();
                        $weekEnd = $weekStart->copy()->endOfWeek();

                        $query = $query->whereBetween('date', [
                            $weekStart->format('Y-m-d'),
                            $weekEnd->format('Y-m-d'),
                        ]);

                        $this->calculatePeriodTotal($query);
                        return $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!isset($data['week_start'])) {
                            return null;
                        }

                        $weekStart = Carbon::parse($data['week_start'])->startOfWeek();
                        $weekEnd = $weekStart->copy()->endOfWeek();

                        return 'Week ' . $weekStart->weekOfYear . ': ' .
                            $weekStart->format('d M') . ' - ' .
                            $weekEnd->format('d M Y');
                    }),

                SelectFilter::make('month')
                    ->label('Maand')
                    ->options(function () {
                        $months = collect(CarbonPeriod::create(
                            now()->subMonths(12)->startOfMonth(),
                            '1 month',
                            now()->endOfMonth()
                        ))->mapWithKeys(fn($date) => [
                            $date->format('Y-m') => $date->format('F Y')
                        ])->toArray();

                        return $months;
                    })
                    ->query(function ($query, $data): mixed {
                        try {
                            if (!empty($data['value'])) {
                                $this->activePeriod = 'month';
                                $date = Carbon::createFromFormat('Y-m', $data['value']);
                                $query = $query->whereMonth('date', $date->month)
                                    ->whereYear('date', $date->year);
                                $this->calculatePeriodTotal($query);
                                return $query;
                            }
                        } catch (\Exception $e) {
                        }

                        return $query;
                    })
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Action::make('edit')
                    ->label('Bekijk')
                    ->icon('heroicon-o-eye')
                    ->size('sm')
                    ->color('gray')
                    ->modalWidth('lg'),

                Action::make('edit')
                    ->label('Bewerk')
                    ->icon('heroicon-o-pencil-square')
                    ->size('sm')
                    ->color('warning')
                    ->modalWidth('lg')
                    ->form(function (TimeEntry $record) {
                        return [
                            DatePicker::make('date')
                                ->required()
                                ->format('Y-m-d')
                                ->label('Datum')
                                ->default($record->date),
                            TimePicker::make('start_time')
                                ->required()
                                ->format('H:i:s')
                                ->seconds(false)
                                ->label('Start Tijd')
                                ->default($record->start_time),
                            TimePicker::make('end_time')
                                ->required()
                                ->format('H:i:s')
                                ->seconds(false)
                                ->label('Eind Tijd')
                                ->default($record->end_time),
                            TimePicker::make('break_duration')
                                ->required()
                                ->format('H:i:s')
                                ->seconds(false)
                                ->label('Pauze')
                                ->default($record->break_duration),
                            TimePicker::make('travel_time')
                                ->format('H:i:s')
                                ->seconds(false)
                                ->label('Reistijd')
                                ->default($record->travel_time),
                            Textarea::make('description')
                                ->rows(3)
                                ->label('Opmerkingen')
                                ->default($record->description)
                        ];
                    })
                    ->action(function (TimeEntry $record, array $data): void {
                        $record->update([
                            'date' => $data['date'],
                            'start_time' => Carbon::parse($data['start_time'])->format('H:i:s'),
                            'end_time' => Carbon::parse($data['end_time'])->format('H:i:s'),
                            'break_duration' => Carbon::parse($data['break_duration'])->format('H:i:s'),
                            'travel_time' => Carbon::parse($data['travel_time'])->format('H:i:s'),
                            'description' => $data['description'],
                        ]);

                        $this->updatedTableFilters($this->getTableFilters());

                        Notification::make()
                            ->title('Tijd aangepast')
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->label('Verwijder')
                    ->icon('heroicon-o-trash')
                    ->size('sm')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Verwijder tijdregistratie')
                    ->modalDescription('Weet je zeker dat je deze tijdregistratie wilt verwijderen?')
                    ->modalSubmitActionLabel('Ja, verwijder')
                    ->modalCancelActionLabel('Annuleren')
                    ->action(function (TimeEntry $record): void {
                        $record->delete();

                        $this->updatedTableFilters($this->getTableFilters());

                        Notification::make()
                            ->title('Tijd verwijderd')
                            ->success()
                            ->send();
                    })
            ])
            ->bulkActions([]);
    }

    public function render()
    {
        return view('livewire.list-work-order-time-sheet');
    }
}

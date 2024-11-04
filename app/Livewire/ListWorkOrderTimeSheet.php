<?php

namespace App\Livewire;

use App\Models\TimeEntry;
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
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class ListWorkOrderTimeSheet extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public $workOrder;
    public $isOpen = false;

    public function mount($workOrder)
    {
        $this->workOrder = $workOrder;
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
                Textarea::make('description')
                    ->rows(3)
                    ->label('Opmerkingen')
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TimeEntry::query()->where('workorder_id', $this->workOrder->id))
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

                        TimeEntry::create([
                            'workorder_id' => $this->workOrder->id,
                            'user_id' => auth()->id(),
                            'date' => $data['date'],
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'break_duration' => $breakDuration,
                            'description' => $data['description'],
                        ]);

                        Notification::make()
                            ->title('Tijd geregistreerd')
                            ->success()
                            ->send();
                    })
            ])
            ->columns([
                TextColumn::make('user.name')
                    ->label('Gebruiker')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Datum')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Start Tijd')
                    ->time()
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('Eind Tijd')
                    ->time()
                    ->sortable(),
                TextColumn::make('break_duration')
                    ->label('Pauze')
                    ->time()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Beschrijving')
                    ->searchable(),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                // Edit and delete actions if needed
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function render()
    {
        return view('livewire.list-work-order-time-sheet');
    }
}

<?php

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Filament\Resources\WorkOrderResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('registerTime')
                ->label('Register Time')
                ->icon('heroicon-o-clock')
                ->modalHeading('Register Time for Work Order')
                ->modalDescription('Register your worked time for this work order')
                ->modalIcon('heroicon-o-clock')
                ->modalSubmitActionLabel('Register Time')
                ->form([
                    DateTimePicker::make('start_time')
                        ->label('Start Time')
                        ->required()
                        ->default(now())
                        ->native(false),
                    DateTimePicker::make('end_time')
                        ->label('End Time')
                        ->native(false)
                        ->after('start_time'),
                    Select::make('user_id')
                        ->label('Employee')
                        ->options(User::pluck('name', 'id'))
                        ->default(auth()->id())
                        ->required()
                        ->visible(fn() => auth()->user()->hasRole('admin')),
                    Textarea::make('description')
                        ->label('Description')
                        ->placeholder('What did you work on?')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data, \App\Models\WorkOrder $record): void {
                    $record->timeEntries()->create([
                        'user_id' => $data['user_id'] ?? auth()->id(),
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'description' => $data['description'],
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Time registered successfully')
                        ->send();
                }),
        ];
    }
}

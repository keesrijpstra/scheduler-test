<?php

namespace App\Filament\Resources;

use App\Enums\StatusType;
use App\Filament\Resources\WorkOrderResource\Pages;
use App\Filament\Resources\WorkOrderResource\RelationManagers;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Forms\Components\RichEditor::make('description')
                    ->columnSpanFull(),

                Forms\Components\Select::make('assignedUsers')
                    ->relationship('assignedUsers', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Toegewezen aan')
                    ->columnSpanFull(),

                Forms\Components\ToggleButtons::make('status')
                    ->options(StatusType::class)
                    ->default(StatusType::Open)
                    ->columnSpanFull()
                    ->inline()
                    ->required(),

                Forms\Components\DateTimePicker::make('start_date')
                    ->label('Start Datum')
                    ->native(false),

                Forms\Components\DateTimePicker::make('due_date')
                    ->label('Eind Datum')
                    ->native(false)
                    ->after('start_date'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Work Order Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('title')
                                    ->columnSpan(2),
                                TextEntry::make('status')
                                    ->badge()
                            ]),
                        TextEntry::make('description')
                            ->html()
                            ->columnSpanFull(),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('creator.name')
                                    ->label('Created By'),

                                TextEntry::make('start_date')
                                    ->dateTime(),

                                TextEntry::make('due_date')
                                    ->dateTime(),
                            ]),
                    ]),
                Section::make('Tijdregistraties')
                    ->schema([
                        \Filament\Infolists\Components\View::make('time-entries')
                            ->view('components.timesheet-table')
                    ]),
                Section::make('System Information')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('description')
                    ->html()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): string {
                        return strip_tags($column->getState());
                    })
                    ->toggleable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(StatusType::class)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('created_by')
                    ->searchable()
                    ->preload()
                    ->label('Created By'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkOrders::route('/'),
            'create' => Pages\CreateWorkOrder::route('/create'),
            'edit' => Pages\EditWorkOrder::route('/{record}/edit'),
            'view' => Pages\ViewWorkOrder::route('/view/work-order/{record}'),
        ];
    }
}

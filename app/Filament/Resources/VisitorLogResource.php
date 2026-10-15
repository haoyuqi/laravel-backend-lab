<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorLogResource\Pages\ListVisitorLogs;
use App\Models\VisitorLog;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VisitorLogResource extends Resource
{
    protected static ?string $model = VisitorLog::class;

    protected static string|\UnitEnum|null $navigationGroup = '访客';

    protected static ?string $navigationLabel = '访客日志';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = '访客日志';

    protected static ?string $pluralModelLabel = '访客日志';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('visitor_id')
                    ->required()
                    ->numeric(),
                TextInput::make('url')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visitor.ip')
                    ->label('IP')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('url')
                    ->label('URL')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitorLogs::route('/'),
        ];
    }
}

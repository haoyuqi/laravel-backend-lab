<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlackListLogResource\Pages;
use App\Models\BlackListLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BlackListLogResource extends Resource
{
    protected static ?string $model = BlackListLog::class;

    protected static ?string $navigationGroup = '黑名单';

    protected static ?string $navigationLabel = '拦截日志';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = '拦截日志';

    protected static ?string $pluralModelLabel = '拦截日志';

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['blackList']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('url')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('blackList.ip')
                    ->label('IP')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('拦截时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->label('拦截时间')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('开始日期'),
                        Forms\Components\DatePicker::make('created_until')->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay()),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([]);
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
            'index' => Pages\ListBlackListLogs::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlackListResource\Pages;
use App\Models\BlackList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BlackListResource extends Resource
{
    protected static ?string $model = BlackList::class;

    protected static ?string $navigationGroup = '黑名单';

    protected static ?string $navigationLabel = '黑名单列表';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = '黑名单';

    protected static ?string $pluralModelLabel = '黑名单列表';

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'logs as all_logs_count',
                'logs as today_logs_count' => fn (Builder $query) => $query->where('created_at', '>=', today()->startOfDay()),
            ])
            ->with(['city']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ip')
                    ->label('IP')
                    ->required()
                    ->ip()
                    ->unique(BlackList::class, 'ip', ignoreRecord: true)
                    ->maxLength(45),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.city')
                    ->label('城市')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('today_logs_count')
                    ->label('今日拦截量')
                    ->sortable(),
                Tables\Columns\TextColumn::make('all_logs_count')
                    ->label('历史拦截量')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('添加时间')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('最后拦截时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->label('添加时间')
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
            ->actions([
                Tables\Actions\Action::make('view_logs')
                    ->label('查看')
                    ->icon('heroicon-o-eye')
                    ->url(fn (BlackList $record): string => BlackListLogResource::getUrl('index', [
                        'tableSearch' => $record->ip,
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlackLists::route('/'),
            'create' => Pages\CreateBlackList::route('/create'),
            'edit' => Pages\EditBlackList::route('/{record}/edit'),
        ];
    }
}

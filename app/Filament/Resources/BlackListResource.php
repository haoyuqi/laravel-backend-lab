<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlackListResource\Pages\CreateBlackList;
use App\Filament\Resources\BlackListResource\Pages\EditBlackList;
use App\Filament\Resources\BlackListResource\Pages\ListBlackLists;
use App\Models\BlackList;
use App\Service\AdminBlackListOperations;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class BlackListResource extends Resource
{
    protected static ?string $model = BlackList::class;

    protected static string|\UnitEnum|null $navigationGroup = '黑名单';

    protected static ?string $navigationLabel = '黑名单列表';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = '黑名单';

    protected static ?string $pluralModelLabel = '黑名单列表';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'logs as all_logs_count',
                'logs as today_logs_count' => fn (Builder $query) => $query->where('created_at', '>=', today()->startOfDay()),
            ])
            ->with(['city']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ip')
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
                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city.city')
                    ->label('城市')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('today_logs_count')
                    ->label('今日拦截量')
                    ->sortable(),
                TextColumn::make('all_logs_count')
                    ->label('历史拦截量')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('添加时间')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('最后拦截时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('添加时间')
                    ->schema([
                        DatePicker::make('created_from')->label('开始日期'),
                        DatePicker::make('created_until')->label('结束日期'),
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
            ->recordActions([
                Action::make('view_logs')
                    ->label('查看')
                    ->icon('heroicon-o-eye')
                    ->url(fn (BlackList $record): string => BlackListLogResource::getUrl('index', [
                        'tableSearch' => $record->ip,
                    ])),
                EditAction::make(),
                DeleteAction::make()->using(fn (BlackList $record): bool => app(AdminBlackListOperations::class)->delete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->using(function (DeleteBulkAction $action, Collection $records): void {
                        $isFirstException = true;
                        foreach ($records as $record) {
                            try {
                                app(AdminBlackListOperations::class)->delete($record);
                            } catch (Throwable $exception) {
                                $action->reportBulkProcessingFailure();
                                if ($isFirstException) {
                                    report($exception);
                                    $isFirstException = false;
                                }
                            }
                        }
                    }),
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
            'index' => ListBlackLists::route('/'),
            'create' => CreateBlackList::route('/create'),
            'edit' => EditBlackList::route('/{record}/edit'),
        ];
    }
}

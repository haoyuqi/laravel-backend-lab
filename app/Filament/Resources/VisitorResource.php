<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VisitorResource\Pages;
use App\Models\BlackList;
use App\Models\Visitor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class VisitorResource extends Resource
{
    protected static ?string $model = Visitor::class;

    protected static ?string $navigationGroup = '访客';

    protected static ?string $navigationLabel = '访客列表';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = '访客';

    protected static ?string $pluralModelLabel = '访客列表';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'logs as all_logs_count',
                'logs as today_logs_count' => fn (Builder $query) => $query->where('created_at', '>=', today()->startOfDay()),
            ])
            ->with(['blackList']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ip')
                    ->required(),
                Forms\Components\TextInput::make('city')
                    ->maxLength(50),
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
                Tables\Columns\TextColumn::make('city')
                    ->label('城市')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('today_logs_count')
                    ->label('今日访问量')
                    ->sortable(),
                Tables\Columns\TextColumn::make('all_logs_count')
                    ->label('历史访问量')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_blacklisted')
                    ->label('黑名单')
                    ->state(fn (Visitor $record): bool => $record->blackList !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('首次访问')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('最后访问')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->label('首次访问时间')
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
                Tables\Filters\TernaryFilter::make('black_list')
                    ->label('是否在黑名单')
                    ->queries(
                        true: fn (Builder $query) => $query->has('blackList'),
                        false: fn (Builder $query) => $query->doesntHave('blackList'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('view_logs')
                    ->label('查看')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Visitor $record): string => VisitorLogResource::getUrl('index', [
                        'tableSearch' => $record->ip,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('add_to_black_list')
                    ->label('加入黑名单')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        foreach ($records as $record) {
                            $blackList = BlackList::withTrashed()->firstOrNew(['ip' => $record->ip]);
                            if ($blackList->trashed()) {
                                $blackList->restore();
                            } elseif (! $blackList->exists) {
                                $blackList->save();
                            }
                        }

                        Notification::make()
                            ->title('已成功加入黑名单')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
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
            'index' => Pages\ListVisitors::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminOperationLogResource\Pages\ListAdminOperationLogs;
use App\Models\AdminOperationLog;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AdminOperationLogResource extends Resource
{
    protected static ?string $model = AdminOperationLog::class;

    protected static string|\UnitEnum|null $navigationGroup = '系统';

    protected static ?string $navigationLabel = '操作日志';

    protected static ?string $modelLabel = '操作日志';

    protected static ?string $pluralModelLabel = '操作日志';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function canViewAny(): bool
    {
        $panel = Filament::getPanel('admin');
        $actor = $panel->auth()->user();

        return $actor instanceof User && $actor->canAccessPanel($panel);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('actor_name')->label('操作人')->searchable(),
            TextColumn::make('actor_id')->label('用户 ID'),
            TextColumn::make('action')->label('操作')->badge()
                ->formatStateUsing(fn (string $state): string => self::actions()[$state] ?? $state),
            TextColumn::make('subject_type')->label('资源')
                ->formatStateUsing(fn (?string $state): string => self::subjects()[$state] ?? $state ?? '认证'),
            TextColumn::make('subject_id')->label('记录 ID'),
            TextColumn::make('action_key')->label('入口')->limit(60)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('before')->label('变更前')
                ->state(fn (AdminOperationLog $record): string => self::snapshotLabel($record->before)),
            TextColumn::make('after')->label('变更后')
                ->state(fn (AdminOperationLog $record): string => self::snapshotLabel($record->after)),
            TextColumn::make('occurred_at')->label('操作时间')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('action')->label('操作')->options(self::actions()),
            SelectFilter::make('subject_type')->label('资源')->options(self::subjects()),
            Filter::make('occurred_at')->label('操作时间')->schema([
                DatePicker::make('from')->label('开始日期'),
                DatePicker::make('until')->label('结束日期'),
            ])->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->where('occurred_at', '>=', Carbon::parse($date)->startOfDay()))
                ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->where('occurred_at', '<=', Carbon::parse($date)->endOfDay()))),
        ])->defaultSort('id', 'desc')->recordActions([])->toolbarActions([]);
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

    public static function getPages(): array
    {
        return ['index' => ListAdminOperationLogs::route('/')];
    }

    private static function actions(): array
    {
        return ['create' => '添加', 'update' => '修改', 'delete' => '删除', 'restore' => '恢复', 'login' => '登录', 'logout' => '退出'];
    }

    private static function subjects(): array
    {
        return collect(config('admin-operation-logs.models', []))->mapWithKeys(fn (array $policy): array => [$policy['type'] => $policy['label'] ?? $policy['type']])->all();
    }

    private static function snapshotLabel(?array $snapshot): string
    {
        if ($snapshot === null) {
            return '—';
        }

        return Str::limit(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 500);
    }
}

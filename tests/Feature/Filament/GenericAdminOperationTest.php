<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\CreateRecord;
use App\Filament\Pages\EditRecord;
use App\Filament\Pages\ListRecords;
use App\Models\AdminOperationLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class GenericAdminOperationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        // A second real resource exists only inside this test; no production User CRUD.
        Route::name('filament.admin.')->prefix('admin')->group(fn () => AuditUserResource::registerRoutes($panel));
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_second_resource_create_and_update_need_no_resource_specific_audit_service(): void
    {
        Livewire::test(AuditCreateUser::class)->fillForm(['name' => 'Before', 'email' => 'fixture@example.com', 'password' => 'fixture password'])->call('create')->assertHasNoFormErrors();
        $record = User::where('email', 'fixture@example.com')->sole();
        Livewire::test(AuditEditUser::class, ['record' => $record->id])->fillForm(['name' => 'After'])->call('save', false)->assertHasNoFormErrors();
        $logs = AdminOperationLog::orderBy('id')->get();
        $this->assertSame(['create', 'update'], $logs->pluck('action')->all());
        $this->assertSame(['user', 'user'], $logs->pluck('subject_type')->all());
        $this->assertSame(['name' => 'Before'], $logs[0]->after);
        $this->assertSame(['name' => 'After'], $logs[1]->after);
    }

    public function test_partial_form_save_uses_the_generic_context_and_transaction(): void
    {
        $record = User::factory()->create(['name' => 'Before']);
        $page = Livewire::test(AuditEditUser::class, ['record' => $record->id])->fillForm(['name' => 'Partial']);
        $component = $page->instance()->getSchema('form')->getComponentByStatePath('name');
        $page->instance()->saveFormComponentOnly($component);
        $this->assertSame('Partial', $record->fresh()->name);
        $this->assertSame(['name' => 'Partial'], AdminOperationLog::sole()->after);
        $this->assertStringEndsWith('.save_component', AdminOperationLog::sole()->action_key);
    }

    public function test_custom_action_is_audited_and_read_only_action_is_not(): void
    {
        $record = User::factory()->create(['name' => 'Before']);
        Livewire::test(AuditListUsers::class)->callTableAction('rename', $record);
        $this->assertSame('Custom action', $record->fresh()->name);
        $this->assertSame(['name' => 'Custom action'], AdminOperationLog::sole()->after);
        Livewire::test(AuditListUsers::class)->callTableAction('inspect', $record);
        $this->assertDatabaseCount('admin_operation_logs', 1);
    }

    public function test_all_production_resource_pages_use_the_audited_common_pages(): void
    {
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            foreach ($resource::getPages() as $registration) {
                $class = $registration->getPage();
                $this->assertTrue(is_subclass_of($class, CreateRecord::class) || is_subclass_of($class, EditRecord::class) || is_subclass_of($class, ListRecords::class), $class.' must adopt the common audited page.');
            }
        }
    }
}

class AuditUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'audit-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required(), TextInput::make('email'), TextInput::make('password')->dehydrated(fn ($state): bool => filled($state))]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')])->recordActions([
            Action::make('rename')->action(fn (User $record) => $record->update(['name' => 'Custom action'])),
            Action::make('inspect')->action(fn (User $record) => $record->name),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => AuditListUsers::route('/'), 'create' => AuditCreateUser::route('/create'), 'edit' => AuditEditUser::route('/{record}/edit')];
    }
}

class AuditCreateUser extends CreateRecord
{
    protected static string $resource = AuditUserResource::class;
}

class AuditEditUser extends EditRecord
{
    protected static string $resource = AuditUserResource::class;
}

class AuditListUsers extends ListRecords
{
    protected static string $resource = AuditUserResource::class;
}

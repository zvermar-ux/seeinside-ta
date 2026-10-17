<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Http\Middleware\SetLocale;
use App\Models\Agency;
use App\Models\User;
use App\Support\Permissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    use GuardedByPermission {
        can as protected permissionCan;
    }

    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function permissionModule(): string
    {
        return 'users';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('User'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        // Nobody but a super admin may touch a super admin account
        if ($record instanceof User && $record->isSuperAdmin() && ! static::isSuperAdmin()) {
            return false;
        }
        // Users can't delete themselves
        if ($action === 'delete' && $record?->getKey() === Auth::id()) {
            return false;
        }

        return static::permissionCan($action, $record);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['roles', 'agency']);

        if (! static::isSuperAdmin()) {
            $query->where('agency_id', Auth::user()->agency_id);
        }

        return $query;
    }

    /** Roles the current user is allowed to hand out */
    public static function assignableRoles(): array
    {
        $roles = [
            Permissions::AGENCY_ADMIN => Permissions::roleLabel(Permissions::AGENCY_ADMIN),
            Permissions::OPERATOR => Permissions::roleLabel(Permissions::OPERATOR),
        ];

        if (static::isSuperAdmin()) {
            $roles = [Permissions::SUPER_ADMIN => Permissions::roleLabel(Permissions::SUPER_ADMIN)] + $roles;
        }

        return $roles;
    }

    /** Permissions the current user is allowed to hand out */
    public static function assignablePermissions(): array
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return Permissions::all();
        }

        return array_values(array_intersect(Permissions::all(), $user->getAllPermissions()->pluck('name')->all()));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Account'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label(__('Full name'))->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->label(__('E-mail'))->email()->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('phone')->label(__('Phone'))->tel(),
                    Forms\Components\TextInput::make('password')->label(__('Password'))
                        ->password()->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? __('Leave empty to keep the current password.') : null),
                    Forms\Components\Select::make('locale')->label(__('Language'))
                        ->options(SetLocale::LOCALES)->default('mk')->required(),
                    Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true)->inline(false),
                ]),
            Forms\Components\Section::make(__('Role & permissions'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('role')->label(__('Role'))
                        ->options(fn () => static::assignableRoles())
                        ->default(Permissions::OPERATOR)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $set('permission_names', array_values(array_intersect(Permissions::roleDefaults((string) $state), static::assignablePermissions())));
                        }),
                    Forms\Components\Select::make('agency_id')->label(__('Agency'))
                        ->options(fn () => Agency::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->default(fn () => request()->integer('agency') ?: null)
                        ->required(fn (Get $get) => $get('role') !== Permissions::SUPER_ADMIN)
                        ->visible(fn () => static::isSuperAdmin())
                        ->hidden(fn (Get $get) => $get('role') === Permissions::SUPER_ADMIN),
                    Forms\Components\CheckboxList::make('permission_names')->label(__('Permissions'))
                        ->options(fn () => Permissions::options(static::assignablePermissions()))
                        ->default(Permissions::roleDefaults(Permissions::OPERATOR))
                        ->columns(2)->gridDirection('row')
                        ->bulkToggleable()
                        ->columnSpanFull()
                        ->hidden(fn (Get $get) => $get('role') === Permissions::SUPER_ADMIN)
                        ->helperText(__('The super admin always has all permissions.')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Full name'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (User $r) => $r->email),
                Tables\Columns\TextColumn::make('agency.name')->label(__('Agency'))->sortable()
                    ->placeholder(__('All agencies'))
                    ->visible(fn () => static::isSuperAdmin()),
                Tables\Columns\TextColumn::make('role')->label(__('Role'))->badge()
                    ->state(fn (User $r) => Permissions::roleLabel($r->primaryRole()))
                    ->color(fn (User $r) => match ($r->primaryRole()) {
                        Permissions::SUPER_ADMIN => 'danger',
                        Permissions::AGENCY_ADMIN => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('locale')->label(__('Language'))->badge()->color('gray')->formatStateUsing(fn ($state) => strtoupper((string) $state))->toggleable(),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active'))
                    ->disabled(fn (User $r) => $r->getKey() === Auth::id()),
                Tables\Columns\TextColumn::make('last_login_at')->label(__('Last login'))->since()->sortable()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('agency_id')->label(__('Agency'))
                    ->relationship('agency', 'name')->searchable()->preload()
                    ->visible(fn () => static::isSuperAdmin()),
                Tables\Filters\SelectFilter::make('role')->label(__('Role'))
                    ->options(fn () => static::assignableRoles())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->role($data['value']) : $query),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

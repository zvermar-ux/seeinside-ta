<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SubagentResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Subagent;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubagentResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Subagent::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'partners';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'company';

    public static function permissionModule(): string
    {
        return 'subagents';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Subagent'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Subagents');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['company', 'contact_person', 'email', 'username'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                AgencyField::select(),
                Forms\Components\TextInput::make('company')->label(__('Company'))->required(),
                Forms\Components\TextInput::make('contact_person')->label(__('Contact person')),
                Forms\Components\TextInput::make('username')->label(__('Username')),
                Forms\Components\TextInput::make('email')->label(__('E-mail'))->email(),
                Forms\Components\TextInput::make('phone')->label(__('Phone'))->tel(),
                Forms\Components\TextInput::make('commission_percent')->label(__('Commission'))->numeric()->suffix('%'),
                Forms\Components\Toggle::make('has_access')->label(__('Access allowed')),
                Forms\Components\Toggle::make('no_balance_limit')->label(__('No balance limit')),
                Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company')->label(__('Company'))->searchable()->sortable()->weight('semibold')
                    ->description(fn (Subagent $r) => $r->contact_person),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('username')->label(__('Username'))->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('email')->label(__('E-mail'))->searchable()->copyable()->icon('heroicon-m-envelope'),
                Tables\Columns\TextColumn::make('phone')->label(__('Phone'))->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('has_access')->label(__('Access')),
                Tables\Columns\ToggleColumn::make('no_balance_limit')->label(__('No balance limit')),
            ])
            ->filters([
                AgencyField::filter(),
                Tables\Filters\TernaryFilter::make('has_access')->label(__('Access allowed')),
                Tables\Filters\TernaryFilter::make('no_balance_limit')->label(__('No balance limit')),
            ])
            ->actions([
                Tables\Actions\Action::make('mail')->label(__('E-mail'))->icon('heroicon-o-envelope')->color('gray')->iconButton()->tooltip(__('E-mail'))
                    ->url(fn (Subagent $r) => $r->email ? 'mailto:'.$r->email : null)
                    ->visible(fn (Subagent $r) => filled($r->email)),
                Tables\Actions\EditAction::make(),
                Duplicate::action(null, ['external_company_id']),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('allow')->label(__('Allow access'))->icon('heroicon-o-lock-open')
                        ->action(fn ($records) => Subagent::whereKey($records->modelKeys())->update(['has_access' => true]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deny')->label(__('Deny access'))->icon('heroicon-o-lock-closed')
                        ->action(fn ($records) => Subagent::whereKey($records->modelKeys())->update(['has_access' => false]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('company');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubagents::route('/'),
            'create' => Pages\CreateSubagent::route('/create'),
            'edit' => Pages\EditSubagent::route('/{record}/edit'),
        ];
    }
}

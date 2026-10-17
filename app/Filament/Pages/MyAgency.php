<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AgencyResource;
use App\Models\Agency;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MyAgency extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'administration';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.my-agency';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('My agency');
    }

    public function getTitle(): string
    {
        return Auth::user()->agency?->name ?? __('My agency');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        // Super admins manage every agency through "Agencies"
        return $user && ! $user->isSuperAdmin() && $user->agency_id && $user->can('agency.view');
    }

    protected function canEdit(): bool
    {
        return Auth::user()->can('agency.manage');
    }

    protected function agency(): Agency
    {
        return Agency::findOrFail(Auth::user()->agency_id);
    }

    public function mount(): void
    {
        $this->form->fill($this->agency()->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(AgencyResource::formSchema(withAdminFields: false))
            ->statePath('data')
            ->disabled(fn () => ! $this->canEdit());
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label(__('Save changes'))->submit('save')->visible(fn () => $this->canEdit()),
        ];
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        $data = $this->form->getState();
        unset($data['is_active'], $data['notes'], $data['slug']);

        $this->agency()->update($data);

        Notification::make()->success()->title(__('Saved'))->send();
    }
}

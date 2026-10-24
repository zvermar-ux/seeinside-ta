<?php

namespace App\Filament\Pages;

use App\Models\Agency;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/** Contract, invoice and cash register settings of an agency (texts, stamp, exchange rate, VAT, bank account) */
class DocumentSettings extends Page implements HasForms
{
    use InteractsWithForms, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'document-settings';

    protected static string $view = 'filament.pages.document-settings';

    public ?array $data = [];

    #[Url]
    public ?int $agency = null;

    public static function defaultNavigationLabel(): string
    {
        return __('Documents & exchange rate');
    }

    public function getTitle(): string
    {
        return __('Documents & exchange rate');
    }

    public static function canAccess(): bool
    {
        if (! \App\Models\Agency::moduleOn('finance')) {
            return false;
        }
        $u = Auth::user();

        return (bool) ($u && ($u->isSuperAdmin() || ($u->agency_id && $u->can('agency.manage'))));
    }

    protected function agencyModel(): ?Agency
    {
        $u = Auth::user();
        $id = $u->isSuperAdmin() ? ($this->agency ?: Agency::query()->orderBy('name')->value('id')) : $u->agency_id;

        return $id ? Agency::find($id) : null;
    }

    public function mount(): void
    {
        $a = $this->agencyModel();
        $this->agency = $a?->id;
        $d = (array) ($a?->documents ?? []);
        $regs = collect((array) ($d['registers'] ?? ['main' => 'Главна благајна']))->map(fn ($name, $key) => ['key' => $key, 'name' => $name])->values()->all();
        $this->form->fill([
            'eur_rate' => $a?->doc('eur_rate'), 'base_currency' => $a?->doc('base_currency'), 'vat_rate' => $a?->doc('vat_rate'), 'invoice_days' => $a?->doc('invoice_days'),
            'bank_account' => $a?->doc('bank_account'), 'bank_name' => $a?->doc('bank_name'), 'license' => $a?->doc('license'),
            'payment_clause' => $a?->doc('payment_clause'), 'terms_clause' => $a?->doc('terms_clause'), 'declaration' => $a?->doc('declaration'),
            'general_terms' => $a?->doc('general_terms'), 'invoice_text' => $a?->doc('invoice_text'), 'invoice_footer' => $a?->doc('invoice_footer'),
            'stamp' => $a?->doc('stamp'), 'registers' => $regs, 'payment_due_days' => $a?->doc('payment_due_days'), 'deposit_percent' => $a?->doc('deposit_percent'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make(__('Exchange rate & taxes'))->columns(4)->schema([
                Forms\Components\TextInput::make('eur_rate')->label(__('EUR rate (1 EUR = … MKD)'))->numeric()->required()->step(0.0001)
                    ->helperText(__('Used for "in denars" amounts in contracts, payments in MKD and cash documents.')),
                Forms\Components\Select::make('base_currency')->label(__('Base currency'))->options(['MKD' => 'MKD', 'EUR' => 'EUR'])->selectablePlaceholder(false),
                Forms\Components\TextInput::make('vat_rate')->label(__('Default VAT on invoices'))->numeric()->suffix('%'),
                Forms\Components\TextInput::make('invoice_days')->label(__('Invoice payment term'))->numeric()->suffix(__('days')),
                Forms\Components\TextInput::make('payment_due_days')->label(__('Final payment: days before travel'))->numeric()->helperText(__('Suggested payment deadline on new bookings'))->columnSpan(2),
                Forms\Components\TextInput::make('deposit_percent')->label(__('Deposit when booking'))->numeric()->suffix('%')->columnSpan(2),
            ]),
            Forms\Components\Section::make(__('Bank & cash registers'))->columns(2)->schema([
                Forms\Components\TextInput::make('bank_account')->label(__('Bank account (жиро сметка)')),
                Forms\Components\TextInput::make('bank_name')->label(__('Bank')),
                Forms\Components\Repeater::make('registers')->label(__('Cash registers'))->columnSpanFull()->columns(2)->defaultItems(1)->reorderable(false)
                    ->schema([
                        Forms\Components\TextInput::make('key')->label(__('Code'))->required()->alphaDash()->maxLength(30)->default('main'),
                        Forms\Components\TextInput::make('name')->label(__('Name'))->required()->default('Главна благајна'),
                    ])->addActionLabel(__('Add cash register')),
            ]),
            Forms\Components\Section::make(__('Travel contract'))->schema([
                Forms\Components\TextInput::make('license')->label(__('License (header)'))->placeholder('Лиценца А'),
                Forms\Components\Textarea::make('payment_clause')->label(__('Payment clause'))->rows(2),
                Forms\Components\Textarea::make('terms_clause')->label(__('General terms clause'))->rows(1),
                Forms\Components\Textarea::make('declaration')->label(__('Declaration above the signatures'))->rows(2),
                Forms\Components\Textarea::make('general_terms')->label(__('General terms of travel (printed on page 2)'))->rows(8),
                Forms\Components\FileUpload::make('stamp')->label(__('Stamp & signature (PNG with transparent background)'))->image()->disk('public')->directory('agency')->imagePreviewHeight('120'),
            ]),
            Forms\Components\Section::make(__('Invoices'))->schema([
                Forms\Components\TextInput::make('invoice_text')->label(__('First words of the invoice text'))->placeholder('Ве задолжуваме за'),
                Forms\Components\Textarea::make('invoice_footer')->label(__('Invoice footer (legal note)'))->rows(2),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pick')->label(fn () => $this->agencyModel()?->name ?? __('Agency'))->icon('heroicon-o-building-office')->color('gray')
                ->visible(fn () => Auth::user()->isSuperAdmin())
                ->form([Forms\Components\Select::make('agency')->label(__('Agency'))->options(Agency::query()->orderBy('name')->pluck('name', 'id'))->required()])
                ->action(fn (array $data) => $this->redirect(static::getUrl(['agency' => $data['agency']]))),
        ];
    }

    public function save(): void
    {
        $a = $this->agencyModel();
        abort_unless($a, 404);
        $data = $this->form->getState();
        $regs = [];
        foreach ((array) ($data['registers'] ?? []) as $r) {
            if (filled($r['key'] ?? null)) {
                $regs[$r['key']] = $r['name'] ?: $r['key'];
            }
        }
        $data['registers'] = $regs ?: ['main' => 'Главна благајна'];
        $a->update(['documents' => array_merge((array) $a->documents, $data)]);
        Notification::make()->success()->title(__('Saved'))->send();
    }
}

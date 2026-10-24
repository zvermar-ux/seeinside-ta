<?php

namespace App\Filament\Pages;

use App\Models\Agency;
use App\Services\Messaging\Channels;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\Templates;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/** Notifications to clients and the agency: e-mail, WhatsApp, Viber – channels, which events, texts */
class MessagingSettings extends Page implements HasForms
{
    use InteractsWithForms, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'notifications';

    protected static string $view = 'filament.pages.document-settings';

    public ?array $data = [];

    #[Url]
    public ?int $agency = null;

    public const SECRETS = ['whatsapp.token', 'whatsapp.api_key', 'viber.api_key'];

    public static function defaultNavigationLabel(): string
    {
        return __('Notifications (e-mail, Viber, WhatsApp)');
    }

    public function getTitle(): string
    {
        return __('Notifications (e-mail, Viber, WhatsApp)');
    }

    public static function canAccess(): bool
    {
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
        $m = (array) ($a?->messaging ?? []);
        foreach (self::SECRETS as $k) {
            data_set($m, $k, null);     // secrets are never sent back to the browser
        }
        $events = [];
        foreach (array_keys(Templates::events()) as $e) {
            $events[$e] = $a ? array_values(array_intersect(Messenger::CHANNELS, (array) ($a->msg('events.'.$e) ?? $this->defaultChannels($e)))) : [];
        }
        $m['events'] = $events;
        $m['email']['enabled'] = $a?->msg('email.enabled', true) !== false;
        $m['country_code'] ??= '389';
        $this->form->fill($m);
    }

    protected function defaultChannels(string $event): array
    {
        return in_array($event, ['booking_received', 'booking_confirmed', 'payment_reminder', 'invoice_reminder', 'option_expiring', 'agency_reply', 'new_booking', 'client_message', 'reminders_digest'], true) ? ['email'] : [];
    }

    public function form(Form $form): Form
    {
        $a = $this->agencyModel();
        $messagingOn = (bool) $a?->hasModule('messaging');
        $secretHelp = fn (string $k) => filled($a?->msg($k)) ? __('Saved – leave empty to keep it.') : null;
        $channelOptions = ['email' => 'E-mail'] + ($messagingOn ? ['whatsapp' => 'WhatsApp', 'viber' => 'Viber'] : []);

        $eventFields = [];
        foreach (Templates::events() as $key => [$label, $for]) {
            [$s, $t] = Templates::defaults($key, 'mk');
            [$sEn, $tEn] = Templates::defaults($key, 'en');
            $eventFields[] = Forms\Components\Fieldset::make($label)->columns(1)->schema([
                Forms\Components\CheckboxList::make('events.'.$key)->hiddenLabel()->options($channelOptions)->columns(3)->gridDirection('row'),
                Forms\Components\Section::make(__('Text of the message'))->compact()->collapsible()->collapsed()->schema([
                    Forms\Components\TextInput::make('templates.'.$key.'.subject')->label(__('E-mail subject').' (МК)')->placeholder($s),
                    Forms\Components\Textarea::make('templates.'.$key.'.text')->label(__('Text').' (МК)')->rows(5)->placeholder($t),
                    ...($for === 'client' ? [
                        Forms\Components\TextInput::make('templates.'.$key.'.subject_en')->label(__('E-mail subject').' (EN)')->placeholder($sEn),
                        Forms\Components\Textarea::make('templates.'.$key.'.text_en')->label(__('Text').' (EN)')->rows(5)->placeholder($tEn),
                    ] : []),
                    Forms\Components\Placeholder::make('ph_'.$key)->hiddenLabel()
                        ->content(__('Empty = the text shown in grey. You can use:').' '.implode(' ', Templates::placeholders()).($key === 'agency_reply' || $for === 'agency' ? ' {message}' : '')),
                ]),
            ]);
        }

        return $form->statePath('data')->schema([
            Forms\Components\Section::make(__('Channels'))->columns(3)->schema([
                Forms\Components\Toggle::make('email.enabled')->label(__('Send e-mails'))->inline(false)
                    ->helperText(__('Over the agency SMTP (My agency) or the application mailer.')),
                Forms\Components\TextInput::make('country_code')->label(__('Country code for local numbers'))->prefix('+')->numeric()->maxLength(4)
                    ->helperText(__('070 123 456 → +389 70 123 456')),
                Forms\Components\TextInput::make('agency_phone')->label(__('Agency mobile for WhatsApp / Viber notifications'))->tel()
                    ->placeholder($a?->phone)->helperText(__('New bookings and client messages arrive here.')),
                Forms\Components\Placeholder::make('module_off')->hiddenLabel()->columnSpanFull()->visible(! $messagingOn)
                    ->content(__('Viber and WhatsApp are not enabled for this agency (the super admin turns them on under Agencies → Modules).')),
            ]),
            Forms\Components\Section::make('WhatsApp')->columns(3)->visible($messagingOn)->collapsible()->schema([
                Forms\Components\Select::make('whatsapp.provider')->label(__('Provider'))->options(Channels::providers()['whatsapp'])->live()->selectablePlaceholder(false),
                Forms\Components\TextInput::make('whatsapp.phone_number_id')->label('Phone number ID')->visible(fn (Get $get) => $get('whatsapp.provider') === 'meta'),
                Forms\Components\TextInput::make('whatsapp.token')->label(__('Access token (permanent)'))->password()->revealable()
                    ->helperText($secretHelp('whatsapp.token'))->visible(fn (Get $get) => $get('whatsapp.provider') === 'meta'),
                Forms\Components\TextInput::make('whatsapp.template')->label(__('Approved template (optional)'))->placeholder('seeinside_notice')
                    ->helperText(__('A WhatsApp template with one {{1}} variable – needed for messages to clients who have not written in the last 24 h.'))
                    ->visible(fn (Get $get) => $get('whatsapp.provider') === 'meta')->columnSpan(2),
                Forms\Components\TextInput::make('whatsapp.template_lang')->label(__('Template language'))->placeholder('mk')->visible(fn (Get $get) => $get('whatsapp.provider') === 'meta'),
                Forms\Components\TextInput::make('whatsapp.base_url')->label('Infobip base URL')->placeholder('xxxxx.api.infobip.com')->visible(fn (Get $get) => $get('whatsapp.provider') === 'infobip'),
                Forms\Components\TextInput::make('whatsapp.api_key')->label('Infobip API key')->password()->revealable()->helperText($secretHelp('whatsapp.api_key'))
                    ->visible(fn (Get $get) => $get('whatsapp.provider') === 'infobip'),
                Forms\Components\TextInput::make('whatsapp.sender')->label(__('WhatsApp sender number'))->placeholder('38970123456')->visible(fn (Get $get) => $get('whatsapp.provider') === 'infobip'),
                Forms\Components\Placeholder::make('wa_help')->hiddenLabel()->columnSpanFull()
                    ->content(__('Without a provider you can still write on WhatsApp / Viber by hand: in a booking, “Write to the client” opens the app with the text ready.')),
            ]),
            Forms\Components\Section::make('Viber')->columns(3)->visible($messagingOn)->collapsible()->schema([
                Forms\Components\Select::make('viber.provider')->label(__('Provider'))->options(Channels::providers()['viber'])->live()->selectablePlaceholder(false),
                Forms\Components\TextInput::make('viber.base_url')->label('Infobip base URL')->placeholder('xxxxx.api.infobip.com')->visible(fn (Get $get) => $get('viber.provider') === 'infobip'),
                Forms\Components\TextInput::make('viber.api_key')->label('Infobip API key')->password()->revealable()->helperText($secretHelp('viber.api_key'))
                    ->visible(fn (Get $get) => $get('viber.provider') === 'infobip'),
                Forms\Components\TextInput::make('viber.sender')->label(__('Viber sender name'))->placeholder('AriesTravel')->visible(fn (Get $get) => $get('viber.provider') === 'infobip'),
            ]),
            Forms\Components\Section::make(__('When is a message sent?'))
                ->description(__('Reminders are sent every morning at 9:00 – one day before the deadline.'))
                ->columns(2)->schema($eventFields),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')->label(__('Send a test'))->icon('heroicon-o-paper-airplane')->color('gray')
                ->form([
                    Forms\Components\Select::make('channel')->label(__('Channel'))->options(['email' => 'E-mail', 'whatsapp' => 'WhatsApp', 'viber' => 'Viber'])->required()->default('email'),
                    Forms\Components\TextInput::make('to')->label(__('To (e-mail or phone)'))->required(),
                ])
                ->action(function (array $data) {
                    $a = $this->agencyModel();
                    $ok = Messenger::send($a, $data['channel'], $data['to'], __('Test message').' – '.$a->name, __('This is a test message from :agency. If you received it, the channel works.', ['agency' => $a->name]), ['event' => 'test']);
                    $err = \App\Models\Message::withoutGlobalScopes()->where('agency_id', $a->id)->latest('id')->value('error');
                    $ok ? Notification::make()->success()->title(__('Sent'))->send()
                        : Notification::make()->danger()->title(__('Not sent'))->body($err)->persistent()->send();
                }),
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
        $old = (array) ($a->messaging ?? []);
        foreach (self::SECRETS as $k) {
            if (blank(data_get($data, $k))) {
                data_set($data, $k, data_get($old, $k));
            }
        }
        // only texts that differ from the default are kept
        foreach ((array) ($data['templates'] ?? []) as $e => $t) {
            $data['templates'][$e] = array_filter((array) $t, fn ($v) => filled($v));
        }
        $data['templates'] = array_filter((array) ($data['templates'] ?? []));
        $a->update(['messaging' => array_replace_recursive($old, $data)]);
        // array_replace_recursive keeps removed channels: write the event lists as they are now
        $m = (array) $a->messaging;
        $m['events'] = $data['events'] ?? [];
        $m['templates'] = $data['templates'];
        $a->update(['messaging' => $m]);
        Notification::make()->success()->title(__('Saved'))->send();
    }
}

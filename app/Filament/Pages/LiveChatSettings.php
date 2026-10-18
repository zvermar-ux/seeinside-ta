<?php

namespace App\Filament\Pages;

use App\Models\Agency;
use App\Models\AiAccount;
use App\Services\Ai\Ai;
use App\Services\Ai\ChatConfig;
use App\Services\Website\WebsiteNotifier;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/** Live chat on the website: on/off, look, channels (Viber, Messenger…), AI bot behaviour */
class LiveChatSettings extends Page implements HasForms
{
    use InteractsWithForms, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';

    protected static ?string $navigationGroup = 'ai';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'live-chat-settings';

    protected static string $view = 'filament.pages.live-chat-settings';

    public ?array $data = [];

    #[\Livewire\Attributes\Url(as: 'agency')]
    public ?int $agencyId = null;

    public static function defaultNavigationLabel(): string
    {
        return __('Live chat settings');
    }

    public function getTitle(): string
    {
        return __('Live chat settings');
    }

    public static function canAccess(): bool
    {
        $u = Auth::user();

        // turning the chat on/off and the bot's rules: agency admin (operators train the bot in Bot knowledge)
        return $u && ($u->isSuperAdmin() || ($u->agency_id && $u->can('agency.manage') && $u->can('live_chat.manage')));
    }

    public function getAgencyOptions(): array
    {
        return Auth::user()->isSuperAdmin() ? Agency::query()->orderBy('name')->pluck('name', 'id')->all() : [];
    }

    public function mount(): void
    {
        $u = Auth::user();
        if (! $u->isSuperAdmin()) {
            $this->agencyId = (int) $u->agency_id;
        } elseif (! $this->agencyId) {
            $this->agencyId = Agency::query()->orderBy('id')->value('id');
        }
        $this->form->fill(ChatConfig::get($this->agencyId));
    }

    public function updatedAgencyId(): void
    {
        if (! Auth::user()->isSuperAdmin()) {
            $this->agencyId = (int) Auth::user()->agency_id;
        }
        $this->form->fill(ChatConfig::get($this->agencyId));
    }

    protected function two(string $name, string $label, bool $area = false): Forms\Components\Group
    {
        $f = fn (string $l) => $area
            ? Forms\Components\Textarea::make("{$name}.{$l}")->label($label.' ('.strtoupper($l).')')->rows(2)->autosize()
            : Forms\Components\TextInput::make("{$name}.{$l}")->label($label.' ('.strtoupper($l).')')->maxLength(200);

        return Forms\Components\Group::make([$f('mk'), $f('en')])->columns(2)->columnSpanFull();
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make()->columns(4)->schema([
                Forms\Components\Toggle::make('enabled')->label(__('Show the chat on the website'))->inline(false)->columnSpan(2)
                    ->helperText(__('Turn it off at any time – the widget disappears from the website within a minute.')),
                Forms\Components\Placeholder::make('ai_state')->label(__('AI answers'))->columnSpan(2)
                    ->content(fn () => new HtmlString(Ai::available($this->agencyId, 'chat')
                        ? '<span style="color:rgb(var(--success-600));font-weight:600">● '.e(__('ready')).'</span>'
                        : '<span style="color:rgb(var(--warning-600));font-weight:600">● '.e(__('not available – turn on the chatbot and add a key in AI settings & limits. Without AI the chat offers Viber / Messenger / phone and collects contacts.')).'</span>')),
            ]),
            Forms\Components\Tabs::make('chat')->columnSpanFull()->persistTabInQueryString()->tabs([
                Forms\Components\Tabs\Tab::make(__('Look'))->icon('heroicon-o-swatch')->columns(4)->schema([
                    Forms\Components\ColorPicker::make('color')->label(__('Colour')),
                    Forms\Components\Select::make('position')->label(__('Position'))->options(['right' => __('Bottom right'), 'left' => __('Bottom left')]),
                    Forms\Components\TextInput::make('bot_name')->label(__('Name of the bot'))->maxLength(60),
                    Forms\Components\FileUpload::make('avatar')->label(__('Avatar (photo or logo)'))->image()->avatar()->disk('public')->directory('chat')->maxSize(2048),
                    $this->two('title', __('Title')),
                    $this->two('subtitle', __('Subtitle')),
                    $this->two('greeting', __('First message'), true),
                    $this->two('launcher', __('Button text')),
                    $this->two('hours', __('Working hours')),
                    $this->two('privacy', __('Privacy note'), true),
                ]),
                Forms\Components\Tabs\Tab::make(__('Channels'))->icon('heroicon-o-share')->columns(2)->schema([
                    Forms\Components\Placeholder::make('ch_hint')->hiddenLabel()->columnSpanFull()
                        ->content(__('At the start the visitor chooses how to talk: Viber, Messenger, WhatsApp, phone, e-mail or the chat with our assistant (AI). Empty fields are not shown.')),
                    Forms\Components\TextInput::make('viber')->label('Viber')->prefixIcon('heroicon-o-phone')
                        ->placeholder('+389 70 123 456 '.__('or').' viber://pa?chatURI=…')->helperText(__('Phone number or the link of your Viber community / bot.')),
                    Forms\Components\TextInput::make('messenger')->label('Messenger (Meta)')->prefixIcon('heroicon-o-chat-bubble-left')
                        ->placeholder('m.me/AriesTravel')->helperText(__('Your Facebook page username or m.me link.')),
                    Forms\Components\TextInput::make('whatsapp')->label('WhatsApp')->placeholder('+389 70 123 456'),
                    Forms\Components\TextInput::make('phone')->label(__('Phone'))->tel(),
                    Forms\Components\TextInput::make('email')->label(__('E-mail'))->email(),
                    Forms\Components\Toggle::make('show_ai')->label(__('Chat with our assistant (AI)'))->inline(false),
                    Forms\Components\Toggle::make('show_questionnaires')->label(__('"Help me choose" questionnaires'))->inline(false),
                    Forms\Components\Toggle::make('ask_contact')->label(__('Offer "Call me back" (name, phone, e-mail)'))->inline(false),
                ]),
                Forms\Components\Tabs\Tab::make(__('Bot behaviour'))->icon('heroicon-o-academic-cap')->schema([
                    Forms\Components\Textarea::make('instructions')->label(__('Instructions for the bot'))->rows(8)->autosize()->maxLength(3000)
                        ->placeholder("Пример:\n- Секогаш предложи и осигурување.\n- За групи над 10 лица кажи дека имаме специјални цени.\n- Плаќање: 30% аванс, остатокот 14 дена пред патување.")
                        ->helperText(__('Rules, tone and facts the bot must follow. More knowledge (and answers corrected by operators) is in AI & Live chat → Bot knowledge.')),
                    $this->two('handoff', __('Message when an agent takes over'), true),
                ]),
                Forms\Components\Tabs\Tab::make(__('Install'))->icon('heroicon-o-code-bracket')->schema([
                    Forms\Components\Placeholder::make('install')->hiddenLabel()->content(new HtmlString(
                        '<p>'.e(__('The WordPress plugin "SeeInside Travel" shows the chat automatically when it is turned on here (version 1.3 or newer). Settings reach the website in real time.')).'</p>'
                        .'<p style="margin-top:.5rem">'.e(__('Conversations arrive in AI & Live chat → Conversations, where operators answer, take over from the bot and correct its answers.')).'</p>'
                    )),
                ]),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $acc = AiAccount::for($this->agencyId);
        $acc->chat = array_intersect_key($data, ChatConfig::defaults());
        if (! $acc->exists) {
            $acc->forceFill(['assistant_enabled' => true, 'images_enabled' => true]);
        }
        $acc->save();
        WebsiteNotifier::listChanged($this->agencyId, 'chat');
        Notification::make()->success()->title(__('Saved'))->body(__('The website gets the new settings in a moment.'))->send();
    }
}

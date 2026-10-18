<?php

namespace App\Filament\Pages;

use App\Models\Agency;
use App\Models\AiAccount;
use App\Models\AiUsage;
use App\Services\Ai\Ai;
use App\Services\Ai\AiException;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/** Settings → AI: keys (Claude / ChatGPT / Gemini), models and monthly spending limits per agency */
class AiSettings extends Page implements HasForms
{
    use InteractsWithForms, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'ai';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'ai-settings';

    protected static string $view = 'filament.pages.ai-settings';

    public ?array $data = [];

    /** null = the super admin's own account (also the platform keys agencies may share) */
    #[\Livewire\Attributes\Url(as: 'agency')]
    public ?int $agencyId = null;

    public static function defaultNavigationLabel(): string
    {
        return __('AI settings & limits');
    }

    public function getTitle(): string
    {
        return __('AI settings & limits');
    }

    public function getSubheading(): ?string
    {
        return $this->agencyId
            ? __('Keys, models and monthly limits of :agency. Operators use the assistant, website visitors the chatbot.', ['agency' => $this->agencyName()])
            : __('Your own AI account (super admin). Agencies do NOT use these keys automatically: choose the agency above and either enter its own keys or turn on "May use the platform keys".');
    }

    public static function canAccess(): bool
    {
        $u = Auth::user();

        return $u && ($u->isSuperAdmin() || ($u->agency_id && $u->can('agency.manage')));
    }

    protected function agencyName(): string
    {
        return (string) Agency::query()->whereKey($this->agencyId)->value('name');
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
        }
        $this->fillForm();
    }

    public function updatedAgencyId(): void
    {
        if (! Auth::user()->isSuperAdmin()) {
            $this->agencyId = (int) Auth::user()->agency_id;
        }
        $this->agencyId = $this->agencyId ?: null;
        $this->fillForm();
    }

    protected function account(): AiAccount
    {
        return AiAccount::for($this->agencyId);
    }

    protected function fillForm(): void
    {
        $a = $this->account();
        $state = $a->exists ? $a->only([
            'assistant_enabled', 'assistant_provider', 'assistant_model', 'assistant_limit', 'user_daily_limit',
            'images_enabled', 'image_provider', 'image_model', 'chat_enabled', 'chat_provider', 'chat_model', 'chat_limit',
            'platform_allowed', 'platform_limit', 'alert_email',
        ]) : [
            'assistant_enabled' => true, 'assistant_provider' => 'anthropic', 'images_enabled' => true, 'image_provider' => 'openai',
            'chat_enabled' => false, 'chat_provider' => 'anthropic', 'platform_allowed' => false,
        ];
        $state['prices'] = collect($a->prices ?? [])->map(fn ($p) => is_array($p) ? implode(' / ', $p) : (string) $p)->all();
        foreach (['openai', 'anthropic', 'gemini'] as $p) {
            $state["{$p}_key"] = null;
        }
        $state['remove_keys'] = [];
        $this->form->fill($state);
    }

    protected function has(string $provider): bool
    {
        return (bool) $this->account()->key($provider);
    }

    protected function keyField(string $provider): Forms\Components\TextInput
    {
        $labels = ['anthropic' => 'Claude (Anthropic)', 'openai' => 'ChatGPT (OpenAI)', 'gemini' => 'Gemini (Google)'];
        $links = ['anthropic' => 'https://console.anthropic.com/settings/keys', 'openai' => 'https://platform.openai.com/api-keys', 'gemini' => 'https://aistudio.google.com/apikey'];

        return Forms\Components\TextInput::make("{$provider}_key")->label($labels[$provider].' – API key')
            ->password()->revealable()->autocomplete('new-password')
            ->placeholder(fn () => $this->has($provider) ? '•••••••••• '.__('saved – type a new one to replace it') : __('not set'))
            ->helperText(new HtmlString('<a href="'.$links[$provider].'" target="_blank" rel="noopener" style="text-decoration:underline">'.e(__('Get a key')).' ↗</a>'))
            ->suffixAction(Forms\Components\Actions\Action::make("test_{$provider}")->icon('heroicon-m-signal')->tooltip(__('Test connection'))
                ->action(fn () => $this->testKey($provider)));
    }

    protected function modelField(string $name, string $providerField, string $kind): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)->label(__('Model'))
            ->placeholder(fn (Get $get) => config("ai.defaults.{$kind}.".($get($providerField) ?: 'anthropic')))
            ->datalist(fn (Get $get) => array_keys(config(($kind === 'image' ? 'ai.image_models.' : 'ai.text_models.').($get($providerField) ?: 'anthropic'), [])))
            ->helperText(__('Empty = the recommended model shown in grey.'))->maxLength(80);
    }

    public function form(Form $form): Form
    {
        $providers = config('ai.providers');
        $usd = fn (string $n, string $label) => Forms\Components\TextInput::make($n)->label($label)->numeric()->minValue(0)->step(0.01)->prefix('$')
            ->placeholder(__('no limit'));

        return $form->statePath('data')->schema([
            Forms\Components\Section::make(__('Spending this month'))->icon('heroicon-o-chart-bar')->schema([
                Forms\Components\Placeholder::make('usage')->hiddenLabel()->content(fn () => $this->usageHtml()),
            ])->collapsible(),

            Forms\Components\Section::make(__('API keys'))->icon('heroicon-o-key')
                ->description(__('Keys are stored encrypted and are never shown again. Each agency uses its own keys and pays its own provider bill.'))
                ->columns(3)->schema([
                    $this->keyField('anthropic'),
                    $this->keyField('openai'),
                    $this->keyField('gemini'),
                    Forms\Components\CheckboxList::make('remove_keys')->label(__('Delete saved keys'))->columns(3)->columnSpanFull()
                        ->options(fn () => collect($providers)->filter(fn ($l, $p) => $this->has($p))->all())
                        ->visible(fn () => $this->has('anthropic') || $this->has('openai') || $this->has('gemini')),
                ]),

            Forms\Components\Section::make(__('Assistant for operators'))->icon('heroicon-o-sparkles')
                ->description(__('Filling in tours, hotels, events and visas, translating MK → EN, SEO texts. Every user with the AI permission can use it.'))
                ->columns(4)->schema([
                    Forms\Components\Toggle::make('assistant_enabled')->label(__('On'))->inline(false),
                    Forms\Components\Select::make('assistant_provider')->label(__('Provider'))->options($providers)->required()->live(),
                    $this->modelField('assistant_model', 'assistant_provider', 'assistant'),
                    $usd('assistant_limit', __('Monthly limit (USD)'))->helperText(__('Texts + images + bot training.')),
                    $usd('user_daily_limit', __('Daily limit per user (USD)')),
                    Forms\Components\Toggle::make('images_enabled')->label(__('AI images'))->inline(false),
                    Forms\Components\Select::make('image_provider')->label(__('Image provider'))->live()
                        ->options(collect($providers)->only(config('ai.image_providers'))->all()),
                    $this->modelField('image_model', 'image_provider', 'image'),
                ]),

            Forms\Components\Section::make(__('Live chat bot (website)'))->icon('heroicon-o-chat-bubble-left-right')
                ->description(__('Answers visitors on the website. A cheaper, fast model is usually enough. The widget, channels and the bot\'s behaviour are set in AI & Live chat → Live chat settings.'))
                ->columns(4)->schema([
                    Forms\Components\Toggle::make('chat_enabled')->label(__('AI answers on'))->inline(false)
                        ->helperText(__('Must be on for the chatbot to answer.')),
                    Forms\Components\Select::make('chat_provider')->label(__('Provider'))->options($providers)->required()->live(),
                    $this->modelField('chat_model', 'chat_provider', 'chat'),
                    $usd('chat_limit', __('Monthly limit (USD)'))->helperText(__('When used up, the bot offers Viber / Messenger / phone instead.')),
                ]),

            Forms\Components\Section::make(__('Prices'))->icon('heroicon-o-currency-dollar')->collapsed()
                ->description(__('Used to calculate the spending. Text models: price per 1 million input / output tokens, e.g. "3 / 15". Image models: price per image, e.g. "0.07". Empty = standard price.'))
                ->schema([
                    Forms\Components\KeyValue::make('prices')->hiddenLabel()->keyLabel(__('Model'))->valueLabel(__('Price (USD)'))
                        ->addActionLabel(__('Add model')),
                    Forms\Components\Placeholder::make('std_prices')->hiddenLabel()->content(fn () => $this->standardPricesHtml()),
                ]),

            Forms\Components\Section::make(__('Platform keys'))->icon('heroicon-o-building-library')
                ->description(__('Only the super admin sees this. When the agency has no own key, it may use your keys up to this amount per month.'))
                ->visible(fn () => Auth::user()->isSuperAdmin() && $this->agencyId)
                ->columns(3)->schema([
                    Forms\Components\Toggle::make('platform_allowed')->label(__('May use the platform keys'))->inline(false),
                    $usd('platform_limit', __('Max per month on platform keys (USD)')),
                ]),

            Forms\Components\Section::make(__('Alerts'))->icon('heroicon-o-bell')->columns(3)->schema([
                Forms\Components\TextInput::make('alert_email')->label(__('E-mail at 80 % and 100 % of a limit'))->email()
                    ->placeholder(fn () => $this->agencyId ? Agency::query()->whereKey($this->agencyId)->value('email') : Auth::user()->email),
            ]),
        ]);
    }

    public function save(): void
    {
        $d = $this->form->getState();
        $a = $this->account();
        $a->fill(collect($d)->only([
            'assistant_enabled', 'assistant_provider', 'assistant_model', 'assistant_limit', 'user_daily_limit',
            'images_enabled', 'image_provider', 'image_model', 'chat_enabled', 'chat_provider', 'chat_model', 'chat_limit', 'alert_email',
        ])->map(fn ($v) => $v === '' ? null : $v)->all());
        if (Auth::user()->isSuperAdmin() && $this->agencyId) {
            $a->platform_allowed = (bool) ($d['platform_allowed'] ?? false);
            $a->platform_limit = ($d['platform_limit'] ?? '') === '' ? null : $d['platform_limit'];
        }
        foreach (['openai', 'anthropic', 'gemini'] as $p) {
            if (filled($d["{$p}_key"] ?? null)) {
                $a->{"{$p}_key"} = trim($d["{$p}_key"]);
            }
            if (in_array($p, $d['remove_keys'] ?? [], true)) {
                $a->{"{$p}_key"} = null;
            }
        }
        $prices = [];
        foreach ((array) ($d['prices'] ?? []) as $model => $v) {
            $nums = array_values(array_filter(array_map('trim', preg_split('#[/;|]#', str_replace(',', '.', (string) $v))), 'is_numeric'));
            if ($model && $nums) {
                $prices[trim($model)] = array_map('floatval', array_slice($nums, 0, 2));
            }
        }
        $a->prices = $prices ?: null;
        $a->save();
        \App\Services\Website\WebsiteNotifier::listChanged($this->agencyId, 'chat');

        $this->fillForm();
        Notification::make()->success()->title(__('Saved'))->send();
    }

    public function testKey(string $provider): void
    {
        // a key typed but not saved yet: save first
        $typed = $this->data["{$provider}_key"] ?? null;
        if (filled($typed)) {
            $a = $this->account();
            $a->{"{$provider}_key"} = trim($typed);
            $a->save();
            $this->data["{$provider}_key"] = null;
        }
        try {
            $answer = Ai::test($this->agencyId, $provider);
            Notification::make()->success()->title(__('Connection works'))->body($answer)->send();
        } catch (AiException $e) {
            Notification::make()->danger()->title(__('Connection failed'))->body($e->getMessage())->persistent()->send();
        }
    }

    protected function usageHtml(): HtmlString
    {
        $o = Ai::overview($this->agencyId);
        $bar = function (string $label, array $x) {
            $limit = $x['limit'];
            $pct = $x['percent'];
            $color = $pct === null ? 'var(--primary-500)' : ($pct >= 100 ? 'var(--danger-500)' : ($pct >= 80 ? 'var(--warning-500)' : 'var(--success-500)'));
            $txt = '$'.number_format($x['spent'], 2).($limit !== null ? ' / $'.number_format($limit, 2) : ' · '.__('no limit'));

            return '<div style="flex:1;min-width:15rem"><div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.3rem"><b>'.e($label).'</b><span>'.e($txt).'</span></div>'
                .'<div style="height:.6rem;border-radius:99px;background:rgb(var(--gray-200));overflow:hidden"><div style="height:100%;width:'.($pct ?? 4).'%;background:rgb('.$color.')"></div></div></div>';
        };
        $html = '<div style="display:flex;flex-wrap:wrap;gap:1.5rem">'.$bar(__('Assistant for operators'), $o['assistant']).$bar(__('Live chat bot'), $o['chat']).'</div>';
        if ($this->agencyId && $o['platform'] > 0) {
            $html .= '<p style="font-size:.85rem;margin-top:.6rem">'.e(__('Of that on platform keys')).': <b>$'.number_format($o['platform'], 2).'</b></p>';
        }

        $rows = AiUsage::query()->with('user:id,name')
            ->when($this->agencyId, fn ($q) => $q->where('agency_id', $this->agencyId), fn ($q) => $q->whereNull('agency_id'))
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('feature, user_id, count(*) as calls, sum(cost) as cost, sum(images) as images')
            ->groupBy('feature', 'user_id')->orderByDesc('cost')->limit(12)->get();
        if ($rows->isNotEmpty()) {
            $html .= '<table style="width:100%;margin-top:1rem;font-size:.85rem;border-collapse:collapse"><thead><tr style="text-align:left;color:rgb(var(--gray-500))"><th style="padding:.3rem 0">'.e(__('Use')).'</th><th>'.e(__('User')).'</th><th style="text-align:right">'.e(__('Requests')).'</th><th style="text-align:right">USD</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $html .= '<tr style="border-top:1px solid rgb(var(--gray-200))"><td style="padding:.35rem 0">'.e(AiUsage::featureLabel($r->feature)).'</td><td>'.e($r->user?->name ?? ($r->feature === 'chat' ? __('Website visitors') : '—')).'</td><td style="text-align:right">'.(int) $r->calls.($r->images ? ' ('.(int) $r->images.' '.e(__('images')).')' : '').'</td><td style="text-align:right">$'.number_format((float) $r->cost, 3).'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return new HtmlString($html);
    }

    protected function standardPricesHtml(): HtmlString
    {
        $html = '<div style="font-size:.8rem;color:rgb(var(--gray-500));columns:3 14rem">';
        foreach (config('ai.text_models') as $p => $models) {
            foreach ($models as $m => $pr) {
                $html .= '<div><code>'.e($m).'</code> '.e($pr[0].' / '.$pr[1]).'</div>';
            }
        }
        foreach (config('ai.image_models') as $p => $models) {
            foreach ($models as $m => $pr) {
                $html .= '<div><code>'.e($m).'</code> '.e($pr).' / '.e(__('image')).'</div>';
            }
        }

        return new HtmlString($html.'</div>');
    }
}

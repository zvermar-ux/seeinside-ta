<?php

namespace App\Filament\Pages;

use App\Support\Branding;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;

/** Settings → Appearance: logo, favicon, colours, fonts, dark mode, menu icons… (super admin) */
class Appearance extends Page implements HasForms
{
    use InteractsWithForms, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'administration';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'appearance';

    protected static string $view = 'filament.pages.appearance';

    public ?array $data = [];

    public static function defaultNavigationLabel(): string
    {
        return __('Appearance');
    }

    public function getTitle(): string
    {
        return __('Appearance');
    }

    public function getSubheading(): ?string
    {
        return __('Logo, colours, fonts, dark mode, menu and icons for the whole application.');
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(Branding::all());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')->label(__('Restore defaults'))->icon('heroicon-o-arrow-path')->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('All appearance settings go back to the original look. Uploaded images stay on the server.'))
                ->action(function () {
                    Branding::reset();
                    Notification::make()->success()->title(__('Restored'))->send();
                    $this->redirect(static::getUrl());
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Tabs::make()->id('appearance-tabs')->persistTab()->contained(false)->tabs([
                $this->brandTab(),
                $this->loginTab(),
                $this->colorsTab(),
                $this->typographyTab(),
                $this->layoutTab(),
                $this->menuTab(),
                $this->advancedTab(),
            ]),
        ]);
    }

    protected function upload(string $name, string $label): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($name)->label($label)
            ->disk('public')->directory('branding')->visibility('public')
            ->maxSize(4096);
    }

    protected function localized(string $name, string $label, bool $textarea = false): Forms\Components\Grid
    {
        $field = fn (string $l) => ($textarea ? Forms\Components\Textarea::make("{$name}.{$l}")->rows(3) : Forms\Components\TextInput::make("{$name}.{$l}"))
            ->label($label.' ('.strtoupper($l).')');

        return Forms\Components\Grid::make(2)->schema([$field('mk'), $field('en')]);
    }

    protected function brandTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make(__('Brand'))->icon('heroicon-o-sparkles')->schema([
            Forms\Components\Section::make(__('Name and logo'))->columns(2)->schema([
                Forms\Components\TextInput::make('app_name')->label(__('Application name'))
                    ->placeholder(config('app.name'))->helperText(__('Shown in the browser tab, e-mails and next to the logo.'))->maxLength(60),
                Forms\Components\Select::make('logo_height')->label(__('Logo height'))->selectablePlaceholder(false)
                    ->options(['1.75rem' => __('Small'), '2.25rem' => __('Normal'), '2.75rem' => __('Large'), '3.25rem' => __('Extra large')]),
                $this->upload('logo', __('Logo (light mode)'))->image()->imageEditor()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                    ->helperText(__('Horizontal logo, transparent PNG or SVG recommended.')),
                $this->upload('logo_dark', __('Logo (dark mode)'))->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                    ->helperText(__('Light version for the dark background. Empty = the same logo.')),
                Forms\Components\Toggle::make('show_name_with_logo')->label(__('Show the name next to the logo'))->columnSpanFull(),
            ]),
            Forms\Components\Section::make(__('Icons'))->columns(2)->schema([
                $this->upload('favicon', __('Favicon'))
                    ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'])
                    ->helperText(__('The small icon in the browser tab (ICO, PNG 32×32 or SVG).')),
                $this->upload('app_icon', __('App icon'))->image()->imageEditor()->imageEditorAspectRatios(['1:1'])
                    ->acceptedFileTypes(['image/png'])
                    ->helperText(__('Square PNG 512×512. Used when the panel is added to the home screen of a phone ("Install app").')),
            ]),
        ]);
    }

    protected function loginTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make(__('Login page'))->icon('heroicon-o-lock-closed')->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                $this->upload('login_background', __('Background image'))->image()->imageEditor()
                    ->helperText(__('A large photo (e.g. 1920×1080) behind the login form.')),
                Forms\Components\Radio::make('login_layout')->label(__('Layout'))->options([
                    'centered' => __('Form in the middle, photo over the whole screen'),
                    'split' => __('Photo on the left, form on the right'),
                ]),
            ]),
            Forms\Components\Section::make(__('Welcome text'))->schema([
                $this->localized('login_title', __('Title')),
                $this->localized('login_text', __('Text'), textarea: true),
            ]),
        ]);
    }

    protected function colorsTab(): Forms\Components\Tabs\Tab
    {
        $presets = collect(Branding::PRESETS)->mapWithKeys(fn ($p, $k) => [$k =>
            '<span style="display:inline-flex;align-items:center;gap:.5rem"><span style="width:1rem;height:1rem;border-radius:99px;background:'.$p['primary'].';display:inline-block"></span>'.e(__($p['label'])).'</span>',
        ]);

        return Forms\Components\Tabs\Tab::make(__('Colours'))->icon('heroicon-o-swatch')->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Section::make()->columnSpan(2)->columns(3)->schema([
                    Forms\Components\Select::make('preset')->label(__('Ready-made combination'))->columnSpanFull()
                        ->options($presets)->allowHtml()->dehydrated(false)->live()
                        ->placeholder(__('Choose to fill the colours below'))
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($p = Branding::PRESETS[$state] ?? null) {
                                $set('primary', $p['primary']);
                                $set('gray', $p['gray']);
                                $set('info', $p['info']);
                            }
                        }),
                    Forms\Components\ColorPicker::make('primary')->label(__('Main colour'))->live()
                        ->helperText(__('Buttons, links, active menu')),
                    Forms\Components\Select::make('gray')->label(__('Neutral tone'))->live()->selectablePlaceholder(false)
                        ->options(['slate' => __('Slate (bluish)'), 'gray' => __('Gray'), 'zinc' => __('Zinc (cool)'), 'neutral' => __('Neutral'), 'stone' => __('Stone (warm)')]),
                    Forms\Components\ColorPicker::make('info')->label(__('Information'))->live(),
                    Forms\Components\ColorPicker::make('success')->label(__('Success'))->live(),
                    Forms\Components\ColorPicker::make('warning')->label(__('Warning'))->live(),
                    Forms\Components\ColorPicker::make('danger')->label(__('Danger / delete'))->live(),
                ]),
                Forms\Components\Section::make(__('Preview'))->columnSpan(1)->schema([
                    Forms\Components\Placeholder::make('preview')->hiddenLabel()
                        ->content(fn (Get $get) => new HtmlString(view('filament.pages.appearance-preview', ['s' => $get()])->render())),
                ]),
            ]),
        ]);
    }

    protected function typographyTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make(__('Theme & fonts'))->icon('heroicon-o-language')->schema([
            Forms\Components\Section::make(__('Light / dark mode'))->columns(2)->schema([
                Forms\Components\Radio::make('theme_mode')->label(__('Mode'))->live()->options([
                    'switchable' => __('Users choose (light / dark / system) in their menu'),
                    'light' => __('Always light'),
                    'dark' => __('Always dark'),
                ]),
                Forms\Components\Radio::make('default_theme')->label(__('Starting mode for new users'))
                    ->visible(fn (Get $get) => $get('theme_mode') === 'switchable')
                    ->options(['system' => __('Like the device (system)'), 'light' => __('Light'), 'dark' => __('Dark')]),
            ]),
            Forms\Components\Section::make(__('Typography'))->columns(2)->schema([
                Forms\Components\Select::make('font')->label(__('Font'))->options(collect(Branding::FONTS)->map(fn ($l) => __($l)))
                    ->searchable()->selectablePlaceholder(false)
                    ->helperText(__('All fonts support Cyrillic.')),
                Forms\Components\Select::make('heading_font')->label(__('Font for titles'))->options(collect(Branding::FONTS)->except('system')->map(fn ($l) => __($l)))
                    ->searchable()->placeholder(__('Same as the text')),
                Forms\Components\Select::make('font_scale')->label(__('Text size'))->selectablePlaceholder(false)
                    ->options(['90' => __('Small'), '100' => __('Normal'), '106' => __('Larger'), '112' => __('Large')]),
                Forms\Components\Select::make('heading_weight')->label(__('Title weight'))->selectablePlaceholder(false)
                    ->options(['500' => __('Regular'), '600' => __('Semi-bold'), '700' => __('Bold'), '800' => __('Extra bold')]),
            ]),
            Forms\Components\Section::make(__('Shapes and background'))->columns(2)->schema([
                Forms\Components\Radio::make('radius')->label(__('Corners'))->inline()->options([
                    'none' => __('Square'), 'sm' => __('Slightly rounded'), 'md' => __('Rounded'), 'lg' => __('More rounded'), 'xl' => __('Very rounded'),
                ]),
                Forms\Components\Radio::make('background')->label(__('Page background'))->inline()->options([
                    'plain' => __('Plain'), 'white' => __('White'), 'soft' => __('Soft colour gradient'), 'dots' => __('Dots'),
                ]),
            ]),
        ]);
    }

    protected function layoutTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make(__('Layout'))->icon('heroicon-o-squares-2x2')->schema([
            Forms\Components\Section::make(__('Menu'))->columns(2)->schema([
                Forms\Components\Radio::make('navigation')->label(__('Menu position'))->live()->options([
                    'sidebar' => __('Left sidebar'), 'top' => __('Top bar'),
                ]),
                Forms\Components\Radio::make('sidebar_style')->label(__('Sidebar colour'))
                    ->visible(fn (Get $get) => $get('navigation') !== 'top')
                    ->options(['light' => __('Light'), 'tinted' => __('Light in the main colour'), 'dark' => __('Dark'), 'brand' => __('Main colour')]),
                Forms\Components\Radio::make('sidebar_collapse')->label(__('Collapsing the sidebar (computer)'))
                    ->visible(fn (Get $get) => $get('navigation') !== 'top')
                    ->options(['icons' => __('Collapses to icons'), 'full' => __('Hides completely'), 'none' => __('Always open')]),
                Forms\Components\Select::make('sidebar_width')->label(__('Sidebar width'))->selectablePlaceholder(false)
                    ->visible(fn (Get $get) => $get('navigation') !== 'top')
                    ->options(['16rem' => __('Narrow'), '18rem' => __('Medium'), '20rem' => __('Normal'), '22rem' => __('Wide')]),
            ]),
            Forms\Components\Section::make(__('Pages and tables'))->columns(3)->schema([
                Forms\Components\Select::make('content_width')->label(__('Content width'))->selectablePlaceholder(false)
                    ->options(['full' => __('Full screen'), 'screen-2xl' => __('Very wide'), '7xl' => __('Wide'), '5xl' => __('Narrow')]),
                Forms\Components\Select::make('per_page')->label(__('Rows per page'))->selectablePlaceholder(false)
                    ->options([10 => '10', 25 => '25', 50 => '50', 100 => '100']),
                Forms\Components\Select::make('notifications_position')->label(__('Notifications position'))->selectablePlaceholder(false)
                    ->options([
                        'top-right' => __('Top right'), 'top-center' => __('Top centre'), 'top-left' => __('Top left'),
                        'bottom-right' => __('Bottom right'), 'bottom-center' => __('Bottom centre'), 'bottom-left' => __('Bottom left'),
                    ]),
                Forms\Components\Toggle::make('table_striped')->label(__('Striped table rows')),
                Forms\Components\Toggle::make('breadcrumbs')->label(__('Show breadcrumbs (path above the title)')),
                Forms\Components\Toggle::make('spa')->label(__('Fast page switching (without full reload)')),
                Forms\Components\Toggle::make('unsaved_alerts')->label(__('Warn before leaving a form with unsaved changes')),
            ]),
            Forms\Components\Section::make(__('Footer'))->schema([
                $this->localized('footer_text', __('Text at the bottom of every page'), textarea: true),
                Forms\Components\Placeholder::make('footer_hint')->hiddenLabel()->content(__('{year} is replaced with the current year, e.g. © {year} Aries Travel.')),
            ]),
        ]);
    }

    protected function menuTab(): Forms\Components\Tabs\Tab
    {
        $icons = collect(Branding::ICONS)
            ->merge($this->menuItems()->pluck('default_icon')->filter()->map(fn ($i) => preg_replace('/^heroicon-[osm]-/', '', $i)))
            ->unique()->sort()
            ->mapWithKeys(fn ($name) => ['heroicon-o-'.$name =>
                '<span style="display:inline-flex;align-items:center;gap:.5rem">'.svg('heroicon-o-'.$name, '', ['style' => 'width:1.15rem;height:1.15rem;flex-shrink:0'])->toHtml().e($name).'</span>',
            ]);

        $rows = $this->menuItems()->map(fn ($item) => Forms\Components\Grid::make(12)->schema([
            Forms\Components\Placeholder::make('nav_label_'.$item['key'])->hiddenLabel()->columnSpan(3)
                ->content(new HtmlString('<div style="display:flex;align-items:center;gap:.6rem;padding-top:.5rem">'
                    .($item['default_icon'] ? svg($item['default_icon'], '', ['style' => 'width:1.25rem;height:1.25rem;color:rgb(var(--gray-400))'])->toHtml() : '')
                    .'<div><div style="font-weight:600">'.e($item['label']).'</div><div style="font-size:.75rem;color:rgb(var(--gray-500))">'.e($item['group']).'</div></div></div>')),
            Forms\Components\Select::make("nav.{$item['key']}.icon")->hiddenLabel()->placeholder(__('Default icon'))
                ->options($icons)->allowHtml()->searchable()->columnSpan(3),
            Forms\Components\TextInput::make("nav.{$item['key']}.label_mk")->hiddenLabel()->placeholder(__('Name').' (МК)')->columnSpan(2),
            Forms\Components\TextInput::make("nav.{$item['key']}.label_en")->hiddenLabel()->placeholder(__('Name').' (EN)')->columnSpan(2),
            Forms\Components\TextInput::make("nav.{$item['key']}.sort")->hiddenLabel()->placeholder(__('Order'))->numeric()->columnSpan(2),
        ]))->all();

        $groups = collect(['offer' => __('Offer'), 'bookings' => __('Bookings & requests'), 'partners' => __('Partners'), 'settings' => __('Settings'), 'administration' => __('Administration')])
            ->map(fn ($label, $key) => Forms\Components\Grid::make(3)->schema([
                Forms\Components\Placeholder::make('group_'.$key)->hiddenLabel()->content(new HtmlString('<div style="font-weight:600;padding-top:.5rem">'.e($label).'</div>')),
                Forms\Components\TextInput::make("groups.{$key}.mk")->hiddenLabel()->placeholder(__('Name').' (МК)'),
                Forms\Components\TextInput::make("groups.{$key}.en")->hiddenLabel()->placeholder(__('Name').' (EN)'),
            ]))->values()->all();

        return Forms\Components\Tabs\Tab::make(__('Menu & icons'))->icon('heroicon-o-bars-3')->schema([
            Forms\Components\Section::make(__('Icon style'))->columns(2)->schema([
                Forms\Components\Radio::make('icon_style')->label(__('Icons in the menu'))->inline()->options([
                    'outline' => new HtmlString(svg('heroicon-o-globe-europe-africa', '', ['style' => 'width:1.2rem;height:1.2rem;display:inline'])->toHtml().' '.e(__('Outline'))),
                    'solid' => new HtmlString(svg('heroicon-s-globe-europe-africa', '', ['style' => 'width:1.2rem;height:1.2rem;display:inline'])->toHtml().' '.e(__('Solid'))),
                    'mini' => new HtmlString(svg('heroicon-m-globe-europe-africa', '', ['style' => 'width:1rem;height:1rem;display:inline'])->toHtml().' '.e(__('Small'))),
                ]),
                Forms\Components\Toggle::make('active_icon_solid')->label(__('Solid icon for the open page'))->inline(false),
            ]),
            Forms\Components\Section::make(__('Menu items'))
                ->description(__('Change the icon, the name (MK / EN) or the order of any menu item. Empty = default. Lower number = higher in its group.'))
                ->schema($rows),
            Forms\Components\Section::make(__('Menu groups'))->collapsed()->schema($groups),
        ]);
    }

    protected function advancedTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make(__('Advanced'))->icon('heroicon-o-adjustments-horizontal')->schema([
            Forms\Components\Section::make(__('Agency branding'))->schema([
                Forms\Components\Toggle::make('allow_agency_branding')->label(__('Agencies may use their own logo and main colour'))->live()
                    ->helperText(__('Set in "My agency" (agency admin) or in Agencies. Users of that agency then see their own look.')),
                Forms\Components\Toggle::make('agency_name_as_brand')->label(__('Show the agency name instead of the application name'))
                    ->visible(fn (Get $get) => $get('allow_agency_branding')),
            ]),
            Forms\Components\Section::make(__('Custom CSS'))->collapsed()->schema([
                Forms\Components\Textarea::make('custom_css')->hiddenLabel()->rows(10)->extraInputAttributes(['style' => 'font-family:ui-monospace,monospace;font-size:.8rem'])
                    ->placeholder(".fi-sidebar { }\n.fi-header-heading { letter-spacing: -.02em; }")
                    ->helperText(__('For fine-tuning by a designer. Applies to the whole admin panel.')),
            ]),
        ]);
    }

    /** All menu entries of the panel: key (class basename), default icon and label, group */
    protected function menuItems(): \Illuminate\Support\Collection
    {
        $panel = Filament::getPanel('admin');
        $groups = ['offer' => __('Offer'), 'bookings' => __('Bookings & requests'), 'partners' => __('Partners'), 'settings' => __('Settings'), 'administration' => __('Administration')];

        return collect(array_merge($panel->getResources(), $panel->getPages()))
            ->unique()
            ->filter(fn ($class) => method_exists($class, 'defaultNavigationLabel') && $class::shouldRegisterNavigation())
            ->map(fn ($class) => [
                'key' => class_basename($class),
                'class' => $class,
                'label' => $class::defaultNavigationLabel(),
                'default_icon' => $class::defaultNavigationIcon(),
                'group' => $groups[$class::getNavigationGroup()] ?? '—',
                'sort' => ((int) array_search($class::getNavigationGroup(), array_keys($groups), true)) * 1000 + (int) $class::getNavigationSort(),
            ])
            ->sortBy('sort')->values();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $data['nav'] = collect($data['nav'] ?? [])->map(fn ($row) => array_filter((array) $row, fn ($v) => filled($v)))->filter()->all();
        $data['groups'] = collect($data['groups'] ?? [])->map(fn ($row) => array_filter((array) $row, fn ($v) => filled($v)))->filter()->all();

        Branding::save($data);

        Notification::make()->success()->title(__('Appearance saved'))->send();
        // full reload so the new colours, fonts and menu are applied everywhere
        $this->redirect(static::getUrl(), navigate: false);
    }
}

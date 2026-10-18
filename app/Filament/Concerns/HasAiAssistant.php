<?php

namespace App\Filament\Concerns;

use App\Models\Media;
use App\Services\Ai\Ai;
use App\Services\Ai\AiException;
use App\Services\Ai\AssistantFields;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "✨ AI" menu on create / edit pages: write texts from notes, improve, translate MK → EN, SEO, and AI images.
 * The texts go into the form – nothing is saved until the operator clicks Save.
 */
trait HasAiAssistant
{
    protected function aiActions(): ActionGroup
    {
        return ActionGroup::make([$this->aiWriteAction(), $this->aiTranslateAction(), $this->aiSeoAction(), $this->aiImageAction()])
            ->label('AI')->icon('heroicon-m-sparkles')->color('primary')->button()
            ->visible(fn () => static::aiAllowed());
    }

    public static function aiAllowed(): bool
    {
        $u = Auth::user();

        return $u && ($u->isSuperAdmin() || $u->can('ai.view'));
    }

    protected function aiAgencyId(): ?int
    {
        $id = $this->data['agency_id'] ?? $this->record?->agency_id ?? Auth::user()->agency_id;

        return $id ? (int) $id : null;
    }

    protected function aiModelClass(): string
    {
        return static::getResource()::getModel();
    }

    protected function aiFields(): array
    {
        return AssistantFields::for($this->aiModelClass());
    }

    protected function aiSingle(): bool
    {
        return AssistantFields::singleLocale($this->aiModelClass());
    }

    protected function aiLocale(): string
    {
        return property_exists($this, 'activeLocale') && $this->activeLocale ? $this->activeLocale : 'mk';
    }

    /** Current value of a field in one language */
    protected function aiValue(string $field, string $locale): string
    {
        $v = $this->data[$field] ?? null;
        if ($this->aiSingle()) {
            return $locale === $this->aiLocale() ? trim(strip_tags((string) (is_array($v) ? '' : $v))) : '';
        }

        return is_array($v) ? trim((string) ($v[$locale] ?? '')) : '';
    }

    /** Everything else in the form (country, dates, prices…) as context for the AI */
    protected function aiContext(): string
    {
        $skip = array_merge(array_keys($this->aiFields()), ['agency_id', 'media', 'gallery', 'cover_image', 'path', 'image', 'images']);
        $ctx = Arr::except($this->data ?? [], $skip);
        array_walk_recursive($ctx, function (&$v) {
            if (is_string($v)) {
                $v = Str::limit(strip_tags($v), 400);
            }
        });
        $ctx = array_filter($ctx, fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== false);

        return Str::limit(json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 7000);
    }

    protected function aiSystem(): string
    {
        $agency = \App\Models\Agency::query()->whereKey($this->aiAgencyId())->value('name') ?: 'travel agency';

        return "You are an experienced copywriter of the travel agency \"{$agency}\" from North Macedonia. "
            ."You write offers for its website: warm, clear, concrete and honest – no invented facts, prices or dates. "
            ."Macedonian texts must be in correct standard Macedonian (Cyrillic). English texts in natural British English. "
            ."HTML fields: use only <p>, <ul>, <li>, <strong>, <em>, <h3>. Lines fields: an array of short items without bullets.";
    }

    protected function aiFieldSpec(array $only): string
    {
        $lines = [];
        foreach ($this->aiFields() as $f => [$kind, $desc]) {
            if (in_array($f, $only, true)) {
                $lines[] = "- {$f} ({$kind}): {$desc}";
            }
        }

        return implode("\n", $lines);
    }

    /** Put AI values into the form state */
    protected function aiApply(array $fields, array $locales, bool $overwrite): int
    {
        $spec = $this->aiFields();
        $n = 0;
        foreach ($fields as $field => $value) {
            if (! isset($spec[$field])) {
                continue;
            }
            $kind = $spec[$field][0];
            foreach ($locales as $locale) {
                $v = is_array($value) && ! array_is_list($value) ? ($value[$locale] ?? null) : ($this->aiSingle() ? $value : null);
                if ($v === null || $v === '' || $v === []) {
                    continue;
                }
                if (is_array($v)) {
                    $v = implode("\n", array_map(fn ($x) => trim(ltrim((string) $x, "-•* \t")), $v));
                }
                $v = $kind === 'html' ? strip_tags((string) $v, '<p><ul><ol><li><strong><b><em><i><h3><h4><br><a>') : trim(strip_tags((string) $v));
                if (! $overwrite && $this->aiValue($field, $locale) !== '') {
                    continue;
                }
                if ($this->aiSingle()) {
                    $this->data[$field] = $v;
                } else {
                    $cur = is_array($this->data[$field] ?? null) ? $this->data[$field] : [];
                    $cur[$locale] = $v;
                    $this->data[$field] = $cur;
                }
                $n++;
            }
        }

        return $n;
    }

    protected function aiApplyDays(array $days, bool $overwrite): int
    {
        if (! AssistantFields::hasDays($this->aiModelClass()) || ! $days) {
            return 0;
        }
        $state = is_array($this->data['days'] ?? null) ? $this->data['days'] : [];
        $byNumber = [];
        foreach ($state as $key => $d) {
            $byNumber[(int) ($d['day_number'] ?? 0)] = $key;
        }
        $n = 0;
        foreach ($days as $i => $d) {
            $num = (int) ($d['day'] ?? $d['day_number'] ?? $i + 1);
            $title = ['mk' => $d['title']['mk'] ?? null, 'en' => $d['title']['en'] ?? null];
            $desc = ['mk' => strip_tags((string) ($d['description']['mk'] ?? ''), '<p><ul><li><strong><em><br>'), 'en' => strip_tags((string) ($d['description']['en'] ?? ''), '<p><ul><li><strong><em><br>')];
            if (isset($byNumber[$num])) {
                $key = $byNumber[$num];
                if (! $overwrite && filled($state[$key]['title']['mk'] ?? null)) {
                    continue;
                }
                $state[$key]['title'] = $title;
                $state[$key]['description'] = $desc;
            } else {
                $state[(string) Str::uuid()] = ['day_number' => $num, 'meals' => $d['meals'] ?? null, 'overnight' => $d['overnight'] ?? null, 'title' => $title, 'description' => $desc];
            }
            $n++;
        }
        $this->data['days'] = $state;

        return $n;
    }

    protected function aiRun(callable $fn): void
    {
        try {
            $msg = $fn();
            Notification::make()->success()->title(__('AI filled the form'))->body($msg.' '.__('Check the texts and click Save.'))->send();
        } catch (AiException $e) {
            Notification::make()->danger()->title(__('AI'))->body($e->getMessage())->persistent()->send();
        }
    }

    protected function aiWriteAction(): Action
    {
        $fields = fn () => collect($this->aiFields())->map(fn ($x, $f) => Str::of($f)->replace('_', ' ')->ucfirst()->toString().' – '.$x[1])->all();

        return Action::make('aiWrite')->label(__('Write / improve texts'))->icon('heroicon-o-pencil-square')
            ->modalHeading(__('AI writes the texts'))
            ->modalDescription(__('Paste anything you have – supplier offer, program, notes, link text. AI fills the chosen fields in Macedonian and English. Nothing is saved until you click Save.'))
            ->modalSubmitActionLabel(__('Write'))
            ->form([
                Forms\Components\Textarea::make('notes')->label(__('Your notes / source text'))->rows(8)->autosize()
                    ->placeholder(__('e.g. 7 days Rome–Florence–Venice, bus from Skopje, 3* hotels with breakfast, guide, departures in April and May…')),
                Forms\Components\Radio::make('mode')->label(__('What to do'))->default('fill')->inline()->options([
                    'fill' => __('Fill empty fields'), 'rewrite' => __('Improve / rewrite the existing texts'),
                ]),
                Forms\Components\CheckboxList::make('fields')->label(__('Fields'))->columns(2)->bulkToggleable()
                    ->options($fields)->default(fn () => collect($this->aiFields())->keys()->filter(fn ($f) => $this->aiValue($f, $this->aiSingle() ? $this->aiLocale() : 'mk') === '' && ! str_starts_with($f, 'seo_'))->values()->all()),
                Forms\Components\Toggle::make('days')->label(__('Also write the program by days'))->default(fn () => empty($this->data['days']))
                    ->visible(fn () => AssistantFields::hasDays($this->aiModelClass())),
                Forms\Components\Select::make('tone')->label(__('Tone'))->default('friendly')->options([
                    'friendly' => __('Warm and friendly'), 'premium' => __('Elegant / premium'), 'short' => __('Short and factual'), 'fun' => __('Fun, for young people'),
                ]),
            ])
            ->action(function (array $data) {
                $this->aiRun(function () use ($data) {
                    $only = $data['fields'] ?? [];
                    $wantDays = ! empty($data['days']) && AssistantFields::hasDays($this->aiModelClass());
                    if (! $only && ! $wantDays) {
                        throw new AiException(__('Choose at least one field.'));
                    }
                    $locales = $this->aiSingle() ? [$this->aiLocale()] : ['mk', 'en'];
                    $current = [];
                    foreach ($only as $f) {
                        foreach ($locales as $l) {
                            if (($v = $this->aiValue($f, $l)) !== '') {
                                $current[$f][$l] = Str::limit($v, 3000);
                            }
                        }
                    }
                    $tone = ['friendly' => 'warm and friendly', 'premium' => 'elegant, premium', 'short' => 'short and factual', 'fun' => 'playful, for young travellers'][$data['tone'] ?? 'friendly'];
                    $langs = $this->aiSingle() ? ($locales[0] === 'en' ? 'English only' : 'Macedonian only') : 'both Macedonian ("mk") and English ("en")';
                    $shape = $this->aiSingle() ? '{"fields": {"<field>": "<value>"}}' : '{"fields": {"<field>": {"mk": "...", "en": "..."}}'.($wantDays ? ', "days": [{"day": 1, "title": {"mk": "", "en": ""}, "description": {"mk": "<p>…</p>", "en": "<p>…</p>"}, "meals": "B", "overnight": "Rome"}]' : '').'}';
                    $prompt = "Fields to write (".($data['mode'] === 'rewrite' ? 'improve the current text, keep all facts' : 'write new text').", tone: {$tone}, language: {$langs}):\n"
                        .$this->aiFieldSpec($only)
                        .($wantDays ? "\n- days: day-by-day program, one entry per day, using the duration from the data" : '')
                        ."\n\nLines fields must be JSON arrays of strings. Return: {$shape}"
                        ."\n\nOther data of this offer (JSON):\n".$this->aiContext()
                        .($current ? "\n\nCurrent texts:\n".json_encode($current, JSON_UNESCAPED_UNICODE) : '')
                        .(filled($data['notes'] ?? null) ? "\n\nNotes from the operator / supplier (main source of facts):\n".Str::limit($data['notes'], 12000) : '');
                    $out = Ai::json($this->aiAgencyId(), 'assistant', $this->aiSystem(), [['role' => 'user', 'content' => $prompt]], $wantDays ? 7000 : 4500, null, class_basename($this->aiModelClass()).': write');
                    $n = $this->aiApply((array) ($out['fields'] ?? []), $locales, true);
                    $d = $wantDays ? $this->aiApplyDays((array) ($out['days'] ?? []), $data['mode'] === 'rewrite') : 0;

                    return trans_choice(':count field filled.|:count fields filled.', $n, ['count' => $n]).($d ? ' '.__(':count days of the program.', ['count' => $d]) : '');
                });
            });
    }

    protected function aiTranslateAction(): Action
    {
        return Action::make('aiTranslate')->label(__('Translate МК → EN'))->icon('heroicon-o-language')
            ->visible(fn () => ! $this->aiSingle())
            ->requiresConfirmation()->modalIcon('heroicon-o-language')
            ->modalHeading(__('Translate into English'))
            ->modalDescription(__('All Macedonian texts are translated into English (program by days too). Existing English texts are replaced.'))
            ->modalSubmitActionLabel(__('Translate'))
            ->action(function () {
                $this->aiRun(function () {
                    $src = [];
                    foreach (array_keys($this->aiFields()) as $f) {
                        if (($v = $this->aiValue($f, 'mk')) !== '') {
                            $src[$f] = Str::limit($v, 6000);
                        }
                    }
                    $days = [];
                    foreach ((array) ($this->data['days'] ?? []) as $d) {
                        if (filled($d['title']['mk'] ?? null) || filled($d['description']['mk'] ?? null)) {
                            $days[] = ['day' => (int) ($d['day_number'] ?? 0), 'title' => $d['title']['mk'] ?? '', 'description' => $d['description']['mk'] ?? ''];
                        }
                    }
                    if (! $src && ! $days) {
                        throw new AiException(__('There is no Macedonian text to translate.'));
                    }
                    $prompt = "Translate these Macedonian texts of a travel offer into natural English. Keep HTML tags and line breaks. Keep names of places correct in English.\n"
                        ."Return {\"fields\": {\"<field>\": \"<english>\"}".($days ? ', "days": [{"day": 1, "title": "", "description": ""}]' : '')."}\n\n"
                        .json_encode(['fields' => $src, 'days' => $days], JSON_UNESCAPED_UNICODE);
                    $out = Ai::json($this->aiAgencyId(), 'assistant', $this->aiSystem(), [['role' => 'user', 'content' => $prompt]], 8000, null, class_basename($this->aiModelClass()).': translate');
                    $n = $this->aiApply(collect((array) ($out['fields'] ?? []))->map(fn ($v) => ['en' => $v])->all(), ['en'], true);
                    $dn = 0;
                    if (! empty($out['days']) && is_array($this->data['days'] ?? null)) {
                        $state = $this->data['days'];
                        foreach ($out['days'] as $t) {
                            foreach ($state as $k => $d) {
                                if ((int) ($d['day_number'] ?? -1) === (int) ($t['day'] ?? -2)) {
                                    $state[$k]['title']['en'] = $t['title'] ?? null;
                                    $state[$k]['description']['en'] = $t['description'] ?? null;
                                    $dn++;
                                }
                            }
                        }
                        $this->data['days'] = $state;
                    }

                    return trans_choice(':count field filled.|:count fields filled.', $n, ['count' => $n]).($dn ? ' '.__(':count days of the program.', ['count' => $dn]) : '');
                });
            });
    }

    protected function aiSeoAction(): Action
    {
        return Action::make('aiSeo')->label(__('SEO title & description'))->icon('heroicon-o-magnifying-glass')
            ->visible(fn () => isset($this->aiFields()['seo_title']))
            ->requiresConfirmation()->modalIcon('heroicon-o-magnifying-glass')
            ->modalHeading(__('SEO for Google'))
            ->modalDescription(__('AI writes the SEO title and description (MK and EN) from the texts of this offer.'))
            ->action(function () {
                $this->aiRun(function () {
                    $prompt = "Write SEO title (max 60 chars) and meta description (140–160 chars) in Macedonian and English for this travel offer. Include the destination and the type of trip.\n"
                        .'Return {"fields": {"seo_title": {"mk": "", "en": ""}, "seo_description": {"mk": "", "en": ""}}}'."\n\n".$this->aiContext()
                        ."\n\n".json_encode(collect(['title', 'name', 'short_description', 'description', 'overview'])->mapWithKeys(fn ($f) => [$f => Str::limit($this->aiValue($f, 'mk'), 1500)])->filter()->all(), JSON_UNESCAPED_UNICODE);
                    $out = Ai::json($this->aiAgencyId(), 'assistant', $this->aiSystem(), [['role' => 'user', 'content' => $prompt]], 800, null, 'seo');
                    $n = $this->aiApply((array) ($out['fields'] ?? []), ['mk', 'en'], true);

                    return trans_choice(':count field filled.|:count fields filled.', $n, ['count' => $n]);
                });
            });
    }

    /* ---------------- images ---------------- */

    protected function aiImageTarget(): ?string
    {
        $record = $this->record ?? null;
        if (class_basename($this->aiModelClass()) === 'SliderImage') {
            return 'path';
        }
        if (class_basename($this->aiModelClass()) === 'Group') {
            return 'image';
        }
        if ($record && method_exists($record, 'media')) {
            return 'gallery';
        }

        return null;
    }

    protected function aiImageAction(): Action
    {
        return Action::make('aiImage')->label(__('Create image with AI'))->icon('heroicon-o-photo')
            ->visible(fn () => $this->aiImageTarget() !== null)
            ->modalHeading(__('AI image'))
            ->modalDescription(__('Describe the photo. Tip: name the place, season and mood. AI images are illustrations – for hotels prefer real photos.'))
            ->modalSubmitActionLabel(__('Create'))
            ->form([
                Forms\Components\Textarea::make('prompt')->label(__('Description'))->required()->rows(3)
                    ->default(fn () => trim(implode(', ', array_filter([
                        $this->aiValue('title', 'mk') ?: $this->aiValue('name', 'mk') ?: ($this->data['name'] ?? null),
                        is_string($this->data['location'] ?? null) ? $this->data['location'] : null,
                        is_string($this->data['country'] ?? null) ? $this->data['country'] : null,
                    ])))),
                Forms\Components\Select::make('style')->label(__('Style'))->default('photo')->options([
                    'photo' => __('Realistic travel photo'), 'golden' => __('Golden hour, warm light'), 'aerial' => __('Aerial / drone view'),
                    'winter' => __('Snowy winter scene'), 'illustration' => __('Modern illustration'),
                ]),
                Forms\Components\Select::make('shape')->label(__('Format'))->default(fn () => class_basename($this->aiModelClass()) === 'SliderImage' ? 'landscape' : 'landscape')
                    ->options(['landscape' => __('Landscape'), 'square' => __('Square'), 'portrait' => __('Portrait')]),
                Forms\Components\Toggle::make('main')->label(__('Set as main image'))->default(fn () => blank($this->record?->cover_image ?? null))
                    ->visible(fn () => $this->aiImageTarget() === 'gallery'),
            ])
            ->action(function (array $data) {
                try {
                    $styles = [
                        'photo' => 'Realistic, high-quality travel photograph, natural colours, no text, no logos, no watermarks.',
                        'golden' => 'Realistic travel photograph at golden hour, warm light, inviting, no text, no logos.',
                        'aerial' => 'Realistic aerial drone photograph, wide view, no text, no logos.',
                        'winter' => 'Realistic winter photograph with fresh snow, blue sky, no text, no logos.',
                        'illustration' => 'Modern flat travel illustration, soft colours, no text.',
                    ];
                    $prompt = trim($data['prompt']).'. '.$styles[$data['style'] ?? 'photo'].' Wide composition suitable for a travel agency website.';
                    $binary = Ai::image($this->aiAgencyId(), $prompt, $data['shape'] ?? 'landscape', null, Str::limit($data['prompt'], 120));
                    $ext = str_starts_with($binary, "\x89PNG") ? 'png' : (str_starts_with($binary, "\xFF\xD8") ? 'jpg' : 'webp');
                    $path = 'ai/'.now()->format('Y/m').'/'.Str::slug(Str::limit($data['prompt'], 40, '')).'-'.Str::lower(Str::random(6)).'.'.$ext;
                    Storage::disk('public')->put($path, $binary);
                    \App\Services\Media\MediaLibrary::register($path, $this->aiAgencyId(), 'ai', 'AI – '.Str::limit($data['prompt'], 60, ''))?->update(['folder' => 'ai']);

                    $target = $this->aiImageTarget();
                    if ($target === 'gallery') {
                        $record = $this->record;
                        $main = ! empty($data['main']);
                        $record->media()->create(['path' => $path, 'caption' => ['mk' => null, 'en' => null], 'is_main' => $main, 'sort_order' => (int) $record->media()->max('sort_order') + 1]);
                        if ($main) {
                            $record->media()->where('path', '!=', $path)->update(['is_main' => false]);
                            $record->forceFill(['cover_image' => $path])->save();
                            if (array_key_exists('cover_image', $this->data ?? [])) {
                                $this->data['cover_image'] = [(string) Str::uuid() => $path];
                            }
                        }
                        $body = __('The image was added to the gallery.');
                    } else {
                        $this->data[$target] = [(string) Str::uuid() => $path];
                        $body = __('The image is in the form – click Save.');
                    }
                    Notification::make()->success()->title(__('Image created'))->body($body)->send();
                } catch (AiException $e) {
                    Notification::make()->danger()->title(__('AI'))->body($e->getMessage())->persistent()->send();
                }
            });
    }
}

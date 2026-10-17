<?php

namespace App\Console\Commands;

use App\Models\Accommodation;
use App\Models\Event;
use App\Models\Tour;
use App\Models\VisaType;
use Illuminate\Console\Command;

/**
 * Re-creates the web addresses (slugs) of tours, events, hotels and visas from their names with the
 * Macedonian transliteration (Хотел → hotel). Run before the website goes live – addresses change.
 */
class FixSlugs extends Command
{
    protected $signature = 'catalog:fix-slugs {--dry : only show the changes}';

    protected $description = 'Regenerate web addresses (slugs) with Macedonian transliteration';

    public function handle(): int
    {
        foreach ([Tour::class, Event::class, Accommodation::class, VisaType::class] as $class) {
            foreach ($class::withoutGlobalScopes()->get() as $m) {
                $old = $m->slug;
                $m->slug = null;
                if ($this->option('dry')) {
                    $new = \Illuminate\Support\Str::slug((fn () => $this->slugSource())->call($m), '-', 'mk');
                    if ($new !== $old) {
                        $this->line(class_basename($class)." #{$m->id}: {$old} → {$new}");
                    }
                    continue;
                }
                $m->save();
                if ($m->slug !== $old) {
                    $this->line(class_basename($class)." #{$m->id}: {$old} → {$m->slug}");
                }
            }
        }

        return self::SUCCESS;
    }
}

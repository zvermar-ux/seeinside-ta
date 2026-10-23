<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/** Sequential number per agency, kind and year: 1, 2, 3… → "1350/26" */
trait HasYearlyNumber
{
    /** columns that split the numbering (besides agency and year) */
    abstract protected function numberScope(): array;

    public static function bootHasYearlyNumber(): void
    {
        static::creating(function ($m) {
            if ($m->seq) {
                return;
            }
            $date = $m->getAttribute($m->numberDateColumn()) ?? now();
            $year = (int) \Carbon\Carbon::parse($date)->format('Y');
            $m->year = $year;
            DB::transaction(function () use ($m, $year) {
                $q = static::withoutGlobalScopes()->where('agency_id', $m->agency_id)->where('year', $year)->lockForUpdate();
                foreach ($m->numberScope() as $col) {
                    $q->where($col, $m->getAttribute($col));
                }
                $m->seq = (int) $q->max('seq') + 1;
            });
            if ($col = $m->numberColumn()) {
                $m->{$col} ??= $m->seq.'/'.substr((string) $year, -2);
            }
        });
    }

    /** column that stores the printable number ("1350/26"), if any */
    protected function numberColumn(): ?string
    {
        return null;
    }

    protected function numberDateColumn(): string
    {
        return 'created_at';
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The default EUR rate is 62 MKD: agencies that still have the old default (61.5) saved get 62; own rates stay */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('agencies')->whereNotNull('documents')->get(['id', 'documents']) as $a) {
            $doc = json_decode((string) $a->documents, true);
            if (is_array($doc) && isset($doc['eur_rate']) && abs((float) $doc['eur_rate'] - 61.5) < 0.0001) {
                $doc['eur_rate'] = 62;
                DB::table('agencies')->where('id', $a->id)->update(['documents' => json_encode($doc, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void {}
};

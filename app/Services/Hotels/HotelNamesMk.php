<?php

namespace App\Services\Hotels;

use App\Models\SupplierDestination;
use App\Models\SupplierHotel;
use App\Services\Ai\Ai;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Names of the hotel destinations and their areas in Macedonian Cyrillic, written by AI (only the missing ones) */
class HotelNamesMk
{
    /** Areas of a destination: from the import of the destination and from its imported hotels */
    public static function zones(SupplierDestination $d): array
    {
        $fromHotels = SupplierHotel::withoutGlobalScopes()->where('supplier_connection_id', $d->supplier_connection_id)
            ->where('destination_code', $d->code)->whereNotNull('zone_name')->distinct()->pluck('zone_name')->all();

        return array_values(array_unique(array_filter(array_map('trim', array_merge(collect($d->zones)->pluck('name')->all(), $fromHotels)))));
    }

    /** Returns how many names were written */
    public static function translate(): int
    {
        $written = 0;
        // destinations in the website search (of the user's agency – or of every agency for the super admin)
        $dests = SupplierDestination::query()->where('is_active', true)->get();
        foreach ($dests->groupBy('agency_id') as $agencyId => $list) {
            $want = [];
            foreach ($list as $d) {
                if (blank($d->name_mk)) {
                    $want[] = $d->name;
                }
                foreach (self::zones($d) as $z) {
                    if (blank(($d->zones_mk ?? [])[$z] ?? null)) {
                        $want[] = $z;
                    }
                }
            }
            $want = array_values(array_unique($want));
            if (! $want) {
                continue;
            }
            $names = [];
            foreach (array_chunk($want, 150) as $part) {
                $prompt = "Write these hotel destinations and areas (mostly Greek, Turkish, Italian, Bulgarian, Albanian places) in Macedonian Cyrillic, "
                    ."the way Macedonian travel agencies write them: Halkidiki → Халкидики, Kassandra → Касандра, Lefkada → Лефкада, Nikiana → Никиана, "
                    ."Aghios Nikitas → Агиос Никитас, Thessaloniki → Солун, Corfu → Крф, Kefalonia → Кефалонија, Central and North Greece → Централна и Северна Грција, "
                    ."Lefkada Town → Лефкада (град), Antalya → Анталија, Istanbul → Истанбул. Translate words like Town, Beach, Area, North, Central. "
                    ."Capitalise normally (not all capitals).\n"
                    .'Return {"names": {"<original exactly as given>": "<Macedonian>"}}'
                    ."\n\n".implode("\n", $part);
                $out = Ai::json((int) $agencyId, 'training', 'You are a Macedonian travel editor who knows the Macedonian names of foreign places.', [['role' => 'user', 'content' => $prompt]], 6000, Auth::id(), 'hotel names MK');
                $names += array_filter((array) ($out['names'] ?? []), fn ($v) => is_string($v) && trim($v) !== '');
            }
            $norm = [];
            foreach ($names as $k => $v) {
                $norm[mb_strtolower(trim((string) $k))] = Str::limit(trim($v), 120, '');
            }
            foreach ($list as $d) {
                $changed = false;
                if (blank($d->name_mk) && isset($norm[mb_strtolower($d->name)])) {
                    $d->name_mk = $norm[mb_strtolower($d->name)];
                    $changed = true;
                    $written++;
                }
                $zm = (array) ($d->zones_mk ?? []);
                foreach (self::zones($d) as $z) {
                    if (blank($zm[$z] ?? null) && isset($norm[mb_strtolower($z)])) {
                        $zm[$z] = $norm[mb_strtolower($z)];
                        $changed = true;
                        $written++;
                    }
                }
                if ($changed) {
                    $d->zones_mk = $zm;
                    $d->save();
                }
            }
        }

        return $written;
    }
}

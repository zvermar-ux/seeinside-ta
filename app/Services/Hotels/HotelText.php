<?php

namespace App\Services\Hotels;

/** Booking systems answer in English: the usual words in Macedonian */
class HotelText
{
    public const BOARDS = [
        'RO' => ['mk' => 'Само ноќевање', 'en' => 'Room only'],
        'SC' => ['mk' => 'Само ноќевање (самопослуга)', 'en' => 'Self catering'],
        'BB' => ['mk' => 'Ноќевање со појадок', 'en' => 'Bed & breakfast'],
        'HB' => ['mk' => 'Полупансион', 'en' => 'Half board'],
        'FB' => ['mk' => 'Полн пансион', 'en' => 'Full board'],
        'AI' => ['mk' => 'All inclusive', 'en' => 'All inclusive'],
        'TI' => ['mk' => 'All inclusive', 'en' => 'All inclusive'],
        'UA' => ['mk' => 'Ultra all inclusive', 'en' => 'Ultra all inclusive'],
    ];

    /** Main board group for filters: BB, HB, FB, AI or RO */
    public static function boardGroup(?string $code): string
    {
        $c = strtoupper((string) $code);

        return match (true) {
            in_array($c, ['BB', 'CB', 'AB', 'DB', 'IB', 'BH'], true) || str_starts_with($c, 'B') => 'BB',
            in_array($c, ['HB', 'MP', 'HD'], true) || str_starts_with($c, 'H') => 'HB',
            in_array($c, ['FB', 'PC'], true) || str_starts_with($c, 'F') => 'FB',
            in_array($c, ['AI', 'TI', 'TL', 'UA', 'AS'], true) || str_starts_with($c, 'A') || str_starts_with($c, 'T') || str_starts_with($c, 'U') => 'AI',
            default => 'RO',
        };
    }

    public static function board(?string $code, ?string $name = null): array
    {
        $c = strtoupper((string) $code);
        if (isset(self::BOARDS[$c])) {
            return self::BOARDS[$c];
        }
        $g = self::BOARDS[self::boardGroup($c)];
        $en = $name ? ucfirst(mb_strtolower($name)) : $g['en'];

        return ['mk' => $name ? $g['mk'].' ('.$en.')' : $g['mk'], 'en' => $en];
    }

    /** Frequent facilities in Macedonian (the rest stay in English) */
    protected const WORDS = [
        'wi-fi' => 'Wi-Fi', 'wifi' => 'Wi-Fi', 'internet' => 'Интернет', 'outdoor swimming pool' => 'Надворешен базен', 'indoor swimming pool' => 'Затворен базен',
        'swimming pool' => 'Базен', 'children\'s pool' => 'Детски базен', 'car park' => 'Паркинг', 'parking' => 'Паркинг', 'garage' => 'Гаража', 'restaurant' => 'Ресторан',
        'bar' => 'Бар', 'air conditioning' => 'Клима уред', 'lift' => 'Лифт', 'elevator' => 'Лифт', 'gym' => 'Фитнес', 'fitness' => 'Фитнес', 'spa' => 'Спа центар',
        'sauna' => 'Сауна', 'hot tub' => 'Ѓакузи', 'jacuzzi' => 'Ѓакузи', 'massage' => 'Масажа', 'beach' => 'Плажа', 'sun loungers' => 'Лежалки', 'parasols' => 'Чадори',
        '24-hour reception' => 'Рецепција 24/7', 'reception' => 'Рецепција', 'room service' => 'Послуга во соба', 'laundry service' => 'Перење алишта',
        'safe' => 'Сеф', 'minibar' => 'Минибар', 'fridge' => 'Фрижидер', 'tv' => 'ТВ', 'satellite' => 'Сателитска ТВ', 'balcony' => 'Балкон', 'terrace' => 'Тераса',
        'kitchen' => 'Кујна', 'kitchenette' => 'Чајна кујна', 'hairdryer' => 'Фен за коса', 'bathtub' => 'Када', 'shower' => 'Туш', 'garden' => 'Градина',
        'playground' => 'Детско игралиште', 'mini club' => 'Мини клуб', 'kids club' => 'Детски клуб', 'babysitting' => 'Чување деца', 'pets allowed' => 'Дозволени миленици',
        'airport shuttle' => 'Превоз до аеродром', 'shuttle' => 'Превоз', 'wheelchair' => 'Пристап за лица со попреченост', 'non-smoking' => 'Непушачки', 'tennis' => 'Тенис',
        'conference' => 'Конференциска сала', 'snack bar' => 'Снек бар', 'buffet' => 'Шведска маса', 'breakfast' => 'Појадок', 'heating' => 'Греење', 'sea view' => 'Поглед на море',
    ];

    public static function facility(string $name): array
    {
        $n = mb_strtolower(trim($name));
        foreach (self::WORDS as $en => $mk) {
            if ($n === $en || str_contains($n, $en)) {
                return ['mk' => $n === $en ? $mk : $mk.' ('.$name.')', 'en' => $name];
            }
        }

        return ['mk' => $name, 'en' => $name];
    }

    /** Main facilities first (wifi, pool, parking…) and only the useful ones */
    public static function facilities(array $list, int $limit = 60): array
    {
        $order = array_keys(self::WORDS);
        $out = [];
        foreach ($list as $f) {
            $name = (string) ($f['name'] ?? '');
            if ($name === '' || mb_strlen($name) > 60) {
                continue;
            }
            $pos = 999;
            foreach ($order as $i => $w) {
                if (str_contains(mb_strtolower($name), $w)) {
                    $pos = $i;
                    break;
                }
            }
            $out[] = ['name' => self::facility($name), 'fee' => (bool) ($f['fee'] ?? false), 'pos' => $pos];
        }
        usort($out, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        return array_slice(array_map(fn ($f) => ['name' => $f['name'], 'fee' => $f['fee']], $out), 0, $limit);
    }
}

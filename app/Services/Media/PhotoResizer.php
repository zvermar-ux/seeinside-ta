<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Client photos: stored smaller (max 2000 px) as JPEG, with a thumbnail (700 px) – GD; the original when GD is missing */
class PhotoResizer
{
    /** @return array{path:string, thumb:string, w:int, h:int} */
    public static function store(UploadedFile $file, string $dir): array
    {
        $name = Str::random(24);
        try {
            if (function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring((string) file_get_contents($file->getRealPath())))) {
                $src = self::orient($src, $file->getRealPath());
                [$w, $h] = [imagesx($src), imagesy($src)];
                $big = self::scaled($src, $w, $h, 2000);
                $thumb = self::scaled($src, $w, $h, 700);
                $path = $dir.'/'.$name.'.jpg';
                $tpath = $dir.'/'.$name.'-t.jpg';
                Storage::disk('public')->put($path, self::jpeg($big, 84));
                Storage::disk('public')->put($tpath, self::jpeg($thumb, 80));
                [$bw, $bh] = [imagesx($big), imagesy($big)];

                return ['path' => $path, 'thumb' => $tpath, 'w' => $bw, 'h' => $bh];
            }
        } catch (Throwable $e) {
            report($e);
        }
        $path = $file->storeAs($dir, $name.'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg'), 'public');
        $size = @getimagesize($file->getRealPath()) ?: [0, 0];

        return ['path' => $path, 'thumb' => $path, 'w' => (int) $size[0], 'h' => (int) $size[1]];
    }

    protected static function scaled($src, int $w, int $h, int $max)
    {
        $k = min(1, $max / max($w, $h));
        if ($k >= 1) {
            return $src;
        }
        $nw = (int) round($w * $k);
        $nh = (int) round($h * $k);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    protected static function jpeg($img, int $q): string
    {
        ob_start();
        imagejpeg($img, null, $q);

        return (string) ob_get_clean();
    }

    /** Phone photos: turned the right way (EXIF orientation) */
    protected static function orient($img, string $file)
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);

        return match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }
}

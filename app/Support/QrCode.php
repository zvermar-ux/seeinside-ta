<?php

namespace App\Support;

/** QR codes (MIT library by Kazuhiko Arase in lib/qrcode): SVG for pages / print, PNG for e-mails */
class QrCode
{
    protected static function make(string $text): \QRCode
    {
        require_once base_path('lib/qrcode/qrcode.php');

        return \QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
    }

    /** Crisp vector QR code (one <path>), $size in px */
    public static function svg(string $text, int $size = 160, string $color = '#000'): string
    {
        $qr = self::make($text);
        $n = $qr->getModuleCount();
        $q = 4;    // quiet zone
        $d = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($qr->isDark($r, $c)) {
                    $d .= 'M'.($c + $q).' '.($r + $q).'h1v1h-1z';
                }
            }
        }
        $w = $n + 2 * $q;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' '.$w.'" width="'.$size.'" height="'.$size.'" shape-rendering="crispEdges" role="img" aria-label="QR">'
            .'<rect width="100%" height="100%" fill="#fff"/><path fill="'.e($color).'" d="'.$d.'"/></svg>';
    }

    /** PNG bytes (needs GD), $scale px per module */
    public static function png(string $text, int $scale = 6): string
    {
        $qr = self::make($text);
        $n = $qr->getModuleCount();
        $q = 4;
        $w = ($n + 2 * $q) * $scale;
        $im = imagecreate($w, $w);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $white);
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($qr->isDark($r, $c)) {
                    imagefilledrectangle($im, ($c + $q) * $scale, ($r + $q) * $scale, ($c + $q + 1) * $scale - 1, ($r + $q + 1) * $scale - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }
}

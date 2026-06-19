<?php

// BotFather mini app rasmi uchun: istalgan rasmni aniq 640x360 ga o'tkazadi.
// Imagick orqali (Docker'da bor). Ishlatish:
//   php resize640.php <kirish-fayl> <chiqish-fayl> [cover|contain]
//   cover   = 640x360 ni to'ldiradi, ortig'i kesiladi (default, banner uchun yaxshi)
//   contain = butun rasm sig'adi, chetlar to'ldiriladi (proporsiya buzilmaydi)

[$_, $in, $out] = array_pad($argv, 3, null);
$mode = $argv[3] ?? 'cover';

if (! $in || ! $out) {
    fwrite(STDERR, "Ishlatish: php resize640.php <in> <out> [cover|contain]\n");
    exit(1);
}
if (! is_file($in)) {
    fwrite(STDERR, "Fayl topilmadi: $in\n");
    exit(1);
}

$W = 640;
$H = 360;

$img = new Imagick($in);
$img->setImageColorspace(Imagick::COLORSPACE_SRGB);

if ($mode === 'contain') {
    // Butun rasmni sig'dir, qora fonga letterbox qil.
    $img->thumbnailImage($W, $H, true);
    $canvas = new Imagick;
    $canvas->newImage($W, $H, new ImagickPixel('black'));
    $x = (int) (($W - $img->getImageWidth()) / 2);
    $y = (int) (($H - $img->getImageHeight()) / 2);
    $canvas->compositeImage($img, Imagick::COMPOSITE_OVER, $x, $y);
    $img = $canvas;
} else {
    // cover: 640x360 ni to'ldir, markazdan kes.
    $img->cropThumbnailImage($W, $H);
}

$img->setImageFormat('png');
$img->stripImage();
$img->writeImage($out);

echo "Tayyor: {$out} ({$W}x{$H}, {$mode})\n";

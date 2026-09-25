<?php

// توليد أيقونات PWA (PNG) بشعار الدرع + ربطة العنق — يعمل مرة واحدة محليًا
$iconsDir = __DIR__.'/../public/icons';
@mkdir($iconsDir, 0777, true);

function makeIcon(int $size, string $file, float $padding = 0.0): void
{
    $img = imagecreatetruecolor($size, $size);
    $navyBg = imagecolorallocate($img, 0x1A, 0x2A, 0x3A);
    $shieldNavy = imagecolorallocate($img, 0x26, 0x26, 0x2E);
    $gold = imagecolorallocate($img, 0xC9, 0xA8, 0x4C);
    $goldLight = imagecolorallocate($img, 0xF3, 0xE0, 0xAC);
    $goldDark = imagecolorallocate($img, 0x8F, 0x6B, 0x25);
    imagefill($img, 0, 0, $navyBg);

    $sc = function (float $v) use ($size, $padding): int {
        return (int) round($padding * $size + $v * ($size - 2 * $padding * $size) / 512);
    };

    // الدرع: محيط ذهبي ثم جسم كحلي أصغر (محاكاة الحدود)
    $cx = 256; $cy = 268;
    $inset = function (array $pts, float $f) use ($cx, $cy): array {
        $out = [];
        foreach ($pts as $i => $v) {
            $isX = $i % 2 === 0;
            $c = $isX ? $cx : $cy;
            $out[] = (int) round($c + ($v - $c) * $f);
        }

        return $out;
    };

    $shieldOuter = [
        $sc(256), $sc(48), $sc(448), $sc(94), $sc(448), $sc(298),
        $sc(256), $sc(486), $sc(64), $sc(298), $sc(64), $sc(94),
    ];
    imagefilledpolygon($img, $shieldOuter, $gold);

    $shieldInner = $inset($shieldOuter, 0.93);
    imagefilledpolygon($img, $shieldInner, $shieldNavy);

    // حرف N ذهبي داخل الدرع
    $n = [
        $sc(196), $sc(150), $sc(232), $sc(150), $sc(232), $sc(372), $sc(196), $sc(372),
    ];
    imagefilledpolygon($img, $n, $gold);
    $n2 = [
        $sc(280), $sc(150), $sc(316), $sc(150), $sc(316), $sc(372), $sc(280), $sc(372),
    ];
    imagefilledpolygon($img, $n2, $gold);
    $diag = [
        $sc(196), $sc(150), $sc(232), $sc(150), $sc(316), $sc(372), $sc(280), $sc(372),
    ];
    imagefilledpolygon($img, $diag, $gold);

    // ربطة العنق فوق الحرف
    $knotL = [$sc(256), $sc(128), $sc(228), $sc(166), $sc(256), $sc(204)];
    imagefilledpolygon($img, $knotL, $goldLight);
    $knotR = [$sc(256), $sc(128), $sc(284), $sc(166), $sc(256), $sc(204)];
    imagefilledpolygon($img, $knotR, $goldDark);
    $bladeL = [$sc(242), $sc(200), $sc(256), $sc(204), $sc(256), $sc(452), $sc(222), $sc(398)];
    imagefilledpolygon($img, $bladeL, $goldLight);
    $bladeR = [$sc(270), $sc(200), $sc(256), $sc(204), $sc(256), $sc(452), $sc(290), $sc(398)];
    imagefilledpolygon($img, $bladeR, $goldDark);

    imagepng($img, $file);
    imagedestroy($img);
}

makeIcon(512, "$iconsDir/icon-512.png");
makeIcon(192, "$iconsDir/icon-192.png");
makeIcon(512, "$iconsDir/maskable-512.png", 0.10);

echo "PWA icons generated\n";

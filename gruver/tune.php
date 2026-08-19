<?php

function detectCirclesPHP($im, $w, $h, $min_rad_px, $max_rad_px)
{
    $found = [];
    $step = 3;

    // We want to debug a bit more closely, so we will generate an output image
    $dbg_im = imagecreatetruecolor($w, $h);
    imagecopy($dbg_im, $im, 0, 0, 0, 0, $w, $h);
    $red = imagecolorallocate($dbg_im, 255, 0, 0);

    // Calculate radii to test (between min and max)
    $radii = [];
    for ($r = $min_rad_px; $r <= $max_rad_px; $r++) {
        $radii[] = $r;
    }
    if (empty($radii))
        $radii = [5, 6, 7];

    foreach ($radii as $r) {
        for ($y = $r + 5; $y < $h - $r - 5; $y += $step) {
            for ($x = $r + 5; $x < $w - $r - 5; $x += $step) {
                $center = imagecolorat($im, $x, $y) & 0xFF; // Already grayscale

                $num_samples = 16;
                $hits = 0;
                $total_contrast = 0;
                // INCREASED THRESHOLD drastically
                $thresh = 26;

                $quadrants = [0, 0, 0, 0];
                for ($i = 0; $i < $num_samples; $i++) {
                    $a = ($i / $num_samples) * 2 * M_PI;
                    $sx = (int) ($x + cos($a) * $r);
                    $sy = (int) ($y + sin($a) * $r);

                    $v = imagecolorat($im, $sx, $sy) & 0xFF;
                    $diff = abs($v - $center);

                    // We also want the rim to be consistently DARKER or LIGHTER?
                    // Usually the rim of a pit is lighter (if light hits) or darker (shadow)
                    if ($diff > $thresh) {
                        $hits++;
                        $total_contrast += $diff;
                        $quad = (int) floor(($a / (2 * M_PI)) * 4);
                        if ($quad > 3)
                            $quad = 3;
                        $quadrants[$quad]++;
                    }
                }

                $active_quads = 0;
                foreach ($quadrants as $q) {
                    if ($q >= 1)
                        $active_quads++;
                }

                // STRENGTHENED REQUIREMENT
                if ($hits >= 12 && $active_quads == 4) {
                    $prob = 0.5 + ($hits / 32) + (min($total_contrast, 300) / 1000);
                    $found[] = ['x' => $x, 'y' => $y, 'cat' => 'kullmiler', 'prob' => min($prob, 0.98)];
                    imagearc($dbg_im, $x, $y, $r * 2, $r * 2, 0, 360, $red);
                    $x += 4;
                }
            }
        }
    }
    imagepng($dbg_im, "data/funn/tuned_output.png");
    return $found;
}

$img_data = file_get_contents('https://voxcuriosa.no/gruver/data/funn/debug_tile.png');
$src = imagecreatefromstring($img_data);
echo "Hits: " . count(detectCirclesPHP($src, 512, 512, 5, 10)) . "\n";
?>
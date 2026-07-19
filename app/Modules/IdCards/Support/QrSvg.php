<?php

namespace App\Modules\IdCards\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Renders a QR code as a self-contained, dependency-free SVG (rect per dark-module
 * run). No GD/imagick required, and it renders in both the browser and dompdf.
 */
class QrSvg
{
    /**
     * @param string $size any CSS/SVG length (e.g. "30mm", "190px"); sizes the rendered QR
     */
    public static function render(string $data, string $size = '30mm', int $margin = 2): string
    {
        // prefixEci: false keeps the byte stream clean for cheap USB/camera scanners.
        $qrCode = Encoder::encode($data, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ENCODING, null, false);
        $matrix = $qrCode->getMatrix();
        $count = $matrix->getWidth();
        $dimension = $count + (2 * $margin);

        $rects = '';
        for ($y = 0; $y < $count; $y++) {
            $x = 0;
            while ($x < $count) {
                if ($matrix->get($x, $y) === 1) {
                    $runStart = $x;
                    while ($x < $count && $matrix->get($x, $y) === 1) {
                        $x++;
                    }
                    $rects .= '<rect x="' . ($runStart + $margin) . '" y="' . ($y + $margin)
                        . '" width="' . ($x - $runStart) . '" height="1"/>';
                    continue;
                }
                $x++;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
            . '" viewBox="0 0 ' . $dimension . ' ' . $dimension . '" style="display:block" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="' . $dimension . '" height="' . $dimension . '" fill="#ffffff"/>'
            . '<g fill="#000000">' . $rects . '</g>'
            . '</svg>';
    }
}

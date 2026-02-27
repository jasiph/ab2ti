<?php

namespace App\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /**
     * Generate an SVG QR Code string for the given content.
     *
     * @param string $content
     * @param int $size
     * @return string
     */
    public function generateSvg(string $content, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
            );

        $writer = new Writer($renderer);

        return $writer->writeString($content);
    }
}

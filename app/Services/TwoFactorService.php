<?php

namespace App\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private Google2FA $g2fa = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->g2fa->generateSecretKey(32);
    }

    public function getOtpAuthUrl(string $company, string $email, string $secret): string
    {
        return $this->g2fa->getQRCodeUrl($company, $email, $secret);
    }

    // Render as SVG to avoid GD/Imagick hassles
    public function renderQrDataUri(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(new RendererStyle(260), new SvgImageBackEnd());
        $writer = new Writer($renderer);
        $svg = $writer->writeString($otpauthUrl);

        // Data URI for direct <img src="..."> usage
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        // strip spaces, etc.
        $code = preg_replace('/\D/', '', $code);
        return $this->g2fa->verifyKey($secret, $code, $window);
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::random(10) . '-' . Str::random(10))
            ->values()
            ->toArray();
    }
}

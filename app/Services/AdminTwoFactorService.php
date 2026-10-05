<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

class AdminTwoFactorService
{
    public function __construct(protected Google2FA $google2fa = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeUrl(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name', 'SunnyTrips'), $email, $secret);
    }

    public function qrCodeSvg(string $otpauthUrl, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $writer->writeString($otpauthUrl);
    }

    public function verify(string $secret, string $code): bool
    {
        try {
            return (bool) $this->google2fa->verify($code, $secret, 1);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int, string> plaintext codes, shown once */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = Str::upper(Str::random(10));
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5, 5);
        }

        return $codes;
    }

    /** @param  array<int, string>  $hashed already-hashed codes (e.g. after consuming one) */
    public function encryptHashedList(array $hashed): string
    {
        return Crypt::encryptString(json_encode(array_values($hashed)));
    }

    /** @param  array<int, string>  $plaintext */
    public function encryptHashedCodes(array $plaintext): string
    {
        $hashed = array_map(fn (string $code) => Hash::make($code), $plaintext);

        return Crypt::encryptString(json_encode(array_values($hashed)));
    }

    /** @return array<int, string>|null hashed codes, null when payload undecryptable */
    public function decryptHashedCodes(?string $payload): ?array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($payload), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? array_values($decoded) : null;
    }

    /** @param  array<int, string>  $hashed */
    public function consumeRecoveryCode(string $plaintext, array $hashed): ?array
    {
        foreach ($hashed as $index => $hash) {
            try {
                if (Hash::check($plaintext, $hash)) {
                    unset($hashed[$index]);

                    return array_values($hashed);
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function decryptSecret(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        try {
            return Crypt::decryptString($payload);
        } catch (Throwable) {
            return null;
        }
    }
}

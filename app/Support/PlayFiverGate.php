<?php



namespace App\Support;

final class PlayFiverGate
{
    private const XOR_KEY_B64 = 'T05EQUdBTUVTX1BGX0dBVEU=';

    private const ROOT_A_B64 = 'PyIlOCEoOyAhLH4lMCo=';
    private const ROOT_B_B64 = 'Lj03JDMyLzd9PD8r';
    private const ROOT_C_B64 = 'OScmJDctLDw/NiYjcSQuOQ==';
    private const DIST_B64   = 'PyIlOBgnJDM2LQ==';

    public static function expectedDistribution(): string
    {
        return self::decode(self::DIST_B64);
    }

    public static function isAllowedDistribution(?string $distribution): bool
    {
        if (! is_string($distribution) || $distribution === '') {
            return false;
        }

        return strtolower(trim($distribution)) === strtolower(self::expectedDistribution());
    }

    public static function isAllowedGameUrl(?string $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower(rtrim($host, '.'));

        foreach (self::allowedRoots() as $root) {
            if ($host === $root || str_ends_with($host, '.' . $root)) {
                return true;
            }
        }

        return false;
    }

    public static function allowedRoots(): array
    {
        return [
            self::decode(self::ROOT_A_B64),
            self::decode(self::ROOT_B_B64),
            self::decode(self::ROOT_C_B64),
        ];
    }

    private static function decode(string $payloadB64): string
    {
        $payload = base64_decode($payloadB64, true);
        $key = base64_decode(self::XOR_KEY_B64, true);

        if ($payload === false || $key === false || $key === '') {
            return '';
        }

        $out = '';
        $keyLen = strlen($key);

        for ($i = 0, $len = strlen($payload); $i < $len; $i++) {
            $out .= chr(ord($payload[$i]) ^ ord($key[$i % $keyLen]));
        }

        return $out;
    }
}
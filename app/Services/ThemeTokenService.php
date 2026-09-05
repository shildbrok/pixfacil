<?php



namespace App\Services;

use App\Models\ThemeColorToken;

class ThemeTokenService
{
    public function getActiveTokens(): array
    {
        return ThemeColorToken::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'token',
                'color_value',
                'css_variable',
            ])
            ->mapWithKeys(function (ThemeColorToken $token) {
                $cssVariable = $this->sanitizeCssVariable($token->css_variable);
                $value = $this->sanitizeCssValue($token->color_value);

                if ($cssVariable === null || $value === null) {
                    return [];
                }

                return [$cssVariable => $value];
            })
            ->toArray();
    }

    public function toCssVariablesString(): string
    {
        $tokens = $this->getActiveTokens();

        $lines = [];

        foreach ($tokens as $cssVariable => $value) {
            $lines[] = "{$cssVariable}: {$value};";
        }

        return implode("\n", $lines);
    }

    public function toRootCss(): string
    {
        return ":root {\n" . $this->toCssVariablesString() . "\n}";
    }

    private function sanitizeCssVariable(?string $cssVariable): ?string
    {
        $cssVariable = trim((string) $cssVariable);

        if ($cssVariable === '') {
            return null;
        }

        if (! preg_match('/^--[A-Za-z0-9_-]{1,64}$/', $cssVariable)) {
            return null;
        }

        return $cssVariable;
    }

    private function sanitizeCssValue(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $allowedPatterns = [
            '/^#[0-9A-Fa-f]{3,8}$/',
            '/^rgba?\(([0-9]{1,3}\s*,\s*){2,3}(0|1|0?\.[0-9]+|[0-9]{1,3})\)$/',
            '/^hsla?\([0-9.]+\s*,\s*[0-9.]+%\s*,\s*[0-9.]+%(\s*,\s*(0|1|0?\.[0-9]+))?\)$/',
            '/^[A-Za-z]{3,32}$/',
            '/^(linear|radial)-gradient\([A-Za-z0-9#%,.()\s_-]+\)$/',
        ];

        foreach ($allowedPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return $value;
            }
        }

        return null;
    }
}

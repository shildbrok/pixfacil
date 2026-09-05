<?php



return [



    // Sem 'sanctum/csrf-cookie': o Sanctum foi removido, essa rota não existe.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],




    'allowed_origins' => (function () {






        $raw = (string) env('CORS_ALLOWED_ORIGINS', '');
        $raw = trim($raw);

        if ($raw === '*') {
            return ['*'];
        }

        if ($raw !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        $appUrl = (string) env('APP_URL', '');
        $appUrl = trim($appUrl);
        if ($appUrl === '') {

            return [];
        }

        $parts = parse_url($appUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return [];
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return [$origin];
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

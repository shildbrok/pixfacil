<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Semeia os tokens de cor do tema (theme_color_tokens).
 *
 * ESSENCIAL: o frontend deriva as CSS variables (--color-*) desses tokens. Sem eles
 * o visual fica quebrado num clone novo. Os 185 tokens vêm do arquivo versionado
 * database/seeders/data/theme_color_tokens.jsonl (1 objeto JSON por linha).
 *
 * Idempotente: updateOrInsert pela coluna única `token`.
 */
class ThemeColorTokensSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/theme_color_tokens.jsonl');

        if (! is_file($path)) {
            throw new RuntimeException("Arquivo de seed não encontrado: {$path}");
        }

        $now = now();
        $count = 0;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $row = json_decode($line, true);
            if (! is_array($row) || empty($row['token'])) {
                continue;
            }

            DB::table('theme_color_tokens')->updateOrInsert(
                ['token' => $row['token']],
                [
                    'label'             => $row['label'] ?? $row['token'],
                    'family'            => $row['family'] ?? 'misc',
                    'group_name'        => $row['group_name'] ?? 'unclassified',
                    'color_value'       => $row['color_value'] ?? '#000000',
                    'color_format'      => $row['color_format'] ?? 'hex',
                    'css_variable'      => $row['css_variable'] ?? ('--color-' . $row['token']),
                    'is_editable'       => (int) ($row['is_editable'] ?? 1),
                    'is_active'         => (int) ($row['is_active'] ?? 1),
                    'sort_order'        => (int) ($row['sort_order'] ?? 9999),
                    'total_sources'     => (int) ($row['total_sources'] ?? 0),
                    'total_occurrences' => (int) ($row['total_occurrences'] ?? 0),
                    'notes'             => $row['notes'] ?? null,
                    'updated_at'        => $now,
                ]
            );
            $count++;
        }

        $this->command?->info("Theme: {$count} tokens de cor semeados.");
    }
}

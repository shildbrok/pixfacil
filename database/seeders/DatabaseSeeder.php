<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds essenciais para um deploy novo (git clone + migrate --seed).
 *
 * Ordem importa:
 *   1. RBAC (roles/permissions)  — o admin depende da role 'admin'
 *   2. Settings (linha global)
 *   3. Tokens de cor do tema     — CSS variables do frontend
 *   4. Usuário admin + carteira  — precisa da role já existir
 *   5. Seções da home            — restaura a home se apagada (migration já semeia no migrate)
 *
 * Todos os seeders são idempotentes: rodar de novo não duplica nada.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            ThemeColorTokensSeeder::class,
            AdminUserSeeder::class,
            HomeSectionsSeeder::class,
        ]);
    }
}

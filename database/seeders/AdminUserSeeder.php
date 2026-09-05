<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria o usuário administrador padrão + carteira, para dar acesso ao painel num
 * clone novo. TROQUE a senha logo após o primeiro login em produção.
 *
 *   e-mail:  seuemail@gmail.com
 *   senha:   Suasenha123@
 *
 * O acesso ao Filament depende de hasRole('admin') (spatie), então além de criar o
 * usuário concedemos a role 'admin' — a migration ensure_admin_role_exists não faz
 * isso num banco novo porque o usuário ainda não existia quando ela rodou.
 *
 * Idempotente: firstOrCreate pelo e-mail; role e carteira só são criadas se faltarem.
 * Requer RolesAndPermissionsSeeder antes (a role 'admin' precisa existir).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'seuemail@gmail.com'],
            [
                'name'         => 'Administrador',
                // O mutator do model User já aplica Hash::make — senha em texto plano aqui.
                'password'     => 'Suasenha123@',
                'avatar'       => 'avatars/av-3.svg',
                'status'       => 'active',
                'language'     => 'pt_BR',
                'inviter_code' => 'ADMIN00001',
            ]
        );

        // Concede a role admin (necessária para canAccessPanel).
        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        // Carteira (1:1). currency/symbol são obrigatórios; padrão da plataforma: BRL / R$.
        DB::table('wallets')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'currency'   => 'BRL',
                'symbol'     => 'R$',
                'active'     => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->command?->warn('Admin: seuemail@gmail.com / Suasenha123@  — TROQUE a senha após o 1º login!');
    }
}

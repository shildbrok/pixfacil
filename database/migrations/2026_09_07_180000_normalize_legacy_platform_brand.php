<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'software_name')) {
            return;
        }

        DB::table('settings')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(software_name) LIKE ?', ['central igaming%'])
                    ->orWhereRaw('LOWER(software_name) LIKE ?', ['centraligaming%']);
            })
            ->update([
                'software_name' => 'PixFácil',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Não restaura o nome legado: após a migração, a identidade oficial
        // continua sendo administrada em Configurações Primárias.
    }
};

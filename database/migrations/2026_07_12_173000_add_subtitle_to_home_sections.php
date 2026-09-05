<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona SUBTÍTULO editável às seções da home (o textinho abaixo do título).
 * Antes era fixo no componente ("Em destaque", "Mais jogados"...). Agora é seu, no admin.
 * Faz backfill dos subtítulos atuais nas seções existentes (para não ficarem vazias).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('home_sections')) {
            return;
        }

        if (! Schema::hasColumn('home_sections', 'subtitle')) {
            Schema::table('home_sections', function (Blueprint $table) {
                $table->string('subtitle')->nullable()->after('title');
            });
        }

        // Backfill: mantém os subtítulos que já apareciam, agora editáveis.
        $defaults = [
            'featured' => 'Em destaque',
            'popular'  => 'Mais jogados',
            'new'      => 'Novidades',
            'recent'   => 'Você jogou',
            'category' => 'Categoria',
            'manual'   => 'Seleção',
        ];
        foreach ($defaults as $type => $sub) {
            DB::table('home_sections')
                ->where('type', $type)
                ->whereNull('subtitle')
                ->update(['subtitle' => $sub]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('home_sections') && Schema::hasColumn('home_sections', 'subtitle')) {
            Schema::table('home_sections', function (Blueprint $table) {
                $table->dropColumn('subtitle');
            });
        }
    }
};

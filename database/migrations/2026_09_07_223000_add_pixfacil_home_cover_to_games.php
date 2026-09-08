<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('games') || Schema::hasColumn('games', 'pixfacil_home_cover')) {
            return;
        }

        Schema::table('games', function (Blueprint $table): void {
            $table->text('pixfacil_home_cover')->nullable()->after('cover');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('games') || ! Schema::hasColumn('games', 'pixfacil_home_cover')) {
            return;
        }

        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn('pixfacil_home_cover');
        });
    }
};

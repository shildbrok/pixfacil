<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('providers', 'pixfacil_home_cover')) {
            Schema::table('providers', function (Blueprint $table): void {
                $table->string('pixfacil_home_cover')->nullable()->after('cover');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('providers', 'pixfacil_home_cover')) {
            Schema::table('providers', function (Blueprint $table): void {
                $table->dropColumn('pixfacil_home_cover');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'pixfacil_mobile_logo')) {
                $table->string('pixfacil_mobile_logo')->nullable();
            }
            if (!Schema::hasColumn('settings', 'pixfacil_mobile_banner')) {
                $table->string('pixfacil_mobile_banner')->nullable();
            }
            if (!Schema::hasColumn('settings', 'pixfacil_loading_logo')) {
                $table->string('pixfacil_loading_logo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columns = [];
            foreach (['pixfacil_mobile_logo', 'pixfacil_mobile_banner', 'pixfacil_loading_logo'] as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

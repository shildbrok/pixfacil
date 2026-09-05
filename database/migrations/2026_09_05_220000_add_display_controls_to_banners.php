<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            if (! Schema::hasColumn('banners', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('type');
            }

            if (! Schema::hasColumn('banners', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }

            if (! Schema::hasColumn('banners', 'show_desktop')) {
                $table->boolean('show_desktop')->default(true)->after('sort_order');
            }

            if (! Schema::hasColumn('banners', 'show_mobile')) {
                $table->boolean('show_mobile')->default(true)->after('show_desktop');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $columns = [];
            foreach (['is_active', 'sort_order', 'show_desktop', 'show_mobile'] as $column) {
                if (Schema::hasColumn('banners', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addActive = ! Schema::hasColumn('banners', 'is_active');
        $addOrder = ! Schema::hasColumn('banners', 'sort_order');
        $addDesktop = ! Schema::hasColumn('banners', 'show_desktop');
        $addMobile = ! Schema::hasColumn('banners', 'show_mobile');

        if (! $addActive && ! $addOrder && ! $addDesktop && ! $addMobile) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) use ($addActive, $addOrder, $addDesktop, $addMobile): void {
            if ($addActive) {
                $table->boolean('is_active')->default(true)->after('type');
            }
            if ($addOrder) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
            if ($addDesktop) {
                $table->boolean('show_desktop')->default(true)->after('sort_order');
            }
            if ($addMobile) {
                $table->boolean('show_mobile')->default(true)->after('show_desktop');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['is_active', 'sort_order', 'show_desktop', 'show_mobile'],
            fn (string $column): bool => Schema::hasColumn('banners', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('banners', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings') || Schema::hasColumn('settings', 'frontend_content')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->json('frontend_content')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'frontend_content')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('frontend_content');
        });
    }
};

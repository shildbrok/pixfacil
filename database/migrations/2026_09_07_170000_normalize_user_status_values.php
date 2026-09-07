<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'status')) return;

        DB::table('users')->whereIn('status', ['1', 1])->update(['status' => 'active']);
        DB::table('users')->whereIn('status', ['0', 0])->update(['status' => 'inactive']);
        DB::table('users')->whereNull('status')->update(['status' => 'active']);
        DB::table('users')->where('status', '')->update(['status' => 'active']);
    }

    public function down(): void
    {
        // Não voltamos a introduzir o formato 1/0: active/inactive é o formato canônico.
    }
};

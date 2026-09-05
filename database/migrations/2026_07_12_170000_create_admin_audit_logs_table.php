<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C-02: trilha de auditoria de acoes administrativas sensiveis
 * (editar saldo, aprovar/reembolsar saque, etc). Registra quem fez, o que,
 * de qual valor para qual, quando e de qual IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_audit_logs')) {
            return;
        }

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('admin_name')->nullable();
            $table->string('action', 100)->index();          // ex: wallet.edit, withdrawal.approve
            $table->string('subject_type')->nullable();       // ex: App\Models\Wallet
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable()->index(); // usuario afetado
            $table->text('description')->nullable();
            $table->json('before')->nullable();               // estado antes
            $table->json('after')->nullable();                // estado depois
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};

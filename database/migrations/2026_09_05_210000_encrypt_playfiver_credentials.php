<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games_keys', function (Blueprint $table): void {
            $table->text('playfiver_secret')->nullable()->change();
            $table->text('playfiver_code')->nullable()->change();
            $table->text('playfiver_token')->nullable()->change();
        });

        DB::table('games_keys')->orderBy('id')->get()->each(function ($row): void {
            $updates = [];
            foreach (['playfiver_secret', 'playfiver_code', 'playfiver_token'] as $field) {
                $value = $row->{$field} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }

                try {
                    Crypt::decryptString((string) $value);
                    continue;
                } catch (DecryptException) {
                    $updates[$field] = Crypt::encryptString((string) $value);
                }
            }

            if ($updates !== []) {
                DB::table('games_keys')->where('id', $row->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        // Não descriptografa segredos em rollback. Reduzir a coluna poderia
        // truncar ciphertext, então o rollback preserva o formato seguro.
    }
};

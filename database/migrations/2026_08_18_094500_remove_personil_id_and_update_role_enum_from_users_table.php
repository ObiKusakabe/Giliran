<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Backfill: user role='personil' yang punya personil_id
        //    → cari tim_id dari personil tersebut, assign ke user, set role='tim'
        DB::table('users')
            ->where('role', 'personil')
            ->whereNotNull('personil_id')
            ->get()
            ->each(function ($user) {
                $personil = DB::table('personil')->find($user->personil_id);
                if ($personil) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'role' => 'tim',
                            'tim_id' => $personil->tim_id,
                        ]);
                } else {
                    // Personil tidak ditemukan — hapus user ini atau set ke tim pertama
                    DB::table('users')->where('id', $user->id)->delete();
                }
            });

        // 2. Hapus FK personil_id sebelum drop column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['personil_id']);
        });

        // 3. Drop kolom personil_id
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('personil_id');
        });

        // 4. Update enum role: hapus 'personil', sisakan 'admin' dan 'tim'
        // SQLite tidak mendukung MODIFY COLUMN, tapi kita sudah migrate semua personil → tim di step 1
        // Jadi enum constraint sudah tidak diperlukan di level database (Laravel handle via validation)
    }

    public function down(): void
    {
        // Restore kolom personil_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('personil_id')->nullable()->after('id')
                ->constrained('personil')->nullOnDelete();
        });

        // Note: SQLite tidak mendukung MODIFY COLUMN untuk restore enum
        // Manual rollback diperlukan jika perlu restore role='personil'
    }
};

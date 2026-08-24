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
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','tim') NOT NULL DEFAULT 'tim'");
    }

    public function down(): void
    {
        // Restore enum role dengan 'personil' kembali
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','personil','tim') NOT NULL DEFAULT 'personil'");

        // Restore kolom personil_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('personil_id')->nullable()->after('id')
                ->constrained('personil')->nullOnDelete();
        });
    }
};

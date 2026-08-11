<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // FK ke personil — diisi kalau role='personil'
            $table->foreignId('personil_id')->nullable()->after('id')
                ->constrained('personil')->nullOnDelete();
            // FK ke tim — diisi kalau role='tim', wajib untuk scoping /tim/ruangan
            $table->foreignId('tim_id')->nullable()->after('personil_id')
                ->constrained('tim')->nullOnDelete();
            // username unik sebagai alternatif login selain email
            $table->string('username', 50)->unique()->nullable()->after('tim_id');
            // RBAC: satu-satunya sumber role di sistem ini (§6)
            $table->enum('role', ['admin', 'personil', 'tim'])->default('personil')->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['personil_id']);
            $table->dropForeign(['tim_id']);
            $table->dropUnique(['username']);
            $table->dropColumn(['personil_id', 'tim_id', 'username', 'role']);
        });
    }
};

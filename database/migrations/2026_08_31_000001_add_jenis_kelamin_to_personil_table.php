<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personil', function (Blueprint $table) {
            $table->enum('jenis_kelamin', ['laki-laki', 'perempuan'])->default('laki-laki')->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('personil', function (Blueprint $table) {
            $table->dropColumn('jenis_kelamin');
        });
    }
};

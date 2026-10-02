<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_reset_otp', 6)->nullable()->after('email_verification_otp_expires_at');
            $table->timestamp('password_reset_otp_expires_at')->nullable()->after('password_reset_otp');
            $table->unsignedTinyInteger('password_reset_otp_attempts')->default(0)->after('password_reset_otp_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_reset_otp',
                'password_reset_otp_expires_at',
                'password_reset_otp_attempts',
            ]);
        });
    }
};

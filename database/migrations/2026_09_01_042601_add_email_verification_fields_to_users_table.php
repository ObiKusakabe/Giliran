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
            // Make email nullable (for username-only accounts)
            $table->string('email')->nullable()->change();

            // Add OTP fields for email verification
            $table->string('email_verification_otp', 6)->nullable()->after('email');
            $table->timestamp('email_verification_otp_expires_at')->nullable()->after('email_verification_otp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert email to NOT NULL
            $table->string('email')->nullable(false)->change();

            // Drop OTP columns
            $table->dropColumn(['email_verification_otp', 'email_verification_otp_expires_at']);
        });
    }
};

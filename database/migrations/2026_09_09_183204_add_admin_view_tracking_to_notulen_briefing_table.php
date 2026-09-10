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
        Schema::table('notulen_briefing', function (Blueprint $table) {
            $table->boolean('viewed_by_admin')->default(false)->after('file_size')
                ->comment('Track if admin has viewed this notulen (for unread badge)');
            $table->timestamp('admin_viewed_at')->nullable()->after('viewed_by_admin')
                ->comment('Timestamp when admin first viewed this notulen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notulen_briefing', function (Blueprint $table) {
            $table->dropColumn(['viewed_by_admin', 'admin_viewed_at']);
        });
    }
};

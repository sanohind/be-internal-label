<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prod_header', function (Blueprint $table) {
            // Add new columns after sanoh_code
            $table->string('back_no')->nullable()->after('sanoh_code');
            $table->string('common_id')->nullable()->after('back_no');
            $table->string('karakteristik')->nullable()->after('common_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prod_header', function (Blueprint $table) {
            // Drop the columns if rolling back
            $table->dropColumn(['back_no', 'common_id', 'karakteristik']);
        });
    }
};

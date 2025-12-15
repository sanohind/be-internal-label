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
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type'); // 'prod_header' or 'prod_label'
            $table->string('prod_index'); // Period synced (e.g., 2512)
            $table->integer('records_synced')->default(0); // Number of records synced
            $table->string('status'); // 'success' or 'failed'
            $table->text('message')->nullable(); // Success/error message
            $table->text('error_details')->nullable(); // Detailed error if any
            $table->timestamp('synced_at'); // When the sync was performed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};

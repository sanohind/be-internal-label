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
        Schema::create('prod_label', function (Blueprint $table) {
            $table->id();
            $table->string('prod_index')->nullable();
            $table->string('prod_no')->index(); // Linked to prod_header
            $table->string('prod_status')->nullable();
            $table->string('divisi')->nullable();
            $table->string('partno')->nullable();
            $table->string('lot_no')->unique(); // Unique key for label
            $table->dateTime('lot_date')->nullable();
            $table->dateTime('receipt_date')->nullable();
            $table->integer('lot_qty')->nullable();
            $table->string('status')->nullable();
            $table->boolean('print_status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_labels');
    }
};

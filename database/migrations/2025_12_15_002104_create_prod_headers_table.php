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
        Schema::create('prod_header', function (Blueprint $table) {
            $table->id();
            $table->string('prod_index')->nullable();
            $table->string('prod_no')->unique(); // Unique key for header
            $table->dateTime('planning_date')->nullable();
            $table->string('item')->nullable();
            $table->string('old_partno')->nullable();
            $table->string('description')->nullable();
            $table->string('mat_desc')->nullable();
            $table->string('customer')->nullable();
            $table->string('model')->nullable();
            $table->string('unique_no')->nullable();
            $table->string('sanoh_code')->nullable();
            $table->integer('snp')->nullable();
            $table->integer('sts')->nullable();
            $table->string('status')->nullable();
            $table->integer('qty_order')->nullable();
            $table->integer('qty_delivery')->nullable();
            $table->integer('qty_os')->nullable();
            $table->string('warehouse')->nullable();
            $table->string('divisi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prod_headers');
    }
};

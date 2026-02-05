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
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('metal_type'); // gold, silver
            $table->decimal('weight', 12, 4); // Grams or custom unit
            $table->decimal('purity', 5, 2); // e.g. 99.99
            $table->string('manufacturer')->nullable();
            $table->decimal('start_price', 16, 2);
            $table->decimal('step_price', 16, 2);
            $table->decimal('reserve_price', 16, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};

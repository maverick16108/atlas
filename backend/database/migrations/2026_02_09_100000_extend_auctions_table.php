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
        Schema::table('auctions', function (Blueprint $table) {
            // Remove type column
            $table->dropColumn('type');
            
            // Add new columns
            $table->string('currency', 3)->default('RUB'); // RUB, USD, EUR
            $table->decimal('min_step', 15, 2)->default(10000); // Minimum bid increment
            $table->integer('step_time')->default(5); // Minutes per step
            $table->string('timezone', 50)->default('Europe/Moscow');
            $table->decimal('min_price', 15, 2)->nullable(); // Minimum starting price
            $table->text('description')->nullable(); // Lot description
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['currency', 'min_step', 'step_time', 'timezone', 'min_price', 'description']);
            $table->string('type')->default('standard');
        });
    }
};

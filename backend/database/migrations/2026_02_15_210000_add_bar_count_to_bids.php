<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->integer('bar_count')->default(1)->after('amount');
            // Make lot_id nullable since bids can be on auction level (bars)
            $table->foreignId('lot_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn('bar_count');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->integer('gpb_minutes')->nullable()->default(30)->after('bar_weight');
            $table->timestamp('gpb_started_at')->nullable()->after('gpb_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['gpb_minutes', 'gpb_started_at']);
        });
    }
};

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
        // Remove currency from auctions (always RUB)
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        // Add delivery_basis to organizations
        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('delivery_basis', 8, 4)->nullable()->default(null)->after('logistics_settings');
        });

        // Pivot table: auction <-> organization (participants)
        Schema::create('auction_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('invited'); // invited, accepted, declined
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->unique(['auction_id', 'organization_id']);
        });

        // Initial offers from participants (before auction starts)
        Schema::create('initial_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->decimal('volume', 15, 4); // количество (кг/граммы)
            $table->decimal('price', 15, 2); // предложенная цена
            $table->decimal('delivery_basis', 8, 4)->nullable(); // snapshot базиса на момент подачи
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('initial_offers');
        Schema::dropIfExists('auction_participants');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('delivery_basis');
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->string('currency', 3)->default('RUB');
        });
    }
};

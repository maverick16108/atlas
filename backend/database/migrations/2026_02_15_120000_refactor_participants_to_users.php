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
        // 1. Add organization fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('inn')->nullable();
            $table->string('kpp')->nullable();
            $table->text('address')->nullable();
            $table->json('logistics_settings')->nullable();
            $table->decimal('delivery_basis', 8, 4)->nullable();
        });

        // 2. Update auction_participants table
        Schema::table('auction_participants', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropUnique(['auction_id', 'organization_id']);
            $table->dropColumn('organization_id');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['auction_id', 'user_id']);
        });

        // 3. Update initial_offers table
        Schema::table('initial_offers', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        // 4. Drop organizations table and clean up users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('organizations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate organizations table
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('inn')->nullable();
            $table->string('kpp')->nullable();
            $table->text('address')->nullable();
            $table->json('logistics_settings')->nullable();
            $table->decimal('delivery_basis', 8, 4)->nullable();
            $table->timestamps();
        });

        // Restore users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable();
            $table->dropColumn(['inn', 'kpp', 'address', 'logistics_settings', 'delivery_basis']);
        });

        // Restore auction_participants
        Schema::table('auction_participants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['auction_id', 'user_id']);
            $table->dropColumn('user_id');

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unique(['auction_id', 'organization_id']);
        });

        // Restore initial_offers
        Schema::table('initial_offers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
        });
    }
};

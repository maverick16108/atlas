<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes to speed up common queries.
     */
    public function up(): void
    {
        // === USERS ===
        Schema::table('users', function (Blueprint $table) {
            // Filtering by role (admin list, client list separation)
            $table->index('role');
            // Filtering accredited clients
            $table->index('is_accredited');
            // GPB participant lookups
            $table->index('is_gpb');
            // OTP login via auth_phone
            $table->index('auth_phone');
            // Last login sorting
            $table->index('last_login_at');
        });

        // === AUCTIONS ===
        Schema::table('auctions', function (Blueprint $table) {
            // Filtering auctions by status (most common filter)
            $table->index('status');
            // Sorting by dates
            $table->index('start_at');
            $table->index('end_at');
            // Composite: status + dates for filtered+sorted views
            $table->index(['status', 'start_at']);
        });

        // === BIDS ===
        Schema::table('bids', function (Blueprint $table) {
            // Fast lookup of all bids per auction (most used query)
            $table->index(['auction_id', 'amount']);
            // Fast lookup of user's bids in an auction
            $table->index(['auction_id', 'user_id']);
            // Sorting by time
            $table->index('created_at');
        });

        // === INITIAL OFFERS ===
        Schema::table('initial_offers', function (Blueprint $table) {
            // Fetch all offers for an auction
            $table->index('auction_id');
            // Fetch all offers by a user
            $table->index('user_id');
            // Composite for fetching a user's offer in a specific auction
            $table->index(['auction_id', 'user_id']);
        });

        // === AUCTION PARTICIPANTS ===
        Schema::table('auction_participants', function (Blueprint $table) {
            // Lookup all auctions a user participates in
            $table->index('user_id');
            // Filter by status (invited, accepted, etc.)
            $table->index('status');
        });

        // === CLIENT NOTIFICATIONS ===
        Schema::table('client_notifications', function (Blueprint $table) {
            // Filter by auction
            $table->index('auction_id');
            // Filter by type
            $table->index('type');
            // Sort by creation time
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_accredited']);
            $table->dropIndex(['is_gpb']);
            $table->dropIndex(['auth_phone']);
            $table->dropIndex(['last_login_at']);
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['start_at']);
            $table->dropIndex(['end_at']);
            $table->dropIndex(['status', 'start_at']);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropIndex(['auction_id', 'amount']);
            $table->dropIndex(['auction_id', 'user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('initial_offers', function (Blueprint $table) {
            $table->dropIndex(['auction_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['auction_id', 'user_id']);
        });

        Schema::table('auction_participants', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('client_notifications', function (Blueprint $table) {
            $table->dropIndex(['auction_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['created_at']);
        });
    }
};

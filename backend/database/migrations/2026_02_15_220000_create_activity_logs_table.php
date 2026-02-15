<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // created, updated, deleted
            $table->string('entity_type'); // auction, user, moderator, bid
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_name')->nullable(); // human-readable name for display after deletion
            $table->jsonb('changes')->nullable(); // { "field": { "old": ..., "new": ... } }
            $table->jsonb('metadata')->nullable(); // extra context (ip, related auction, etc.)
            $table->timestamp('created_at')->useCurrent();

            $table->index('entity_type');
            $table->index('action');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

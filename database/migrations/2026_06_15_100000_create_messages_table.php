<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // customer | ai | owner | system
            $table->string('direction'); // inbound | outbound
            $table->text('content')->nullable();
            $table->string('agent_used')->nullable();
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->string('external_mid')->nullable(); // platform message id (idempotency)
            $table->string('status')->default('sent'); // sent | suggested | failed
            $table->timestamps();

            $table->index(['store_id', 'conversation_id']);
            $table->index('external_mid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

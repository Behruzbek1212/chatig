<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // instagram | telegram
            $table->string('external_id'); // platform user id
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'channel', 'external_id']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('status')->default('open'); // open | ai_handling | needs_human | closed
            $table->string('mode')->default('suggest'); // suggest | auto
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('city')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status')->default('new'); // new | contacted | closed
            $table->string('source')->default('manual'); // instagram | telegram | manual
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('customers');
    }
};

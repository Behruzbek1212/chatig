<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // instagram | telegram
            $table->string('external_id')->nullable(); // IG business account id / bot id
            $table->string('username')->nullable();
            $table->text('access_token')->nullable(); // encrypted
            $table->string('status')->default('disconnected'); // connected | disconnected | error
            $table->json('meta')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('status')->default('new'); // new | confirmed | cancelled
            $table->unsignedBigInteger('total')->default(0); // so'm, snapshot
            $table->string('source')->default('telegram_mini_app');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // nullable so deleting a product keeps order history intact.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');           // snapshot at order time
            $table->unsignedBigInteger('unit_price');  // snapshot at order time
            $table->integer('quantity');
            $table->unsignedBigInteger('subtotal');    // unit_price * quantity, snapshot
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

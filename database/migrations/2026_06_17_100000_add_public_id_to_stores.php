<?php

use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->unique()->after('id');
        });

        // Backfill existing rows in PHP so it stays portable across sqlite/pgsql.
        Store::query()->whereNull('public_id')->get()->each(function (Store $store): void {
            $store->forceFill(['public_id' => (string) Str::ulid()])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};

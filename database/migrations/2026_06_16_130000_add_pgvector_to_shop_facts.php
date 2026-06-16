<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds pgvector-backed semantic search to shop_facts. PostgreSQL-only —
 * guarded so the sqlite test database (and any non-pgsql driver) skips it
 * cleanly.
 *
 * No ivfflat index here (unlike products): a store has on the order of a
 * few dozen facts at most, far too few to build meaningful IVF clusters —
 * an approximate index would be both unnecessary and worse-tuned than a
 * plain sequential scan over that few rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isPgsql()) {
            return;
        }

        $dimensions = (int) config('chatig.llm.embedding.dimensions', 1536);

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement("ALTER TABLE shop_facts ADD COLUMN IF NOT EXISTS embedding vector({$dimensions})");
    }

    public function down(): void
    {
        if (! $this->isPgsql()) {
            return;
        }

        if (Schema::hasColumn('shop_facts', 'embedding')) {
            DB::statement('ALTER TABLE shop_facts DROP COLUMN embedding');
        }
    }

    private function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};

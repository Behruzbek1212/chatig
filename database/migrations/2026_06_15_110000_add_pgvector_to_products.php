<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds pgvector-backed semantic search to products. PostgreSQL-only — guarded
 * so the sqlite test database (and any non-pgsql driver) skips it cleanly.
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
        DB::statement("ALTER TABLE products ADD COLUMN IF NOT EXISTS embedding vector({$dimensions})");

        // Approximate-nearest-neighbour index (cosine distance).
        DB::statement('CREATE INDEX IF NOT EXISTS products_embedding_idx ON products USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        if (! $this->isPgsql()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS products_embedding_idx');

        if (Schema::hasColumn('products', 'embedding')) {
            DB::statement('ALTER TABLE products DROP COLUMN embedding');
        }
    }

    private function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};

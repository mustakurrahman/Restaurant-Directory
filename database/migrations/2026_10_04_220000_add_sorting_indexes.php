<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the columns the site sorts by. Without them MySQL reads and sorts every row for each page of
     * results (checked with EXPLAIN on 5000 restaurants). slug, city_id, status and is_featured were already indexed.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->index('name');       // "Name A-Z", the default order, and the admin list
            $table->index('created_at'); // "Newest"
        });

        // The default public order is "featured first, then A-Z" for published restaurants. One index in exactly that
        // order lets MySQL read the first 12 rows straight off the index instead of sorting thousands. Written as raw
        // SQL because the schema builder cannot declare a descending column (works on MySQL 8 and SQLite).
        DB::statement('CREATE INDEX restaurants_listing_index ON restaurants (status, is_featured DESC, name)');

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['status', 'created_at']); // admin tabs: pending / approved / rejected, newest first
            $table->index('created_at');             // admin "All" tab
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex('restaurants_listing_index');
            $table->dropIndex(['name']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['created_at']);
        });
    }
};

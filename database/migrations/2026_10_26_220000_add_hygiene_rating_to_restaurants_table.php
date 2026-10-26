<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Food hygiene rating 0-5 as typed in by the owner; empty (null) means "no rating shown"
            $table->unsignedTinyInteger('hygiene_rating')->nullable()->after('price_range');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('hygiene_rating');
        });
    }
};

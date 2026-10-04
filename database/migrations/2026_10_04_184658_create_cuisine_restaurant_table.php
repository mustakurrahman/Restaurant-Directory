<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuisine_restaurant', function (Blueprint $table) {
            // cascade: deleting either side also removes the link rows
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cuisine_id')->constrained()->cascadeOnDelete();

            // Same pair can only be saved once
            $table->primary(['restaurant_id', 'cuisine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuisine_restaurant');
    }
};

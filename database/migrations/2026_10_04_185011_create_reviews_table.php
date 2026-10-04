<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // cascade: reviews go away with their restaurant
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('email'); // private, never shown publicly
            $table->unsignedTinyInteger('rating'); // 1 to 5, checked by validation
            $table->text('comment');

            // pending, approved or rejected; only approved is public
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            // Speeds up "approved reviews for this restaurant"
            $table->index(['restaurant_id', 'status']);
            // Speeds up the admin "pending reviews" list
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

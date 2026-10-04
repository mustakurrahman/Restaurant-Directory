<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('address');

            // restrict: a city that still has restaurants cannot be deleted by accident
            // (foreignId + constrained also adds an index on city_id)
            $table->foreignId('city_id')->constrained()->restrictOnDelete();

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // 1 to 4 = $ to $$$$ (range is checked by validation)
            $table->unsignedTinyInteger('price_range')->default(2)->index();

            // 7 decimals is about 1 cm of precision, plenty for a map pin
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('cover_image')->nullable();
            $table->boolean('is_featured')->default(false)->index();

            // draft or published; only published restaurants are public
            $table->string('status', 20)->default('draft')->index();

            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};

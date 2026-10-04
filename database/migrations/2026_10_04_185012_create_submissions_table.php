<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Visitor-suggested restaurants; kept apart from `restaurants` until the owner approves them
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_name');
            $table->string('address');

            // Plain text: the visitor's city/cuisine may not exist in our lists yet
            $table->string('city', 100);
            $table->string('cuisine', 100)->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();

            $table->string('submitter_name', 100);
            $table->string('submitter_email');

            // pending, approved or rejected
            $table->string('status', 20)->default('pending')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};

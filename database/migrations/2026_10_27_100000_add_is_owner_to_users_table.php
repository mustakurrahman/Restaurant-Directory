<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The Owner account cannot be deleted from the Users panel, and only the Owner can change it
            $table->boolean('is_owner')->default(false)->after('password');
        });

        // Accounts that already exist: the first one ever created is the Owner
        $first = DB::table('users')->min('id');

        if ($first !== null) {
            DB::table('users')->where('id', $first)->update(['is_owner' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};

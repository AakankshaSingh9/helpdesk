<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's database session driver writes the authenticated user's id into
     * sessions.user_id. Now that user ids are strings (Better Auth shape), that
     * column must be a string instead of the default bigint.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // Drop the index before the column. Postgres cascades this for us,
            // but SQLite (the test database) leaves the index dangling and errors
            // unless it's removed explicitly first.
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->index()->after('id');
        });
    }
};

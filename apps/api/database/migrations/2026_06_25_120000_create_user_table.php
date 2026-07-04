<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Translated from the Better Auth Prisma `User` model (@@map("user")).
     * Column names are kept as defined in the schema (camelCase); Prisma only
     * remaps the table name, not the fields.
     */
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->string('id')->primary();          // String @id (app-generated id)
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('emailVerified')->default(false);
            $table->string('image')->nullable();
            $table->string('role')->default('agent'); // Role enum -> string; default agent
            $table->timestamp('createdAt');
            $table->timestamp('updatedAt');
            $table->timestamp('deletedAt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};

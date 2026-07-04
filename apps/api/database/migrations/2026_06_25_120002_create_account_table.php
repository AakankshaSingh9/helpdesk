<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Translated from the Better Auth Prisma `Account` model (@@map("account")).
     * For credential (email/password) accounts, providerId is "credential" and
     * the hashed password lives in the `password` column. OAuth accounts use the
     * token columns. userId is a FK to user.id with onDelete: Cascade.
     */
    public function up(): void
    {
        Schema::create('account', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('accountId');
            $table->string('providerId');
            $table->string('userId');
            $table->text('accessToken')->nullable();
            $table->text('refreshToken')->nullable();
            $table->text('idToken')->nullable();
            $table->timestamp('accessTokenExpiresAt')->nullable();
            $table->timestamp('refreshTokenExpiresAt')->nullable();
            $table->string('scope')->nullable();
            $table->string('password')->nullable();
            $table->timestamp('createdAt');
            $table->timestamp('updatedAt');

            $table->foreign('userId')
                ->references('id')->on('user')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contacts are the external people who email support — the "customer" side of
     * a ticket. Deliberately separate from `user` (staff/agents, Better Auth
     * shape): contacts never authenticate. This table follows standard Laravel
     * conventions (bigint id, snake_case, created_at/updated_at).
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

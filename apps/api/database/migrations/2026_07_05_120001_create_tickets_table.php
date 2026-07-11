<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ticket is one support conversation. Opened from an inbound email and
     * threaded onto by later replies.
     *
     * - `reference` is a short public token (e.g. TKT-A1B2C3) embedded in outbound
     *   subject lines so customer replies can be threaded back deterministically.
     * - `status` / `category` are string-backed enums (portable across Postgres
     *   and the SQLite test DB — no native enum type). `category` is nullable:
     *   AI classification lands in Phase 3; until then tickets stay uncategorised.
     * - `assigned_to` references the string `user.id` (Better Auth shape), nullable
     *   until an agent is assigned.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('subject');
            $table->string('status')->default('open');
            $table->string('category')->nullable();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('assigned_to')->nullable();
            $table->timestamps();

            $table->foreign('assigned_to')
                ->references('id')->on('user')
                ->nullOnDelete();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

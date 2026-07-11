<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One message in a ticket thread — inbound (from the customer) or outbound
     * (a reply we sent). Email plumbing lives here:
     *
     * - `message_id` is the RFC822 `Message-ID` header, unique so re-delivery of
     *   the same email is idempotent (dedupe key).
     * - `in_reply_to` / `email_references` capture the threading headers used to
     *   attach a reply to an existing ticket. (`references` is a reserved word in
     *   some engines, hence `email_references`.)
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('direction'); // inbound | outbound
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->text('body_text');
            $table->string('message_id')->unique();
            $table->string('in_reply_to')->nullable();
            $table->text('email_references')->nullable();
            $table->timestamps();

            $table->index('in_reply_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

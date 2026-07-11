<?php

namespace App\Models;

use App\Enums\MessageDirection;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a ticket thread. Carries the email threading headers used to
 * dedupe and attach follow-ups (see the create_messages_table migration).
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'direction',
        'from_email',
        'from_name',
        'body_text',
        'message_id',
        'in_reply_to',
        'email_references',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}

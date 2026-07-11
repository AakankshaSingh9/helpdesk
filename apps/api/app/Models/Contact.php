<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * External person who emails support. Matched to inbound mail by email address.
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
    ];

    /**
     * The contact's first name — the first whitespace-separated token of `name`
     * — or null when we have no name to greet them by (caller degrades to a
     * generic greeting).
     */
    public function firstName(): ?string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return null;
        }

        return explode(' ', $name, 2)[0];
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}

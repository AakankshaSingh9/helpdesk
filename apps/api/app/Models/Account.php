<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Account extends Model
{
    /**
     * Better Auth `account` table: holds OAuth provider links and, for
     * providerId = "credential", the bcrypt password hash used by Fortify.
     */
    protected $table = 'account';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'accountId',
        'providerId',
        'userId',
        'accessToken',
        'refreshToken',
        'idToken',
        'accessTokenExpiresAt',
        'refreshTokenExpiresAt',
        'scope',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'accessToken',
        'refreshToken',
        'idToken',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessTokenExpiresAt' => 'datetime',
            'refreshTokenExpiresAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Account $account) {
            if (empty($account->id)) {
                $account->id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}

<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a user for the admin user list. Deliberately narrow — no
 * password/account data, only what the agent-facing UI renders.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'emailVerified' => (bool) $this->emailVerified,
            'createdAt' => $this->createdAt?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'fromEmail' => $this->from_email,
            'fromName' => $this->from_name,
            'body' => $this->body_text,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

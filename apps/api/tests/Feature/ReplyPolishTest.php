<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReplyPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_polish_returns_the_ai_improved_draft_when_enabled(): void
    {
        config(['ai.enabled' => true, 'ai.openai.key' => 'test-key']);
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Hello! Thanks for reaching out — happy to help.']]],
            ]),
        ]);

        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tickets/{$ticket->id}/polish", ['draft' => 'thx will help u'])
            ->assertOk()
            ->assertJsonPath('data.aiApplied', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.polished', 'Hello! Thanks for reaching out — happy to help.');
    }

    public function test_polish_falls_back_to_the_original_draft_when_ai_is_off(): void
    {
        config(['ai.enabled' => false]);
        Http::fake(); // ensure no outbound call is made

        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tickets/{$ticket->id}/polish", ['draft' => 'original text'])
            ->assertOk()
            ->assertJsonPath('data.aiApplied', false)
            ->assertJsonPath('data.reason', 'disabled')
            ->assertJsonPath('data.polished', 'original text');

        Http::assertNothingSent();
    }

    public function test_polish_falls_back_when_the_ai_call_fails(): void
    {
        config(['ai.enabled' => true, 'ai.openai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response(['error' => 'boom'], 500)]);

        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tickets/{$ticket->id}/polish", ['draft' => 'keep me'])
            ->assertOk()
            ->assertJsonPath('data.aiApplied', false)
            ->assertJsonPath('data.reason', 'failed')
            ->assertJsonPath('data.polished', 'keep me');
    }

    public function test_polish_requires_authentication_and_a_draft(): void
    {
        $ticket = Ticket::factory()->create();

        $this->postJson("/api/tickets/{$ticket->id}/polish", ['draft' => 'x'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tickets/{$ticket->id}/polish", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('draft');
    }
}

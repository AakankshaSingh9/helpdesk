<?php

namespace App\Http\Controllers;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Aggregate ticket metrics for the agent dashboard: headline counts (active,
 * resolved, priority) plus a category breakdown for the bar chart.
 *
 * Authenticated (auth:sanctum) — available to both roles, mirroring the ticket
 * read endpoints. Every metric is derived from the same filtered base query, so
 * the whole dashboard responds to the same criteria (category, status, assigned
 * agent, date range).
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['sometimes', Rule::in(['general', 'technical', 'refund', 'uncategorised'])],
            'status' => ['sometimes', Rule::in(['open', 'resolved', 'closed'])],
            // Agent id (string user.id), or the literal 'unassigned'.
            'assigned_to' => ['sometimes', 'string', 'max:255'],
            // Rolling window in days over created_at; 'all' = no date bound.
            'range' => ['sometimes', Rule::in(['7', '30', '90', 'all'])],
        ]);

        $base = Ticket::query();

        if (($category = $validated['category'] ?? null) !== null) {
            $category === 'uncategorised'
                ? $base->whereNull('category')
                : $base->where('category', $category);
        }

        if (($status = $validated['status'] ?? null) !== null) {
            $base->where('status', $status);
        }

        if (($agent = $validated['assigned_to'] ?? null) !== null && $agent !== 'all') {
            $agent === 'unassigned'
                ? $base->whereNull('assigned_to')
                : $base->where('assigned_to', $agent);
        }

        if (($range = $validated['range'] ?? 'all') !== 'all') {
            $base->where('created_at', '>=', now()->subDays((int) $range));
        }

        return response()->json([
            'data' => [
                'stats' => [
                    'active' => $this->count($base, fn ($q) => $q->where('status', TicketStatus::Open->value)),
                    'resolved' => $this->count($base, fn ($q) => $q->where('status', TicketStatus::Resolved->value)),
                    // "Priority" = open tickets in the refund category.
                    'priority' => $this->count($base, fn ($q) => $q
                        ->where('status', TicketStatus::Open->value)
                        ->where('category', TicketCategory::Refund->value)),
                    'total' => $this->count($base, fn ($q) => $q),
                ],
                'byCategory' => [
                    $this->categoryRow($base, 'general', 'General'),
                    $this->categoryRow($base, 'technical', 'Technical'),
                    $this->categoryRow($base, 'refund', 'Refund'),
                    $this->categoryRow($base, 'uncategorised', 'Uncategorised'),
                ],
            ],
        ]);
    }

    /**
     * Count against a fresh clone of the filtered base query so each metric is
     * independent (a Builder is mutable — reusing it would compound wheres).
     *
     * @param  callable(Builder): Builder  $scope
     */
    private function count(Builder $base, callable $scope): int
    {
        return $scope((clone $base))->count();
    }

    /**
     * One bar in the category breakdown. 'uncategorised' maps to a null category
     * (tickets not yet classified).
     *
     * @return array{category: string, label: string, count: int}
     */
    private function categoryRow(Builder $base, string $category, string $label): array
    {
        $count = $category === 'uncategorised'
            ? $this->count($base, fn ($q) => $q->whereNull('category'))
            : $this->count($base, fn ($q) => $q->where('category', $category));

        return ['category' => $category, 'label' => $label, 'count' => $count];
    }
}

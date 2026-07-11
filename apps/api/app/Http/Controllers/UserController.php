<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * List users for the admin management screen.
     *
     * Admin-only — enforced by the `admin` middleware on the route, not here.
     * Soft-deleted users are excluded by the model's SoftDeletes scope. An
     * optional `search` term filters by name or email (case-insensitive).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when(
                $validated['search'] ?? null,
                fn ($query, string $term) => $query->where(
                    fn ($q) => $q
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($term).'%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%'.strtolower($term).'%']),
                ),
            )
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Soft-delete a user.
     *
     * Admin-only (route middleware). Admins themselves are never deletable —
     * they're a privilege anchor, so removing one is refused with a 403.
     * Deletion is soft (SoftDeletes sets `deletedAt`); the row stays in the
     * table but drops out of every default query, including the list above.
     */
    public function destroy(User $user): Response
    {
        if ($user->role === 'admin') {
            abort(403, 'Administrators cannot be deleted.');
        }

        $user->delete();

        return response()->noContent();
    }
}
